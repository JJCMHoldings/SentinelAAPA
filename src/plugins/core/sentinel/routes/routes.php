<?php

use ChurchCRM\Authentication\AuthenticationManager;
use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugins\Sentinel\SentinelPlugin;
use ChurchCRM\Plugins\Sentinel\Service\Access;
use ChurchCRM\Plugins\Sentinel\Service\CrmOperations;
use ChurchCRM\Plugins\Sentinel\Service\Newsletter;
use ChurchCRM\Plugins\Sentinel\Service\Operations;
use ChurchCRM\Slim\SlimUtils;
use ChurchCRM\Utils\CSRFUtils;
use DrewM\MailChimp\MailChimp;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Views\PhpRenderer;

$plugin = SentinelPlugin::getInstance();
if ($plugin === null) {
    return;
}

$guard = static function (string $action) {
    return static function (Request $request, RequestHandlerInterface $handler) use ($action): Response {
        $response = new \Slim\Psr7\Response();
        if (!Access::allows(AuthenticationManager::getCurrentUser(), $action)) {
            return SlimUtils::renderJSON($response, ['error' => dgettext('sentinel', 'Permission required.')], 403);
        }
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)
            && !CSRFUtils::validateToken($request->getHeaderLine('X-CSRF-Token'))) {
            return SlimUtils::renderJSON($response, ['error' => dgettext('sentinel', 'Reload the page before saving.')], 403);
        }
        try {
            return $handler->handle($request);
        } catch (\InvalidArgumentException | \JsonException $e) {
            return SlimUtils::renderJSON($response, ['error' => $e->getMessage()], 422);
        } catch (\DomainException $e) {
            return SlimUtils::renderJSON($response, ['error' => $e->getMessage()], 409);
        }
    };
};

$app->get('/sentinel', static function (Request $request, Response $response) use ($plugin): Response {
    $user = AuthenticationManager::getCurrentUser();
    $permissions = [];
    foreach (['contacts', 'partner-read', 'partners', 'events', 'event-write', 'assignments', 'publish', 'consent', 'settings', 'email'] as $action) {
        $permissions[$action] = Access::allows($user, $action);
    }
    $permissions['add-contacts'] = $user->isAddRecordsEnabled();
    $permissions['teams'] = $user->isManageGroupsEnabled();
    $permissions['programs'] = $user->isVolunteerCoordinatorEnabled();
    return (new PhpRenderer($plugin->getBasePath() . '/views'))->render($response, 'operations.php', [
        'sPageTitle' => 'Sentinel', 'sRootPath' => SystemURLs::getRootPath(),
        'organization' => $plugin->setting('organizationName') ?: 'Abolish Abortion Pennsylvania',
        'permissions' => $permissions, 'csrf' => CSRFUtils::generateToken(),
        'newsletterUrl' => $plugin->setting('newsletterUrl'),
    ]);
})->add($guard('read'));

$app->get('/sentinel/api/contacts', static function (Request $request, Response $response) use ($plugin): Response {
    return SlimUtils::renderJSON($response, ['data' => $plugin->operations()->contacts()]);
})->add($guard('read'));
$app->get('/sentinel/api/bootstrap', static function (Request $request, Response $response) use ($plugin): Response {
    $user = AuthenticationManager::getCurrentUser();
    $operations = $plugin->operations();
    return SlimUtils::renderJSON($response, [
        'contacts' => $operations->contacts(),
        'partners' => Access::allows($user, 'partner-read') ? $operations->partners() : [],
        'publications' => Access::allows($user, 'events') ? $operations->publications() : [],
        'consents' => Access::allows($user, 'consent') ? $operations->consents() : [],
        'lookups' => [
            'contacts' => CrmOperations::lookup('contacts', ''),
            'teams' => CrmOperations::lookup('teams', ''),
            'events' => Access::allows($user, 'events') ? CrmOperations::lookup('events', '') : [],
        ],
    ])->withHeader('Cache-Control', 'no-store');
})->add($guard('read'));
$app->get('/sentinel/api/lookups/{type:contacts|teams|events}', static function (Request $request, Response $response, array $args): Response {
    $type = $args['type'];
    if ($type === 'events' && !Access::allows(AuthenticationManager::getCurrentUser(), 'events')) {
        return SlimUtils::renderJSON($response, ['error' => 'Permission required.'], 403);
    }
    $search = Operations::text($request->getQueryParams(), 'q', 100);
    return SlimUtils::renderJSON($response, ['data' => CrmOperations::lookup($type, $search)]);
})->add($guard('read'));
$app->post('/sentinel/api/contacts/{id:[0-9]+}', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    $id = Operations::id($args['id']);
    CrmOperations::requirePerson($id);
    return SlimUtils::renderJSON($response, $plugin->operations()->contact($id, (array) $request->getParsedBody(),
        (int) AuthenticationManager::getCurrentUser()->getId()));
})->add($guard('contacts'));

$app->get('/sentinel/api/partners', static function (Request $request, Response $response) use ($plugin): Response {
    return SlimUtils::renderJSON($response, ['data' => $plugin->operations()->partners()]);
})->add($guard('partner-read'));
$app->post('/sentinel/api/partners/{id:[a-f0-9]{32}}', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    return SlimUtils::renderJSON($response, $plugin->operations()->partner($args['id'], (array) $request->getParsedBody(),
        (int) AuthenticationManager::getCurrentUser()->getId(), CrmOperations::personExists(...), CrmOperations::eventExists(...)));
})->add($guard('partners'));

$app->get('/sentinel/api/publications', static function (Request $request, Response $response) use ($plugin): Response {
    return SlimUtils::renderJSON($response, ['data' => $plugin->operations()->publications()]);
})->add($guard('events'));
$app->post('/sentinel/api/events/{id:[0-9]+}/draft', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    $id = Operations::id($args['id']);
    CrmOperations::requireEvent($id);
    return SlimUtils::renderJSON($response, $plugin->operations()->draft($id, (array) $request->getParsedBody(),
        (int) AuthenticationManager::getCurrentUser()->getId()));
})->add($guard('event-write'));
$app->post('/sentinel/api/events/{id:[0-9]+}/{action:approve|withdraw}', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    $id = Operations::id($args['id']);
    CrmOperations::requireEvent($id);
    return SlimUtils::renderJSON($response, $plugin->operations()->publish($id,
        Operations::revision((array) $request->getParsedBody()), (int) AuthenticationManager::getCurrentUser()->getId(), $args['action'] === 'withdraw'));
})->add($guard('publish'));
$app->get('/sentinel/api/public-events', static function (Request $request, Response $response) use ($plugin): Response {
    $artwork = SystemURLs::getRootPath() . '/plugins/core/sentinel/assets/aapa-event.svg';
    return SlimUtils::renderJSON($response, ['events' => (new CrmOperations($plugin->operations()))->feed($artwork)])
        ->withHeader('Cache-Control', 'no-store');
})->add($guard('events'));

$app->get('/sentinel/api/events/{id:[0-9]+}/assignments', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    $id = Operations::id($args['id']);
    CrmOperations::requireEvent($id);
    return SlimUtils::renderJSON($response, ['data' => $plugin->operations()->assignments($id)]);
})->add($guard('events'));
$app->post('/sentinel/api/events/{id:[0-9]+}/assignments/{person:[0-9]+}', static function (Request $request, Response $response, array $args) use ($plugin): Response {
    $eventId = Operations::id($args['id']);
    $personId = Operations::id($args['person']);
    CrmOperations::requirePerson($personId);
    CrmOperations::requireEvent($eventId);
    return SlimUtils::renderJSON($response, $plugin->operations()->assign($eventId, $personId, (array) $request->getParsedBody(),
        (int) AuthenticationManager::getCurrentUser()->getId()));
})->add($guard('assignments'));

$app->get('/sentinel/api/consents', static function (Request $request, Response $response) use ($plugin): Response {
    return SlimUtils::renderJSON($response, ['data' => $plugin->operations()->consents()]);
})->add($guard('consent'));
$app->post('/sentinel/api/consents', static function (Request $request, Response $response) use ($plugin): Response {
    $input = (array) $request->getParsedBody();
    return SlimUtils::renderJSON($response, $plugin->operations()->consent(Operations::text($input, 'email', 254, true), $input,
        (int) AuthenticationManager::getCurrentUser()->getId()));
})->add($guard('consent'));
$app->post('/sentinel/api/email', static function (Request $request, Response $response) use ($plugin): Response {
    if (($request->getParsedBody()['scope'] ?? '') === 'event'
        && !Access::allows(AuthenticationManager::getCurrentUser(), 'events')) {
        return SlimUtils::renderJSON($response, ['error' => 'Permission required.'], 403);
    }
    return SlimUtils::renderJSON($response, (new CrmOperations($plugin->operations()))->email((array) $request->getParsedBody(),
        AuthenticationManager::getCurrentUser()));
})->add($guard('email'));
$app->post('/sentinel/api/mailchimp/sync', static function (Request $request, Response $response) use ($plugin): Response {
    $input = (array) $request->getParsedBody();
    $groupId = Operations::id($input['id'] ?? null);
    $mapping = Newsletter::mappings($plugin->setting('mailchimpMappings'));
    if (!isset($mapping[$groupId]) || $plugin->setting('mailchimpApiKey') === '') {
        throw new \InvalidArgumentException('Configure a Mailchimp key and a team to audience mapping in plugin settings.');
    }
    $client = new MailChimp($plugin->setting('mailchimpApiKey'));
    $transport = static function (string $method, string $path, array $data) use ($client): array {
        $result = $client->$method($path, $data);
        return ['ok' => $client->success(), 'data' => $result, 'code' => (int) ($client->getLastResponse()['headers']['http_code'] ?? 0)];
    };
    $contacts = (new CrmOperations($plugin->operations()))->recipients(['scope' => 'team', 'id' => $groupId]);
    return SlimUtils::renderJSON($response, (new Newsletter($plugin->operations(), $transport))->sync($contacts['recipients'], $mapping[$groupId]));
})->add($guard('sync'));
