<?php

namespace ChurchCRM\Config\Menu;

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\Group;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Map\GroupTableMap;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugin\PluginManager;

class Menu
{
    /**
     * @var array<string, MenuItem>|null
     */
    private static ?array $menuItems = null;

    public static function init(): void
    {
        self::$menuItems = self::buildMenuItems();
    }

    public static function getMenu(): ?array
    {
        return self::$menuItems;
    }

    private static function buildMenuItems(): array
    {
        $currentUser = AuthenticationManager::getCurrentUser();
        $isAdmin = $currentUser->isAdmin();
        $isMenuOptions = $currentUser->isMenuOptionsEnabled();
        $menus = [
            'Dashboard'    => new MenuItem(gettext('Dashboard'), 'v2/dashboard', true, 'fa-gauge'),
            'Calendar'     => self::getCalendarMenu(),
            'People'       => self::getPeopleMenu($isAdmin, $isMenuOptions, $currentUser->isAddRecordsEnabled()),
        ];
        if ($currentUser->isAddRecordsEnabled()) {
            $menus['AttendanceManagement'] = self::getAttendanceManagementMenu();
        }
        $menus = array_merge($menus, [
            'SundaySchool'  => self::getSundaySchoolMenu(),
            'Communication' => self::getCommunicationMenu($isAdmin),
            'Events'        => self::getEventsMenu(isAddEventEnabled: $currentUser->isAddEventEnabled()),
            'Fundraiser'    => self::getFundraisersMenu(),
            'Reports'       => self::getReportsMenu(),
        ]);
        
        // Backward compatibility: plugins that declare parent 'Email' still attach to Communication
        if (isset($menus['Communication'])) {
            $menus['Email'] = $menus['Communication'];
        }

        // Add plugin menu items to their parent menus
        self::addPluginMenuItems($menus);

        // Remove the backward-compat alias so it doesn't appear as a duplicate menu
        unset($menus['Email']);
        
        // Allow plugins to add top-level menus via the MENU_BUILDING hook
        $menus = HookManager::applyFilters(Hooks::MENU_BUILDING, $menus);
        
        // Admin menu is always last (at bottom of nav)
        if ($isAdmin) {
            $menus['Admin'] = self::getAdminMenu($isAdmin);
        }
        
        return $menus;

    }

    private static function getCalendarMenu(): MenuItem
    {
        $calendarMenu = new MenuItem(gettext('Calendar'), 'event/calendars', true, 'fa-calendar');
        // Anniversaries calendar (ID 1) - black background
        $calendarMenu->addCounter(new MenuCounter('AnniversaryNumber', 'bg-dark', 0, gettext("Today's Wedding Anniversaries")));
        // Birthdays calendar (ID 0) - blue background  
        $calendarMenu->addCounter(new MenuCounter('BirthdateNumber', 'bg-primary', 0, gettext("Today's Birthdays")));
        // Events happening today - yellow/warning background
        $calendarMenu->addCounter(new MenuCounter('EventsNumber', 'bg-warning', 0, gettext('Events Today')));

        return $calendarMenu;
    }

    private static function getPeopleMenu(bool $isAdmin, bool $isMenuOptions, bool $isAddRecordsEnabled): MenuItem
    {
        $peopleMenu = new MenuItem(gettext('People'), '', true, 'fa-people-group');
        $peopleMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'people/dashboard', true, 'fa-gauge'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Person'), 'PersonEditor.php', $isAddRecordsEnabled, 'fa-user-plus'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Person Listing'), 'v2/people', true, 'fa-person-half-dress'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Photo Directory'), 'v2/people/photos', true, 'fa-images'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Add New') . ' ' . gettext('Family'), 'FamilyEditor.php', $isAddRecordsEnabled, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Listing'), 'v2/family', true, 'fa-people-roof'));
        $peopleMenu->addSubMenu(new MenuItem(gettext('Family Map'), 'v2/map', true, 'fa-map'));

        if ($isAdmin || $isMenuOptions) {
            $adminMenu = new MenuItem(gettext('Admin'), '', true);
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Roles'), 'OptionManager.php?mode=famroles', $isMenuOptions, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Properties'), 'PropertyList.php?Type=f', $isMenuOptions, 'fa-people-roof'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Family Custom Fields'), 'FamilyCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Classifications'), 'OptionManager.php?mode=classes', $isMenuOptions, 'fa-tags'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Properties'), 'PropertyList.php?Type=p', $isMenuOptions, 'fa-person-half-dress'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Person Custom Fields'), 'PersonCustomFieldsEditor.php', $isAdmin, 'fa-sliders'));
            $adminMenu->addSubMenu(new MenuItem(gettext('Volunteer Opportunities'), 'VolunteerOpportunityEditor.php', $isAdmin, 'fa-handshake-angle'));
    
            $peopleMenu->addSubMenu($adminMenu);
        }

        return $peopleMenu;
    }

    private static function getAttendanceManagementMenu(): MenuItem
    {
        $menu = new MenuItem(gettext('Attendance Management'), '', true, 'fa-clipboard-user');
        $menu->addSubMenu(new MenuItem(gettext('Greeter check-in'), 'v2/worship-attendance/greeter', true, 'fa-user-check'));
        $menu->addSubMenu(new MenuItem(gettext('Attendance dashboard'), 'v2/worship-attendance/dashboard', true, 'fa-clipboard-list'));

        return $menu;
    }

    private static function getSundaySchoolMenu(): MenuItem
    {
        $sundaySchoolMenu = new MenuItem(gettext('Ministries'), '', SystemConfig::getBooleanValue('bEnabledSundaySchool'), 'img:Images/church-solid.png');
        $sundaySchoolMenu->addSubMenu(new MenuItem(gettext('Sunday School Ministry'), 'groups/sundayschool/dashboard', true, 'fa-gauge'));

        foreach (self::ministryGroupNamesForMenu() as $ministryName) {
            self::addMinistryGroupSubMenu($sundaySchoolMenu, gettext($ministryName), $ministryName);
        }

        $classes = GroupQuery::create()->filterByType(4)->orderByName()->select(['Id', 'Name'])->find()->toArray();
        $allowed = [];
        foreach ($classes as $group) {
            $name = trim((string) $group['Name']);
            $norm = mb_strtolower($name);
            if (in_array($norm, [
                'angels class',
                'class 1-3',
                'class 4-5',
                'class 6-7',
                'kiosk manager',
            ], true)) {
                continue;
            }
            if ($norm === 'sunday school ministry' || $norm === 'youth meeting') {
                $allowed[] = $group;
            }
        }
        foreach ($allowed as $group) {
            $label = mb_strtolower(trim((string) $group['Name'])) === 'youth meeting'
                ? gettext('Youth Ministry')
                : $group['Name'];
            $sundaySchoolMenu->addSubMenu(new MenuItem($label, 'groups/sundayschool/class/' . $group['Id'], true, 'fa-chalkboard'));
        }

        return $sundaySchoolMenu;
    }

    /**
     * Ministry submenu entries: group name in DB must match (case-insensitive) for a link to appear.
     * Type 4 (Sunday School) groups use the class roster view; other types use standard group view.
     *
     * @return list<string>
     */
    private static function ministryGroupNamesForMenu(): array
    {
        return [
            "Women's Fellowship",
            "Men's Fellowship",
            'Media Ministry',
            'Worship Ministry',
            'Prayer Ministry',
            'Prayer Groups',
        ];
    }

    private static function findGroupByNameCaseInsensitive(string $name): ?Group
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        return GroupQuery::create()
            ->where('LOWER(' . GroupTableMap::COL_GRP_NAME . ') = ?', $normalized, \PDO::PARAM_STR)
            ->findOne();
    }

    private static function addMinistryGroupSubMenu(MenuItem $menu, string $label, string $groupNameInDatabase): void
    {
        $group = self::findGroupByNameCaseInsensitive($groupNameInDatabase);
        if ($group === null) {
            return;
        }

        $uri = $group->getType() === 4
            ? 'groups/sundayschool/class/' . $group->getId()
            : 'groups/view/' . $group->getId();

        $menu->addSubMenu(new MenuItem($label, $uri, true, 'fa-chalkboard'));
    }

    private static function getCommunicationMenu(bool $isAdmin): MenuItem
    {
        $commMenu = new MenuItem(gettext('Communication'), '', true, 'fa-comments');
        $commMenu->addSubMenu(new MenuItem(gettext('Text'), 'v2/text/dashboard', true, 'fa-comment-sms'));
        $commMenu->addSubMenu(new MenuItem(gettext('Announcements'), 'v2/text/announcements', $isAdmin, 'fa-bullhorn'));

        return $commMenu;
    }

    /**
     * Add plugin menu items to their parent menus.
     *
     * Plugins can register menu items via getMenuItems() which specify a 'parent' key.
     * This method merges those items into the appropriate parent menu.
     *
     * @param array<string, MenuItem> $menus The main menu array to modify
     */
    private static function addPluginMenuItems(array &$menus): void
    {
        try {
            $pluginMenuItems = PluginManager::getPluginMenuItems();
            
            foreach ($pluginMenuItems as $parentKey => $items) {
                // Find the parent menu (case-insensitive match)
                $parentMenu = null;
                foreach ($menus as $menuKey => $menu) {
                    if (strtolower($menuKey) === $parentKey) {
                        $parentMenu = $menu;
                        break;
                    }
                }
                
                if ($parentMenu === null) {
                    // Parent menu not found, skip these items
                    continue;
                }
                
                // Add each plugin menu item as a submenu
                foreach ($items as $item) {
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? '';
                    $icon = $item['icon'] ?? 'fa-plug';
                    
                    if (!empty($label) && !empty($url)) {
                        $parentMenu->addSubMenu(new MenuItem($label, $url, true, $icon));
                    }
                }
            }
        } catch (\Throwable $e) {
            // Don't let plugin errors break the menu
            // Silently fail - plugins may not be initialized yet
        }
    }

    private static function getEventsMenu(bool $isAddEventEnabled): MenuItem
    {
        $eventsMenu = new MenuItem(gettext('Events'), '', SystemConfig::getBooleanValue('bEnabledEvents'), 'fa-ticket');
        $eventsMenu->addSubMenu(new MenuItem(gettext('Events Dashboard'), 'event/dashboard', true, 'fa-gauge'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Add Church Event'), 'event/editor', $isAddEventEnabled, 'fa-circle-plus'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('Check-in and Check-out'), 'event/checkin', true, 'fa-user-check'));
        $eventsMenu->addSubMenu(new MenuItem(gettext('List Event Types'), 'event/types', $isAddEventEnabled, 'fa-tags'));

        return $eventsMenu;
    }

    private static function getFundraisersMenu(): MenuItem
    {
        $fundraiserMenu = new MenuItem(gettext('Fundraiser'), '', SystemConfig::getBooleanValue('bEnabledFundraiser'), 'fa-money-bill-1');
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Dashboard'), 'FindFundRaiser.php', true, 'fa-list'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Create New Fundraiser'), 'FundRaiserEditor.php?FundRaiserID=-1', true, 'fa-circle-plus'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('Add Donors to Buyer List'), 'AddDonors.php', true, 'fa-user-plus'));
        $fundraiserMenu->addSubMenu(new MenuItem(gettext('View Buyers'), 'PaddleNumList.php', true, 'fa-users'));
        $iCurrentFundraiser = 0;
        if (array_key_exists('iCurrentFundraiser', $_SESSION)) {
            $iCurrentFundraiser = $_SESSION['iCurrentFundraiser'];
        }
        $fundraiserMenu->addCounter(new MenuCounter('iCurrentFundraiser', 'bg-blue', $iCurrentFundraiser));

        return $fundraiserMenu;
    }

    private static function getReportsMenu(): MenuItem
    {
        $reportsMenu = new MenuItem(gettext('Data/Reports'), '', true, 'fa-database');
        $reportsMenu->addSubMenu(new MenuItem(gettext('Query Menu'), 'QueryList.php', true, 'fa-magnifying-glass'));

        return $reportsMenu;
    }

    private static function getAdminMenu(bool $isAdmin): MenuItem
    {
        $menu = new MenuItem(gettext('Admin'), '', true, 'fa-screwdriver-wrench');
        $menu->addSubMenu(new MenuItem(gettext('Admin Dashboard'), 'admin/', $isAdmin, 'fa-gauge'));
        $menu->addSubMenu(new MenuItem(gettext('Church Information'), 'admin/system/church-info', $isAdmin, 'fa-church'));
        $menu->addSubMenu(new MenuItem(gettext('Get Started'), 'admin/get-started', $isAdmin, 'fa-rocket'));
        $menu->addSubMenu(new MenuItem(gettext('System Users'), 'admin/system/users', $isAdmin, 'fa-user-gear'));
        $menu->addSubMenu(new MenuItem(gettext('System Settings'), 'SystemSettings.php', $isAdmin, 'fa-gear'));
        $menu->addSubMenu(new MenuItem(gettext('Plugins'), 'plugins/management', $isAdmin, 'fa-plug'));
        $menu->addSubMenu(new MenuItem(gettext('Export'), 'admin/export', $isAdmin, 'fa-file-export'));

        return $menu;
    }
}
