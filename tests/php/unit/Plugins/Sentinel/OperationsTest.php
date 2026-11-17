<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use ChurchCRM\Plugins\Sentinel\Service\Operations;
use ChurchCRM\Plugins\Sentinel\Service\Newsletter;
use ChurchCRM\Plugins\Sentinel\Service\RecordStore;
use ChurchCRM\Plugins\Sentinel\Service\Access;
use ChurchCRM\Plugins\Sentinel\Service\Terminology;
use Tester\Assert;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Portal\Auth\PortalMemberUser;

require dirname(__DIR__, 3) . '/bootstrap.php';
$loader = new ClassLoader();
$loader->addPsr4('ChurchCRM\\Plugins\\Sentinel\\', APP_ROOT . '/plugins/core/sentinel/src');
$loader->register();

$store = new class implements RecordStore {
    public array $records = [];
    public function get(string $key): ?array { return $this->records[$key] ?? null; }
    public function list(string $kind): array { return array_values(array_filter($this->records, static fn ($row) => $row['kind'] === $kind)); }
    public function save(string $key, string $kind, array $data, int $expectedRevision): array
    {
        if (($this->records[$key]['revision'] ?? 0) !== $expectedRevision) {
            throw new DomainException('Conflict', 409);
        }
        unset($data['revision'], $data['key']);
        return $this->records[$key] = $data + ['key' => $key, 'kind' => $kind, 'revision' => $expectedRevision + 1];
    }
    public function remove(string $key, int $expectedRevision): void
    {
        if (($this->records[$key]['revision'] ?? 0) !== $expectedRevision) throw new DomainException('Conflict', 409);
        unset($this->records[$key]);
    }
};
$ops = new Operations($store);
$draft = ['revision' => 0, 'title' => 'Public training', 'description' => 'Approved attendees welcome.',
    'location' => 'Public hall', 'start' => '2026-12-01T18:00:00-05:00', 'end' => '2026-12-01T20:00:00-05:00',
    'imageUrl' => '', 'privateNotes' => 'Private staff details'];
$ops->draft(9, $draft, 1);
Assert::same([], $ops->publicEvents(static fn () => true, '/art.svg'));
$ops->publish(9, 1, 2);
$feed = $ops->publicEvents(static fn () => true, '/art.svg');
Assert::count(1, $feed);
Assert::same('/art.svg', $feed[0]['imageUrl']);
Assert::false(array_key_exists('privateNotes', $feed[0]));
Assert::false(array_key_exists('approvedBy', $feed[0]));
Assert::false(array_key_exists('draft', $feed[0]));
$edited = array_replace($draft, ['revision' => 2, 'title' => 'Unreviewed title']);
$ops->draft(9, $edited, 1);
Assert::same('Public training', $ops->publicEvents(static fn () => true, '/art.svg')[0]['title']);
Assert::exception(static fn () => $ops->publish(9, 2, 2), DomainException::class);
$ops->publish(9, 3, 2);
Assert::same('Unreviewed title', $ops->publicEvents(static fn () => true, '/art.svg')[0]['title']);
Assert::same([], $ops->publicEvents(static fn () => false, '/art.svg'));
$ops->publish(9, 4, 2, true);
Assert::same([], $ops->publicEvents(static fn () => true, '/art.svg'));
$ops->draft(9, array_replace($edited, ['revision' => 5]), 1);
Assert::same([], $ops->publicEvents(static fn () => true, '/art.svg'));
Assert::exception(static fn () => $ops->draft(10, array_replace($draft, ['start' => '2026-02-30T18:00:00-05:00']), 1), InvalidArgumentException::class);
Assert::exception(static fn () => $ops->draft(10, array_replace($draft, ['end' => '2026-11-01T18:00:00Z']), 1), InvalidArgumentException::class);
Assert::exception(static fn () => $ops->draft(10, array_replace($draft, ['imageUrl' => 'javascript:alert(1)']), 1), InvalidArgumentException::class);
$ops->draft(10, array_replace($draft, ['start' => '2026-12-02T09:00:00+02:00', 'end' => '2026-12-02T10:00:00+02:00']), 1);
$ops->publish(10, 1, 2);
$ops->draft(11, array_replace($draft, ['start' => '2026-12-02T08:30:00Z', 'end' => '2026-12-02T10:00:00Z']), 1);
$ops->publish(11, 1, 2);
Assert::same([10, 11], array_column($ops->publicEvents(static fn () => true, '/art.svg'), 'id'));

$ops->contact(1, ['revision' => 0, 'roles' => ['staff', 'volunteer']], 2);
Assert::same(['staff', 'volunteer'], $ops->contacts()[0]['roles']);
Assert::exception(static fn () => $ops->contact(1, ['revision' => 0, 'roles' => ['staff']], 2), DomainException::class);
Assert::exception(static fn () => $ops->contact(2, ['revision' => 0, 'roles' => ['admin']], 2), InvalidArgumentException::class);
$partnerId = str_repeat('a', 32);
$partner = ['revision' => 0, 'name' => 'Example church', 'type' => 'church', 'contactIds' => [1, '1'],
    'history' => [['eventId' => 9, 'description' => 'Training']], 'notes' => 'Internal note'];
$row = $ops->partner($partnerId, $partner, 2, static fn ($id) => $id === 1, static fn ($id) => $id === 9);
Assert::same([1], $row['contactIds']);
Assert::same(9, $row['history'][0]['eventId']);
Assert::exception(static fn () => $ops->partner(str_repeat('b', 32), $partner, 2, static fn () => false, static fn () => true), InvalidArgumentException::class);
$ops->assign(9, 1, ['revision' => 0, 'role' => 'Setup', 'status' => 'confirmed'], 2);
Assert::count(1, $ops->assignments(9));
Assert::same([], $ops->assignments(10));

$consent = $ops->consent(' TEST@example.org ', ['revision' => 0, 'granted' => true, 'source' => 'Signed form 123'], 2);
Assert::true($ops->hasConsent('test@EXAMPLE.org'));
Assert::exception(static fn () => $ops->consent('test@example.org', ['revision' => 0, 'granted' => true, 'source' => 'Duplicate'], 2), DomainException::class);
$ops->consent('test@example.org', ['revision' => 1, 'granted' => false, 'source' => 'Withdrawal request'], 2);
Assert::false($ops->hasConsent('test@example.org'));
Assert::count(2, $ops->consents()[0]['history']);
Assert::null($ops->consentStatus('unknown@example.org'));

$ops->consent('new@example.org', ['revision' => 0, 'granted' => true, 'source' => 'Written permission'], 2);
$ops->consent('unsub@example.org', ['revision' => 0, 'granted' => true, 'source' => 'Written permission'], 2);
$ops->consent('fail@example.org', ['revision' => 0, 'granted' => true, 'source' => 'Written permission'], 2);
$calls = [];
$transport = static function ($method, $path, $data) use (&$calls): array {
    $calls[] = [$method, $path, $data];
    if ($method === 'get') {
        if (str_ends_with($path, md5('unsub@example.org'))) return ['ok' => true, 'code' => 200, 'data' => ['status' => 'unsubscribed']];
        if (str_ends_with($path, md5('fail@example.org'))) return ['ok' => false, 'code' => 500];
        if (str_ends_with($path, md5('test@example.org')) || str_ends_with($path, md5('unknown@example.org'))) return ['ok' => true, 'code' => 200, 'data' => ['status' => 'subscribed']];
        return ['ok' => false, 'code' => 404];
    }
    return ['ok' => true, 'code' => 200];
};
$newsletter = new Newsletter($ops, $transport);
$result = $newsletter->sync(array_map(static fn ($email) => ['email' => $email], [
    'new@example.org', 'NEW@example.org', 'unsub@example.org', 'fail@example.org', 'test@example.org', 'unknown@example.org', 'invalid',
]), 'audience1');
Assert::same(['pending' => 1, 'existing' => 0, 'skipped' => 5, 'failed' => 1], $result);
$writes = array_values(array_filter($calls, static fn ($call) => $call[0] !== 'get'));
Assert::count(2, $writes);
Assert::same(['email_address' => 'new@example.org', 'status_if_new' => 'pending'], $writes[0][2]);
Assert::same(['status' => 'unsubscribed'], $writes[1][2]);
Assert::same(['12' => 'audience1'], Newsletter::mappings('{"12":"audience1"}'));
Assert::exception(static fn () => Newsletter::mappings('{"12":"../../other"}'), InvalidArgumentException::class);

$zero = new class extends User {
    public function isEditRecordsEnabled(): bool { return false; }
    public function isNotesEnabled(): bool { return false; }
    public function canViewEvents(): bool { return false; }
    public function canManageEvents(): bool { return false; }
    public function isAdmin(): bool { return false; }
    public function isEmailEnabled(): bool { return false; }
};
Assert::true(Access::allows($zero, 'read'));
foreach (['contacts', 'partner-read', 'partners', 'event-write', 'assignments', 'publish', 'consent', 'settings', 'email', 'sync', 'unknown'] as $action) {
    Assert::false(Access::allows($zero, $action));
}
$self = PortalMemberUser::forPerson(1);
Assert::false(Access::allows($self, 'read'));
$admin = new class extends User {
    public function isEditRecordsEnabled(): bool { return true; }
    public function isNotesEnabled(): bool { return true; }
    public function canViewEvents(): bool { return true; }
    public function canManageEvents(): bool { return true; }
    public function isAdmin(): bool { return true; }
    public function isEmailEnabled(): bool { return true; }
};
foreach (['contacts', 'partner-read', 'partners', 'event-write', 'assignments', 'publish', 'consent', 'settings', 'email', 'sync'] as $action) Assert::true(Access::allows($admin, $action));
Assert::same('Contacts and Teams', Terminology::replace('People and Groups', Terminology::mapping('')));
Assert::same('Organizers', Terminology::mapping('{"People":"Organizers"}')['People']);
Assert::exception(static fn () => Terminology::mapping('{"People":"<script>"}'), InvalidArgumentException::class);
