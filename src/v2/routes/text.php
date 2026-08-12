<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemConfig;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\PluginManager;
use ChurchCRM\Service\SmsAutomationService;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\view\PageHeader;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;
use Slim\Views\PhpRenderer;

$app->group('/text', function (RouteCollectorProxy $group): void {
    $group->get('/dashboard', 'getTextDashboardMVC');
    $group->get('', 'getTextDashboardMVC');
    $group->get('/', 'getTextDashboardMVC');

    /**
     * @OA\Post(
     *     path="/text/scheduled/run",
     *     summary="Run today's birthday/anniversary SMS jobs immediately (Admin only)",
     *     tags={"Text"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Response(response=200, description="Job summary"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/scheduled/run', function (Request $request, Response $response): Response {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            return SlimUtils::renderErrorJSON($response, gettext('Admin role required'), [], 403);
        }

        $result = (new SmsAutomationService())->runDailyJobs(force: true);

        return SlimUtils::renderJSON($response, $result);
    });

    $group->get('/announcements', 'getAnnouncementsMVC');

    /**
     * @OA\Get(
     *     path="/text/announcements/recipient-count",
     *     summary="Count how many people would receive an announcement (Admin only)",
     *     tags={"Text"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\Parameter(name="type", in="query", required=true, @OA\Schema(type="string", enum={"congregation","group"})),
     *     @OA\Parameter(name="groupId", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Recipient count")
     * )
     */
    $group->get('/announcements/recipient-count', function (Request $request, Response $response): Response {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            return SlimUtils::renderErrorJSON($response, gettext('Admin role required'), [], 403);
        }

        $params = $request->getQueryParams();
        $service = new SmsAutomationService();
        $phones = ($params['type'] ?? '') === 'group'
            ? $service->getGroupPhones((int) ($params['groupId'] ?? 0))
            : $service->getCongregationPhones();

        return SlimUtils::renderJSON($response, ['count' => count($phones)]);
    });

    /**
     * @OA\Post(
     *     path="/text/announcements/send",
     *     summary="Send a one-off SMS announcement to the congregation or a group (Admin only)",
     *     tags={"Text"},
     *     security={{"ApiKeyAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="recipientType", type="string", enum={"congregation","group"}),
     *             @OA\Property(property="groupId", type="integer"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Send summary"),
     *     @OA\Response(response=400, description="Invalid request"),
     *     @OA\Response(response=403, description="Admin role required")
     * )
     */
    $group->post('/announcements/send', function (Request $request, Response $response): Response {
        if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
            return SlimUtils::renderErrorJSON($response, gettext('Admin role required'), [], 403);
        }

        $body = (array) ($request->getParsedBody() ?? []);
        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            return SlimUtils::renderErrorJSON($response, gettext('Message cannot be empty'), [], 400);
        }

        $service = new SmsAutomationService();
        $phones = ($body['recipientType'] ?? '') === 'group'
            ? $service->getGroupPhones((int) ($body['groupId'] ?? 0))
            : $service->getCongregationPhones();

        if (empty($phones)) {
            return SlimUtils::renderErrorJSON($response, gettext('No recipients with a phone number on file'), [], 400);
        }

        return SlimUtils::renderJSON($response, $service->broadcast($phones, $message));
    });
});

function getTextDashboardMVC(Request $request, Response $response, array $args): Response
{
    $renderer = new PhpRenderer('templates/text/');

    $vonagePlugin = PluginManager::getPlugin('vonage');
    $vonageConfigured = $vonagePlugin !== null && $vonagePlugin->isConfigured();

    $isAdmin = AuthenticationManager::getCurrentUser()->isAdmin();
    $smsAutomation = null;
    if ($isAdmin) {
        $token = (new SmsAutomationService())->getOrCreateScheduledMessagesToken();
        $baseUrl = $request->getUri()->getScheme() . '://' . $request->getUri()->getHost();
        $smsAutomation = [
            'lastBirthdayRun'    => SystemConfig::getValue('dLastBirthdaySmsRun'),
            'lastAnniversaryRun' => SystemConfig::getValue('dLastAnniversarySmsRun'),
            'cronUrl'            => $baseUrl . SystemURLs::getRootPath() . '/external/scheduled-messages/run?token=' . $token,
        ];
    }

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Text Dashboard'),
        'sPageSubtitle' => gettext('Manage SMS/text messaging tools and settings'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Communication')],
            [gettext('Text')],
        ]),
        'sSettingsCollapseId' => 'textSettings',
        'sPageHeaderButtons' => PageHeader::buttons([
            ['label' => gettext('Announcements'), 'url' => '/v2/text/announcements', 'icon' => 'fa-bullhorn', 'adminOnly' => true],
            ['label' => gettext('Text Settings'), 'collapse' => '#textSettings', 'icon' => 'fa-sliders', 'adminOnly' => true],
        ]),
        'vonageConfigured' => $vonageConfigured,
        'smsAutomation' => $smsAutomation,
    ];

    return $renderer->render($response, 'dashboard.php', $pageArgs);
}

function getAnnouncementsMVC(Request $request, Response $response, array $args): Response
{
    if (!AuthenticationManager::getCurrentUser()->isAdmin()) {
        return $response->withHeader('Location', SystemURLs::getRootPath() . '/v2/text/dashboard')->withStatus(302);
    }

    $renderer = new PhpRenderer('templates/text/');

    $pageArgs = [
        'sRootPath'  => SystemURLs::getRootPath(),
        'sPageTitle' => gettext('Send Announcement'),
        'sPageSubtitle' => gettext('Text the whole congregation or a single group'),
        'aBreadcrumbs' => PageHeader::breadcrumbs([
            [gettext('Communication')],
            [gettext('Text'), '/v2/text/dashboard'],
            [gettext('Announcements')],
        ]),
    ];

    return $renderer->render($response, 'announcements.php', $pageArgs);
}
