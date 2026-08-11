<?php

declare(strict_types=1);

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Service\WorshipAttendanceService;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/worship-attendance', function (RouteCollectorProxy $group): void {
    $group->get('/greeter', 'viewWorshipGreeter');
    $group->get('/dashboard', 'viewWorshipAttendanceDashboard');
});

function viewWorshipGreeter(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/worship-attendance/');
    $today = WorshipAttendanceService::localTodayYmd();

    $pageArgs = [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Greeter check-in'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Attendance Management')],
            [gettext('Greeter check-in')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Attendance dashboard'), 'url' => '/v2/worship-attendance/dashboard', 'icon' => 'fa-chart-simple', 'adminOnly' => false],
        ]),
        'defaultDate'    => $today,
    ];

    return $renderer->render($response, 'greeter.php', $pageArgs);
}

function viewWorshipAttendanceDashboard(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/worship-attendance/');
    $today = WorshipAttendanceService::localTodayYmd();

    $pageArgs = [
        'sRootPath'      => SystemURLs::getRootPath(),
        'sPageTitle'     => gettext('Attendance dashboard'),
        'aBreadcrumbs'   => PageHeader::breadcrumbs([
            [gettext('Attendance Management')],
            [gettext('Attendance dashboard')],
        ]),
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Greeter check-in'), 'url' => '/v2/worship-attendance/greeter', 'icon' => 'fa-user-check', 'adminOnly' => false],
        ]),
        'defaultDate'    => $today,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}
