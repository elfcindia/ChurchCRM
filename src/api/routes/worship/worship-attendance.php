<?php

declare(strict_types=1);

use ChurchCRM\Service\WorshipAttendanceService;
use ChurchCRM\Slim\Middleware\Request\Auth\AddRecordsRoleAuthMiddleware;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\InputUtils;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

$app->group('/worship/attendance', function (RouteCollectorProxy $group): void {
    $group->get('/summary', 'worshipAttendanceSummary');
    $group->get('/search', 'worshipAttendanceSearch');
    $group->get('/attended', 'worshipAttendanceAttendedList');
    $group->get('/absent', 'worshipAttendanceAbsentList');
    $group->get('/export-absentees.csv', 'worshipAttendanceExportAbsentees');
    $group->get('/export-absentees.pdf', 'worshipAttendanceExportAbsenteesPdf');
    $group->post('/checkin', 'worshipAttendanceCheckin');
    $group->delete('/checkin/{personId:[0-9]+}', 'worshipAttendanceRemoveCheckin');
    $group->post('/quick-person', 'worshipAttendanceQuickPerson');
})->add(new AddRecordsRoleAuthMiddleware());

function worshipAttendanceSummary(Request $request, Response $response, array $args): Response
{
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);
    $summary = WorshipAttendanceService::getSummary($date);

    return SlimUtils::renderJSON($response, $summary);
}

function worshipAttendanceSearch(Request $request, Response $response, array $args): Response
{
    // Plain text for DB LIKE — do not htmlspecialchars (breaks names with quotes and is wrong for SQL).
    $q = InputUtils::sanitizeText((string) ($request->getQueryParams()['q'] ?? ''));
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);
    $results = WorshipAttendanceService::searchGreeter($q, $date);

    return SlimUtils::renderJSON($response, ['results' => $results]);
}

function worshipAttendanceAttendedList(Request $request, Response $response, array $args): Response
{
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);

    return SlimUtils::renderJSON($response, [
        'date'    => $date,
        'attended'=> WorshipAttendanceService::listAttendedDetails($date),
    ]);
}

function worshipAttendanceAbsentList(Request $request, Response $response, array $args): Response
{
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);

    return SlimUtils::renderJSON($response, [
        'date'   => $date,
        'absent' => WorshipAttendanceService::listAbsentExpectedDetails($date),
    ]);
}

function worshipAttendanceExportAbsentees(Request $request, Response $response, array $args): Response
{
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);
    $csv = "\xEF\xBB\xBF" . WorshipAttendanceService::buildAbsenteesCsv($date);
    $filename = 'worship-absentees-' . $date . '.csv';
    $response->getBody()->write($csv);

    return $response
        ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
        ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
}

function worshipAttendanceExportAbsenteesPdf(Request $request, Response $response, array $args): Response
{
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);
    $pdf = WorshipAttendanceService::buildAbsenteesPdf($date);
    $filename = 'worship-absentees-' . $date . '.pdf';
    $response->getBody()->write($pdf);

    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
}

function worshipAttendanceCheckin(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody() ?? [];
    $personId = (int) ($body['personId'] ?? 0);
    if ($personId < 1) {
        return SlimUtils::renderErrorJSON($response, gettext('personId is required'), [], 400);
    }
    $date = isset($body['date']) ? (string) $body['date'] : null;
    try {
        $row = WorshipAttendanceService::recordCheckIn($personId, $date);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 404);
    }

    return SlimUtils::renderJSON($response, [
        'success'      => true,
        'personId'     => $row->getPersonId(),
        'checkinTime'  => $row->getCheckinDatetime()?->format('Y-m-d H:i:s'),
    ]);
}

function worshipAttendanceRemoveCheckin(Request $request, Response $response, array $args): Response
{
    $personId = (int) $args['personId'];
    $date = WorshipAttendanceService::normalizeDate($request->getQueryParams()['date'] ?? null);
    $ok = WorshipAttendanceService::removeCheckIn($personId, $date);

    return SlimUtils::renderJSON($response, ['success' => $ok]);
}

function worshipAttendanceQuickPerson(Request $request, Response $response, array $args): Response
{
    $body = $request->getParsedBody() ?? [];
    $first = (string) ($body['firstName'] ?? '');
    $last = (string) ($body['lastName'] ?? '');
    $cell = isset($body['cellPhone']) ? (string) $body['cellPhone'] : null;
    $fam = isset($body['familyName']) ? (string) $body['familyName'] : null;
    $date = isset($body['date']) ? (string) $body['date'] : null;

    try {
        $ids = WorshipAttendanceService::createQuickVisitorAndCheckIn($first, $last, $cell, $fam, $date);
    } catch (\InvalidArgumentException $e) {
        return SlimUtils::renderErrorJSON($response, $e->getMessage(), [], 400);
    } catch (\Throwable $e) {
        return SlimUtils::renderErrorJSON($response, gettext('Could not create person'), [], 500, $e);
    }

    return SlimUtils::renderJSON($response, [
        'success'  => true,
        'personId' => $ids['personId'],
        'familyId' => $ids['familyId'],
    ]);
}
