<?php

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\Service\SmsAutomationService;
use ChurchCRM\Slim\SlimUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Cron-triggered endpoint for the daily birthday/anniversary SMS jobs.
 *
 * Not session-based — a server cron job calls this directly (e.g. via curl),
 * so it's authorized with a bearer token instead. The token is generated on
 * the admin's Text dashboard (see v2/routes/text.php) and must be present
 * before this endpoint will run anything.
 */
$app->group('/scheduled-messages', function (RouteCollectorProxy $group): void {
    /**
     * @OA\Post(
     *     path="/external/scheduled-messages/run",
     *     summary="Run today's birthday/anniversary SMS jobs (cron-triggered, token-authorized)",
     *     tags={"External"},
     *     @OA\Response(response=200, description="Job summary"),
     *     @OA\Response(response=403, description="Missing or invalid token")
     * )
     */
    $group->post('/run', function (Request $request, Response $response): Response {
        $expected = SystemConfig::getValue('sScheduledMessagesToken');
        $provided = $request->getQueryParams()['token'] ?? '';
        if (empty($provided) && $request->hasHeader('Authorization')) {
            $provided = str_ireplace('Bearer ', '', $request->getHeaderLine('Authorization'));
        }

        if (empty($expected) || !hash_equals($expected, (string) $provided)) {
            return SlimUtils::renderErrorJSON($response, 'Invalid or missing token', [], 403);
        }

        $result = (new SmsAutomationService())->runDailyJobs();

        return SlimUtils::renderJSON($response, $result);
    });
});
