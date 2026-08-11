<?php

declare(strict_types=1);

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\ChurchMetaData;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Family;
use ChurchCRM\model\ChurchCRM\FamilyCustom;
use ChurchCRM\model\ChurchCRM\ListOptionQuery;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Map\PersonTableMap;
use ChurchCRM\model\ChurchCRM\Map\WorshipAttendTableMap;
use ChurchCRM\model\ChurchCRM\WorshipAttend;
use ChurchCRM\model\ChurchCRM\WorshipAttendQuery;
use ChurchCRM\Utils\InputUtils;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Propel;

class WorshipAttendanceService
{
    public static function localTodayYmd(): string
    {
        $tzName = SystemConfig::getValue('sTimeZone') ?: 'UTC';

        return (new DateTimeImmutable('now', new DateTimeZone($tzName)))->format('Y-m-d');
    }

    public static function normalizeDate(?string $dateYmd): string
    {
        if ($dateYmd === null || $dateYmd === '') {
            return self::localTodayYmd();
        }
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $dateYmd);

        return $dt !== false ? $dt->format('Y-m-d') : self::localTodayYmd();
    }

    /**
     * Active people: not inactive classification, family not deactivated (matches main dashboard logic).
     */
    public static function activePersonQuery(): PersonQuery
    {
        $q = PersonQuery::create()->leftJoinWithFamily();
        $sInactiveClassificationIds = SystemConfig::getValue('sInactiveClassification');
        if ($sInactiveClassificationIds === '') {
            $sInactiveClassificationIds = '-1';
        }
        $aInactiveClassificationIds = array_map('intval', explode(',', $sInactiveClassificationIds));
        $q->filterByClsId($aInactiveClassificationIds, Criteria::NOT_IN)
            ->where('Family.DateDeactivated is null');

        return $q;
    }

    /**
     * Expected members for absence reporting: optional group filter from system config.
     */
    public static function expectedPersonQuery(): PersonQuery
    {
        $q = self::activePersonQuery();
        $groupId = (int) SystemConfig::getValue('iWorshipAttendanceMemberGroupId');
        if ($groupId > 0) {
            $q->distinct()
                ->usePerson2group2roleP2g2rQuery()
                    ->filterByGroupId($groupId)
                ->endUse();
        }

        return $q;
    }

    /**
     * @return list<int>
     */
    public static function getExpectedPersonIds(): array
    {
        return self::expectedPersonQuery()
            ->select(['Id'])
            ->orderByLastName()
            ->orderByFirstName()
            ->find()
            ->getData();
    }

    public static function recordCheckIn(int $personId, ?string $dateYmd = null): WorshipAttend
    {
        $person = PersonQuery::create()->findPk($personId);
        if ($person === null) {
            throw new \InvalidArgumentException(gettext('Person not found'));
        }

        $date = self::normalizeDate($dateYmd);
        $attendDate = new DateTime($date);

        $row = WorshipAttendQuery::create()
            ->filterByPersonId($personId)
            ->filterByAttendDate($attendDate)
            ->findOne();

        if ($row === null) {
            $row = new WorshipAttend();
            $row->setPersonId($personId);
            $row->setAttendDate($attendDate);
        }

        $row->setCheckinDatetime(new DateTime());
        $greeterPersonId = AuthenticationManager::getCurrentUser()->getId();
        $row->setCheckedInById($greeterPersonId > 0 ? $greeterPersonId : null);
        $row->save();

        return $row;
    }

    public static function removeCheckIn(int $personId, ?string $dateYmd = null): bool
    {
        $date = self::normalizeDate($dateYmd);
        $attendDate = new DateTime($date);

        return WorshipAttendQuery::create()
            ->filterByPersonId($personId)
            ->filterByAttendDate($attendDate)
            ->delete() > 0;
    }

    /**
     * @return array{date: string, expectedTotal: int, attendedExpected: int, absentTotal: int, guestAttended: int, attendedAll: int}
     */
    public static function getSummary(string $dateYmd): array
    {
        $date = self::normalizeDate($dateYmd);
        $attendDate = new DateTime($date);

        $expectedIds = self::getExpectedPersonIds();
        $expectedSet = array_fill_keys($expectedIds, true);
        $expectedTotal = count($expectedIds);

        $attendedRows = WorshipAttendQuery::create()
            ->filterByAttendDate($attendDate)
            ->select(['PersonId'])
            ->find()
            ->getData();

        $attendedIds = array_map('intval', $attendedRows);
        $attendedSet = array_fill_keys($attendedIds, true);

        $attendedExpected = 0;
        foreach ($attendedIds as $pid) {
            if (isset($expectedSet[$pid])) {
                $attendedExpected++;
            }
        }

        $absentTotal = $expectedTotal - $attendedExpected;

        $guestAttended = 0;
        foreach ($attendedIds as $pid) {
            if (!isset($expectedSet[$pid])) {
                $guestAttended++;
            }
        }

        return [
            'date'              => $date,
            'expectedTotal'     => $expectedTotal,
            'attendedExpected'  => $attendedExpected,
            'absentTotal'       => $absentTotal,
            'guestAttended'     => $guestAttended,
            'attendedAll'       => count($attendedIds),
        ];
    }

    /**
     * @return list<array{id: int, firstName: string, lastName: string, familyName: string, checkedIn: bool, checkinTime: ?string}>
     */
    public static function searchGreeter(string $query, string $dateYmd, int $limit = 25): array
    {
        $date = self::normalizeDate($dateYmd);
        $attendDate = new DateTime($date);
        $q = trim($query);
        if (strlen($q) < 2) {
            return [];
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';
        // Propel where() accepts one value: use an array when the clause has multiple ? placeholders.
        $people = self::activePersonQuery()
            ->where(
                '(' . PersonTableMap::COL_PER_FIRSTNAME . ' LIKE ? OR ' . PersonTableMap::COL_PER_LASTNAME . ' LIKE ? OR ' . FamilyTableMap::COL_FAM_NAME . ' LIKE ?)',
                [$like, $like, $like],
            )
            ->orderByLastName()
            ->orderByFirstName()
            ->limit($limit)
            ->find();

        $checked = [];
        $ids = [];
        foreach ($people as $p) {
            $ids[] = $p->getId();
        }
        if ($ids !== []) {
            $rows = WorshipAttendQuery::create()
                ->filterByAttendDate($attendDate)
                ->filterByPersonId($ids, Criteria::IN)
                ->find();
            foreach ($rows as $r) {
                $checked[$r->getPersonId()] = $r->getCheckinDatetime()?->format('Y-m-d H:i:s');
            }
        }

        $out = [];
        foreach ($people as $person) {
            $fid = $person->getId();
            $fam = $person->getFamily();
            $out[] = [
                'id'           => $fid,
                'firstName'    => $person->getFirstName() ?? '',
                'lastName'     => $person->getLastName() ?? '',
                'familyName'   => $fam !== null ? (string) $fam->getName() : '',
                'checkedIn'    => isset($checked[$fid]),
                'checkinTime'  => $checked[$fid] ?? null,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{personId: int, firstName: string, lastName: string, familyName: string, checkinTime: string}>
     */
    public static function listAttendedDetails(string $dateYmd): array
    {
        $date = self::normalizeDate($dateYmd);
        $attendDate = new DateTime($date);

        $rows = WorshipAttendQuery::create()
            ->filterByAttendDate($attendDate)
            ->joinWithPerson()
            ->usePersonQuery()
                ->leftJoinWithFamily()
            ->endUse()
            ->orderByCheckinDatetime()
            ->find();

        $list = [];
        foreach ($rows as $row) {
            $p = $row->getPerson();
            if ($p === null) {
                continue;
            }
            $fam = $p->getFamily();
            $list[] = [
                'personId'    => $p->getId(),
                'firstName'   => $p->getFirstName() ?? '',
                'lastName'    => $p->getLastName() ?? '',
                'familyName'  => $fam !== null ? (string) $fam->getName() : '',
                'checkinTime' => $row->getCheckinDatetime()?->format('Y-m-d H:i:s') ?? '',
            ];
        }

        return $list;
    }

    /**
     * @return list<array{personId: int, firstName: string, lastName: string, cellPhone: string, homePhone: string, familyName: string}>
     */
    public static function listAbsentExpectedDetails(string $dateYmd): array
    {
        $summary = self::getSummary($dateYmd);
        $date = $summary['date'];
        $attendDate = new DateTime($date);

        $expectedIds = self::getExpectedPersonIds();
        if ($expectedIds === []) {
            return [];
        }

        $attended = WorshipAttendQuery::create()
            ->filterByAttendDate($attendDate)
            ->filterByPersonId($expectedIds, Criteria::IN)
            ->select(['PersonId'])
            ->find()
            ->getData();

        $attendedSet = array_fill_keys(array_map('intval', $attended), true);
        $absentIds = [];
        foreach ($expectedIds as $eid) {
            if (!isset($attendedSet[$eid])) {
                $absentIds[] = $eid;
            }
        }

        if ($absentIds === []) {
            return [];
        }

        $people = PersonQuery::create()
            ->filterById($absentIds, Criteria::IN)
            ->leftJoinWithFamily()
            ->orderByLastName()
            ->orderByFirstName()
            ->find();

        $list = [];
        foreach ($people as $person) {
            $fam = $person->getFamily();
            $cell = (string) ($person->getCellPhone() ?? '');
            $home = (string) ($person->getHomePhone() ?? '');
            if ($home === '' && $fam !== null) {
                $home = (string) ($fam->getHomePhone() ?? '');
            }

            $list[] = [
                'personId'   => $person->getId(),
                'firstName'  => $person->getFirstName() ?? '',
                'lastName'   => $person->getLastName() ?? '',
                'cellPhone'  => $cell,
                'homePhone'  => $home,
                'familyName' => $fam !== null ? (string) $fam->getName() : '',
            ];
        }

        return $list;
    }

    public static function buildAbsenteesCsv(string $dateYmd): string
    {
        $rows = self::listAbsentExpectedDetails($dateYmd);
        $fh = fopen('php://temp', 'r+');
        if ($fh === false) {
            return '';
        }
        fputcsv($fh, [
            gettext('Last name'),
            gettext('First name'),
            gettext('Family'),
            gettext('Cell phone'),
            gettext('Home phone'),
        ]);
        foreach ($rows as $r) {
            fputcsv($fh, [
                $r['lastName'],
                $r['firstName'],
                $r['familyName'],
                $r['cellPhone'],
                $r['homePhone'],
            ]);
        }
        rewind($fh);
        $csv = stream_get_contents($fh) ?: '';
        fclose($fh);

        return $csv;
    }

    public static function buildAbsenteesPdf(string $dateYmd): string
    {
        $rows = self::listAbsentExpectedDetails($dateYmd);
        $report = new \ChurchCRM\Reports\PdfAbsenteeReport(ChurchMetaData::getChurchName(), $dateYmd);
        $report->addRows($rows);

        return $report->Output('S');
    }

    /**
     * Minimal family + person for greeter walk-in; auto checked in for the given date.
     *
     * @return array{personId: int, familyId: int}
     */
    public static function createQuickVisitorAndCheckIn(
        string $firstName,
        string $lastName,
        ?string $cellPhone,
        ?string $familyName,
        ?string $dateYmd = null,
    ): array {
        $firstName = InputUtils::sanitizeAndEscapeText($firstName);
        $lastName = InputUtils::sanitizeAndEscapeText($lastName);
        $cellPhone = $cellPhone !== null ? InputUtils::sanitizeAndEscapeText($cellPhone) : '';
        $familyName = $familyName !== null ? InputUtils::sanitizeAndEscapeText($familyName) : '';

        if (strlen($firstName) < 2 || strlen($lastName) < 2) {
            throw new \InvalidArgumentException(gettext('First and last name must be at least 2 characters'));
        }

        $famLabel = $familyName !== '' ? $familyName : sprintf('%s, %s', $lastName, $firstName);
        if (strlen($famLabel) < 2) {
            $famLabel = $lastName . ' ' . gettext('Guest');
        }

        $defaultCls = ListOptionQuery::create()
            ->filterById(1)
            ->filterByOptionId(0, Criteria::GREATER_THAN)
            ->orderByOptionSequence()
            ->findOne();
        $clsId = $defaultCls !== null ? (int) $defaultCls->getOptionId() : 1;

        $headRole = ListOptionQuery::create()
            ->filterById(2)
            ->orderByOptionSequence()
            ->findOne();
        $fmrId = $headRole !== null ? (int) $headRole->getOptionId() : 1;

        $userId = AuthenticationManager::getCurrentUser()->getId();

        $con = Propel::getWriteConnection(WorshipAttendTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $family = new Family();
            $family->setName($famLabel);
            $family->setCity(SystemConfig::getValue('sDefaultCity') ?? '');
            $family->setState(SystemConfig::getValue('sDefaultState') ?? '');
            $family->setCountry(SystemConfig::getValue('sDefaultCountry') ?? '');
            $family->setZip(SystemConfig::getValue('sDefaultZip') ?? '');
            $family->setSendNewsletter('FALSE');
            $family->setDateEntered(new DateTime());
            $family->setEnteredBy($userId);
            $family->save();

            $fc = new FamilyCustom();
            $fc->setFamId($family->getId());
            $fc->save();

            $person = new Person();
            $person->setFirstName($firstName);
            $person->setLastName($lastName);
            $person->setCellPhone($cellPhone);
            $person->setGender(0);
            $person->setFmrId($fmrId);
            $person->setClsId($clsId);
            $person->setFamId($family->getId());
            $person->setDateEntered(new DateTime());
            $person->setEnteredBy($userId);
            $person->save();

            $pc = new PersonCustom();
            $pc->setPerId($person->getId());
            $pc->save();

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }

        self::recordCheckIn($person->getId(), $dateYmd);

        return ['personId' => $person->getId(), 'familyId' => $family->getId()];
    }
}
