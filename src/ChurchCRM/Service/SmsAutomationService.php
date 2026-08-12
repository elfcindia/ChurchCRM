<?php

namespace ChurchCRM\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\FamilyQuery;
use ChurchCRM\model\ChurchCRM\Map\FamilyTableMap;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\RecordPropertyQuery;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Utils\DateTimeUtils;
use ChurchCRM\Utils\LoggerUtils;
use Propel\Runtime\ActiveQuery\Criteria;

/**
 * Sends birthday/anniversary greetings and one-off congregation/group
 * announcements by SMS, via whichever SMS plugin is active (currently Vonage).
 *
 * Birthday/anniversary sends are idempotent per day (tracked in SystemConfig)
 * so the cron endpoint can safely be hit more than once without double-texting
 * anyone; pass $force to bypass that guard for a manual "send now" trigger.
 */
class SmsAutomationService
{
    /**
     * Resolve the active, configured SMS plugin, or null if none is set up.
     */
    private function getSmsPlugin(): ?object
    {
        $plugin = PluginManager::getPlugin('vonage');
        if ($plugin === null || !$plugin->isConfigured()) {
            return null;
        }

        return $plugin;
    }

    /**
     * Person IDs that have opted out of SMS, per the configured "Do Not SMS" property.
     *
     * @return array<int, true>
     */
    private function getExcludedPersonIds(): array
    {
        $propertyId = SystemConfig::getIntValue('iDoNotSmsPropertyId');
        if ($propertyId <= 0) {
            return [];
        }

        $excluded = [];
        foreach (RecordPropertyQuery::create()->filterByPropertyId($propertyId)->find() as $record) {
            $excluded[(int) $record->getRecordId()] = true;
        }

        return $excluded;
    }

    /**
     * Normalise a stored cell phone number to E.164, assuming India (+91) when
     * no country code is present — this deployment serves an India-based
     * congregation, so a bare 10-digit number is treated as a local Indian
     * mobile number rather than guessing NANP (+1).
     */
    private function toE164(string $rawPhone): ?string
    {
        $digits = preg_replace('/\D/', '', $rawPhone);
        if (empty($digits)) {
            return null;
        }

        if (str_starts_with($rawPhone, '+')) {
            return '+' . $digits;
        }

        if (strlen($digits) === 10) {
            return '+91' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return '+' . $digits;
        }

        return '+' . $digits;
    }

    private function renderTemplate(string $template, string $name): string
    {
        return str_replace(
            ['{Name}', '{Church}'],
            [$name, SystemConfig::getValue('sChurchName')],
            $template
        );
    }

    /**
     * @param Person[] $people
     * @return array{sent: int, failed: int, skipped: int}
     */
    private function sendToPeople(object $plugin, array $people, string $template): array
    {
        $excluded = $this->getExcludedPersonIds();
        $sent = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($people as $person) {
            if (isset($excluded[(int) $person->getId()])) {
                $skipped++;
                continue;
            }

            $phone = $this->toE164((string) $person->getCellPhone());
            if ($phone === null) {
                $skipped++;
                continue;
            }

            $message = $this->renderTemplate($template, $person->getFirstName());
            if (method_exists($plugin, 'sendSMS') && $plugin->sendSMS($phone, $message)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return ['sent' => $sent, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * @return array{ran: bool, reason?: string, sent?: int, failed?: int, skipped?: int}
     */
    public function sendBirthdayMessages(bool $force = false): array
    {
        if (!$force && SystemConfig::getValue('dLastBirthdaySmsRun') === DateTimeUtils::getTodayDate()) {
            return ['ran' => false, 'reason' => 'already_run_today'];
        }

        $plugin = $this->getSmsPlugin();
        if ($plugin === null) {
            return ['ran' => false, 'reason' => 'sms_not_configured'];
        }

        $today = DateTimeUtils::getToday();
        $people = PersonQuery::create()
            ->filterByBirthMonth($today->format('m'))
            ->filterByBirthDay($today->format('d'))
            ->find();

        $result = $this->sendToPeople($plugin, iterator_to_array($people), SystemConfig::getValue('sBirthdaySmsTemplate'));
        SystemConfig::setValue('dLastBirthdaySmsRun', DateTimeUtils::getTodayDate());

        LoggerUtils::getAppLogger()->info('Birthday SMS job complete', $result);

        return array_merge(['ran' => true], $result);
    }

    /**
     * @return array{ran: bool, reason?: string, sent?: int, failed?: int, skipped?: int}
     */
    public function sendAnniversaryMessages(bool $force = false): array
    {
        if (!$force && SystemConfig::getValue('dLastAnniversarySmsRun') === DateTimeUtils::getTodayDate()) {
            return ['ran' => false, 'reason' => 'already_run_today'];
        }

        $plugin = $this->getSmsPlugin();
        if ($plugin === null) {
            return ['ran' => false, 'reason' => 'sms_not_configured'];
        }

        $today = DateTimeUtils::getToday();
        $families = FamilyQuery::create()
            ->filterByDateDeactivated(null)
            ->filterByWeddingdate(null, Criteria::ISNOTNULL)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'MONTH(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') = ' . (int) $today->format('m'), Criteria::CUSTOM)
            ->addUsingAlias(FamilyTableMap::COL_FAM_WEDDINGDATE, 'DAY(' . FamilyTableMap::COL_FAM_WEDDINGDATE . ') = ' . (int) $today->format('d'), Criteria::CUSTOM)
            ->find();

        $couples = [];
        foreach ($families as $family) {
            $couples = array_merge($couples, $family->getAdults());
        }

        $result = $this->sendToPeople($plugin, $couples, SystemConfig::getValue('sAnniversarySmsTemplate'));
        SystemConfig::setValue('dLastAnniversarySmsRun', DateTimeUtils::getTodayDate());

        LoggerUtils::getAppLogger()->info('Anniversary SMS job complete', $result);

        return array_merge(['ran' => true], $result);
    }

    /**
     * Run whichever of the daily jobs are enabled in settings.
     *
     * @return array{birthday: array, anniversary: array}
     */
    public function runDailyJobs(bool $force = false): array
    {
        $result = ['birthday' => ['ran' => false, 'reason' => 'disabled'], 'anniversary' => ['ran' => false, 'reason' => 'disabled']];

        if (SystemConfig::getBooleanValue('bSendBirthdaySms')) {
            $result['birthday'] = $this->sendBirthdayMessages($force);
        }
        if (SystemConfig::getBooleanValue('bSendAnniversarySms')) {
            $result['anniversary'] = $this->sendAnniversaryMessages($force);
        }

        return $result;
    }

    /**
     * Cell phone numbers (E.164) for every person in the congregation who has
     * one on file and hasn't opted out of SMS — the "whole congregation" recipient set.
     *
     * @return string[]
     */
    public function getCongregationPhones(): array
    {
        $excluded = $this->getExcludedPersonIds();
        $phones = [];
        $seen = [];

        foreach (PersonQuery::create()->find() as $person) {
            if (isset($excluded[(int) $person->getId()])) {
                continue;
            }
            $phone = $this->toE164((string) $person->getCellPhone());
            if ($phone === null || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $phones[] = $phone;
        }

        return $phones;
    }

    /**
     * Cell phone numbers (E.164) for members of a single group, excluding SMS opt-outs.
     *
     * @return string[]
     */
    public function getGroupPhones(int $groupId): array
    {
        $excluded = $this->getExcludedPersonIds();
        $phones = [];
        $seen = [];

        $memberships = Person2group2roleP2g2rQuery::create()
            ->filterByGroupId($groupId)
            ->innerJoinWithPerson()
            ->find();

        foreach ($memberships as $membership) {
            $person = $membership->getPerson();
            if ($person === null || isset($excluded[(int) $person->getId()])) {
                continue;
            }
            $phone = $this->toE164((string) $person->getCellPhone());
            if ($phone === null || isset($seen[$phone])) {
                continue;
            }
            $seen[$phone] = true;
            $phones[] = $phone;
        }

        return $phones;
    }

    /**
     * Send the same free-form message to an arbitrary list of phone numbers
     * (already-resolved E.164), for the announcement composer.
     *
     * @param string[] $phones
     * @return array{sent: int, failed: int, total: int}
     */
    public function broadcast(array $phones, string $message): array
    {
        $plugin = $this->getSmsPlugin();
        if ($plugin === null || !method_exists($plugin, 'sendBulkSMS')) {
            return ['sent' => 0, 'failed' => count($phones), 'total' => count($phones)];
        }

        $results = $plugin->sendBulkSMS($phones, $message);
        $sent = count(array_filter($results));

        return ['sent' => $sent, 'failed' => count($results) - $sent, 'total' => count($results)];
    }

    /**
     * Get the token that authorizes the external cron endpoint, generating and
     * persisting one on first use.
     */
    public function getOrCreateScheduledMessagesToken(): string
    {
        $token = SystemConfig::getValue('sScheduledMessagesToken');
        if (empty($token)) {
            $token = bin2hex(random_bytes(32));
            SystemConfig::setValue('sScheduledMessagesToken', $token);
        }

        return $token;
    }
}
