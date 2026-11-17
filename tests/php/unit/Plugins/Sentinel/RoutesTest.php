<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use ChurchCRM\Authentication\AuthenticationProviders\IAuthenticationProvider;
use ChurchCRM\Authentication\AuthenticationResult;
use ChurchCRM\Authentication\Requests\AuthenticationRequest;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PortalMemberUser;
use ChurchCRM\Plugins\Sentinel\SentinelPlugin;
use ChurchCRM\Utils\CSRFUtils;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';
$loader = new ClassLoader();
$loader->addPsr4('ChurchCRM\\Plugins\\Sentinel\\', APP_ROOT . '/plugins/core/sentinel/src');
$loader->register();
$user = new class extends User {
    public bool $admin = false;
    public bool $selfOnly = false;
    public bool $editor = false;
    public bool $email = false;
    public function isAdmin(): bool { return $this->admin; }
    public function isEditRecordsEnabled(): bool { return $this->admin || $this->editor; }
    public function isNotesEnabled(): bool { return $this->admin || $this->editor; }
    public function canManageEvents(): bool { return $this->admin || $this->editor; }
    public function canViewEvents(): bool { return $this->admin || $this->editor; }
    public function isEmailEnabled(): bool { return $this->admin || $this->email; }
};
$provider = new class($user) implements IAuthenticationProvider {
    public function __construct(private User $user) {}
    public function getCurrentUser(): ?User { return $this->user->selfOnly ? PortalMemberUser::forPerson(1) : $this->user; }
    public function authenticate(AuthenticationRequest $AuthenticationRequest): AuthenticationResult { throw new LogicException(); }
    public function validateUserSessionIsActive(bool $updateLastOperationTimestamp): AuthenticationResult { throw new LogicException(); }
    public function endSession(): void {}
    public function getPasswordChangeURL(): string { return ''; }
};
$token = CSRFUtils::generateToken();
$_SESSION['AuthenticationProvider'] = $provider;
new SentinelPlugin(APP_ROOT . '/plugins/core/sentinel');
$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
require APP_ROOT . '/plugins/core/sentinel/routes/routes.php';
$request = static function (string $method, string $path, bool $csrf = true) use ($app, $token) {
    $request = (new ServerRequestFactory())->createServerRequest($method, 'http://localhost' . $path)
        ->withHeader('Accept', 'application/json');
    if ($csrf) $request = $request->withHeader('X-CSRF-Token', $token);
    return $app->handle($request);
};
foreach (['/contacts/1', '/partners/' . str_repeat('a', 32), '/events/1/draft', '/events/1/approve',
    '/events/1/withdraw', '/events/1/assignments/1', '/consents', '/email', '/mailchimp/sync'] as $path) {
    Assert::same(403, $request('POST', '/sentinel/api' . $path)->getStatusCode());
}
foreach (['/partners', '/publications', '/public-events', '/consents', '/events/1/assignments'] as $path) {
    Assert::same(403, $request('GET', '/sentinel/api' . $path)->getStatusCode());
}
$user->editor = true;
Assert::same(403, $request('POST', '/sentinel/api/events/1/approve')->getStatusCode());
Assert::same(403, $request('POST', '/sentinel/api/events/1/withdraw')->getStatusCode());
$user->admin = true;
foreach (['/consents', '/email', '/events/1/draft', '/events/1/approve', '/events/1/assignments/1'] as $path) {
    Assert::same(403, $request('POST', '/sentinel/api' . $path, false)->getStatusCode());
}
Assert::same(422, $request('POST', '/sentinel/api/consents')->getStatusCode());
$user->selfOnly = true;
Assert::same(403, $request('GET', '/sentinel')->getStatusCode());
unset($_SESSION['AuthenticationProvider']);
session_abort();
