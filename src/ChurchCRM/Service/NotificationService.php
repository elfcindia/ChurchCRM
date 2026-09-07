<?php

namespace ChurchCRM\Service;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\Notification\UiNotification;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Utils\LoggerUtils;

/**
 * Generic notification registry.
 *
 * On login, AuthenticationManager sets session state:
 *  - $_SESSION['systemUpdateAvailable'] (system upgrade check)
 *  - $_SESSION['SystemNotifications']   (remote GitHub JSON fetch)
 *
 * On every page load, Header.php calls loadSessionNotifications() which
 * hydrates the in-memory registry from both session sources.
 * No external HTTP calls happen in the render path.
 */
class NotificationService
{
    /** @var UiNotification[] */
    private static array $notifications = [];

    /**
     * Push a notification into the registry.
     * Automatically skips if the current user has dismissed it.
     */
    public static function add(UiNotification $notification): void
    {
        $currentUser = AuthenticationManager::getCurrentUser();

        $dismissKey = $notification->getDismissSettingKey();
        if ($dismissKey !== '' && $currentUser->getSettingValue($dismissKey) === 'true') {
            return;
        }

        self::$notifications[] = $notification;
    }

    /**
     * Return all active (non-dismissed) notifications.
     *
     * @return UiNotification[]
     */
    public static function getNotifications(): array
    {
        return self::$notifications;
    }

    public static function hasActiveNotifications(): bool
    {
        return count(self::$notifications) > 0;
    }

    /**
     * Hydrate the registry from all session-cached notification sources.
     * Called on every page load from Header.php — reads session only, no HTTP.
     */
    public static function loadSessionNotifications(): void
    {
        self::loadRemoteNotifications();
    }

    // ─── Source: Remote GitHub JSON (session set at login) ──────────────
    //
    // Note: this class used to also surface a site-wide "System Update
    // Available" banner (sourced from $_SESSION['systemUpdateAvailable'],
    // set by AuthenticationManager's version check at login). That banner
    // was removed at the church's request - admins can still check for
    // updates via the "New Release" icon in the top nav (Header.php) or
    // Admin > System > Upgrade.

    /**
     * Fetch notifications from the remote GitHub-hosted JSON file.
     * Called ONCE on login by AuthenticationManager — never in the render path.
     * Results are stored in $_SESSION['SystemNotifications'].
     */
    public static function fetchRemoteNotifications(): void
    {
        try {
            $url = SystemConfig::getValue('sNotificationsURL');
            $contents = file_get_contents($url);
            if ($contents === false) {
                LoggerUtils::getAppLogger()->warning('Failed to fetch remote notifications', ['url' => $url]);

                return;
            }
            $data = json_decode($contents, null, 512, JSON_THROW_ON_ERROR);
            if (isset($data->TTL)) {
                $_SESSION['SystemNotifications'] = $data;
            }
        } catch (\Exception $e) {
            LoggerUtils::getAppLogger()->warning('Error processing remote notifications', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Walk cached remote messages and push version/role-matching ones into the registry.
     */
    private static function loadRemoteNotifications(): void
    {
        if (!isset($_SESSION['SystemNotifications']->messages)) {
            return;
        }

        $currentUser = AuthenticationManager::getCurrentUser();
        $installedVersion = $_SESSION['sSoftwareInstalledVersion'] ?? '';

        foreach ($_SESSION['SystemNotifications']->messages as $message) {
            $pattern = $message->targetVersionPattern ?? $message->targetVersion ?? '';
            if ($pattern !== '' && !self::matchesVersionPattern($pattern, $installedVersion)) {
                continue;
            }
            if (!empty($message->adminOnly) && !$currentUser->isAdmin()) {
                continue;
            }

            $id = $message->id ?? '';
            $dismissKey = $id !== '' ? 'notification.dismissed.' . $id : '';

            self::add(new UiNotification(
                $id,
                $dismissKey,
                $message->title ?? '',
                $message->message ?? '',
                $message->link ?? '',
                $message->type ?? 'info',
                $message->icon ?? 'info-circle',
            ));
        }
    }

    /**
     * Check if a version pattern matches the installed version.
     * Supports wildcards: "7.1.*" matches 7.1.x, "7.*" matches 7.x.y, "*" matches all.
     */
    private static function matchesVersionPattern(string $pattern, string $version): bool
    {
        if ($pattern === '*') {
            return true;
        }
        $regex = '/^' . str_replace(['.', '*'], ['\.', '\d+'], $pattern) . '$/';

        return (bool) preg_match($regex, $version);
    }
}
