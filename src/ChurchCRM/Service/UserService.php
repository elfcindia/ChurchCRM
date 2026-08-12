<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Person;
use ChurchCRM\model\ChurchCRM\PersonCustom;
use ChurchCRM\model\ChurchCRM\Map\UserTableMap;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\model\ChurchCRM\UserQuery;
use ChurchCRM\Utils\InputUtils;
use DateTime;
use Propel\Runtime\Collection\ObjectCollection;
use Propel\Runtime\Propel;

class UserService
{
    /**
     * Get all users
     * @return User[]|ObjectCollection
     */
    public function getAllUsers()
    {
        return UserQuery::create()->find();
    }

    /**
     * Get user dashboard statistics efficiently with minimal DB queries
     * @return array
     */
    public function getUserStats(): array
    {
        // Get total count and all failed login counts in one query
        $users = UserQuery::create()
            ->select(['failedLogins', 'twoFactorAuthSecret'])
            ->find();
        
        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
        $totalUsers = $users->count();
        $activeUsers = 0;
        $lockedUsers = 0;
        $usersWithTwoFactor = 0;
        
        // Process results in memory to avoid multiple DB queries
        foreach ($users as $user) {
            if ($user['failedLogins'] >= $maxFailedLogins) {
                $lockedUsers++;
            } else {
                $activeUsers++;
            }
            
            if (!empty($user['twoFactorAuthSecret'])) {
                $usersWithTwoFactor++;
            }
        }

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'locked' => $lockedUsers,
            'twoFactor' => $usersWithTwoFactor
        ];
    }

    /**
     * Get user by ID
     * @param int $userId
     * @return User|null
     */
    public function getUserById(int $userId)
    {
        return UserQuery::create()->findOneById($userId);
    }

    /**
     * Check if a user is locked due to failed login attempts
     * @param User $user
     * @return bool
     */
    public function isUserLocked(User $user): bool
    {
        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
        return $maxFailedLogins > 0 && $user->getFailedLogins() >= $maxFailedLogins;
    }

    /**
     * Get users with failed login attempts
     * @return User[]|ObjectCollection
     */
    public function getLockedUsers()
    {
        $maxFailedLogins = SystemConfig::getIntValue('iMaxFailedLogins');
        return UserQuery::create()
            ->filterByFailedLogins(['min' => $maxFailedLogins])
            ->find();
    }

    /**
     * Get users with two-factor authentication enabled
     * @return User[]|ObjectCollection
     */
    public function getUsersWithTwoFactor()
    {
        return UserQuery::create()
            ->where('User.TwoFactorAuthSecret IS NOT NULL')
            ->find();
    }

    /**
     * Create a brand-new Person record together with a login account in one step —
     * for staff who aren't already in the People database. Unlike UserEditor.php's
     * "attach a login to an existing Person" flow, this creates both together.
     *
     * The Person is created unaffiliated (FamId = 0, a fully supported "Unassigned"
     * state — see PersonEditor.php's own "Unassigned" family option) since a quickly
     * added staff account doesn't need a household grouping.
     *
     * No email is sent (SMTP isn't assumed to be configured) — the generated
     * temporary password is returned in plaintext for the admin to hand over directly,
     * the same way User::resetPasswordToRandom() already does elsewhere in the app.
     *
     * @return array{personId: int, userId: int, userName: string, password: string}
     */
    public function createUser(string $firstName, string $lastName, ?string $email, ?string $cellPhone): array
    {
        $firstName = InputUtils::sanitizeAndEscapeText($firstName);
        $lastName = InputUtils::sanitizeAndEscapeText($lastName);
        $email = $email !== null ? InputUtils::sanitizeAndEscapeText($email) : '';
        $cellPhone = $cellPhone !== null ? InputUtils::sanitizeAndEscapeText($cellPhone) : '';

        if (strlen($firstName) < 2 || strlen($lastName) < 2) {
            throw new \InvalidArgumentException(gettext('First and last name must be at least 2 characters'));
        }

        $userName = $this->generateUniqueUserName($email !== '' ? $email : $firstName . $lastName);
        $enteredById = AuthenticationManager::getCurrentUser()->getId();

        $con = Propel::getWriteConnection(UserTableMap::DATABASE_NAME);
        $con->beginTransaction();
        try {
            $person = new Person();
            $person->setFirstName($firstName);
            $person->setLastName($lastName);
            $person->setEmail($email);
            $person->setCellPhone($cellPhone);
            $person->setGender(0);
            $person->setFamId(0);
            $person->setDateEntered(new DateTime());
            $person->setEnteredBy($enteredById);
            $person->save($con);

            $personCustom = new PersonCustom();
            $personCustom->setPerId($person->getId());
            $personCustom->save($con);

            $rawPassword = User::randomPassword();
            $user = new User();
            $user->setPersonId($person->getId());
            $user->setUserName($userName);
            $user->setEditSelf(1);
            $user->setNeedPasswordChange(true);
            $user->updatePassword($rawPassword);
            $user->save($con);

            $con->commit();
        } catch (\Throwable $e) {
            $con->rollBack();
            throw $e;
        }

        return [
            'personId' => $person->getId(),
            'userId'   => $user->getPersonId(),
            'userName' => $userName,
            'password' => $rawPassword,
        ];
    }

    /**
     * Sanitize a candidate login name and, if it's already taken, append an
     * incrementing number until it's unique (avoids a dead-end error for the
     * common case of two people sharing a name).
     */
    private function generateUniqueUserName(string $candidate): string
    {
        $base = preg_replace('/[^a-zA-Z0-9._-]/', '', $candidate);
        if ($base === '' || $base === null) {
            $base = 'user';
        }

        $userName = $base;
        $suffix = 1;
        while (UserQuery::create()->filterByUserName($userName)->count() > 0) {
            $suffix++;
            $userName = $base . $suffix;
        }

        return $userName;
    }

    /**
     * Get user settings configuration from SystemConfig
     * @return array Array of setting configurations
     */
    public function getUserSettingsConfig(): array
    {
        // Define user-related settings that should appear in the admin panel
        $userSettings = [
            'iSessionTimeout',
            'iMaxFailedLogins',
            'bEnableLostPassword',
            'bSendUserDeletedEmail',
            'iMinPasswordLength',
            'iMinPasswordChange',
            'aDisallowedPasswords',
            'bRequire2FA',
            's2FAApplicationName'
        ];

        return SystemConfig::getSettingsConfig($userSettings);
    }
}