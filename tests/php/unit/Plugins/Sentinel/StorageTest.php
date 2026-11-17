<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use ChurchCRM\Plugins\Sentinel\Service\PropelRecordStore;
use Propel\Runtime\Propel;
use Propel\Runtime\Connection\ConnectionManagerSingle;
use Tester\Assert;

require dirname(__DIR__, 3) . '/bootstrap.php';
if (!extension_loaded('pdo_sqlite')) {
    Tester\Environment::skip('SQLite PDO is required for the isolated ORM storage test.');
}
$loader = new ClassLoader();
$loader->addPsr4('ChurchCRM\\Plugins\\Sentinel\\', APP_ROOT . '/plugins/core/sentinel/src');
$loader->register();
$manager = new ConnectionManagerSingle('default');
$manager->setConfiguration(['dsn' => 'sqlite::memory:']);
$manager->setName('default');
Propel::getServiceContainer()->setAdapterClass('default', 'sqlite');
Propel::getServiceContainer()->setConnectionManager($manager);
$connection = Propel::getWriteConnection('default');
$connection->exec('CREATE TABLE sentinel_record (record_key VARCHAR(100) PRIMARY KEY, kind VARCHAR(24) NOT NULL, payload TEXT NOT NULL, revision INTEGER NOT NULL DEFAULT 1)');
$store = new PropelRecordStore();
$record = $store->save('contact:1', 'contact', ['personId' => 1, 'roles' => ['staff']], 0);
Assert::same(1, $record['revision']);
Assert::same(['staff'], $store->get('contact:1')['roles']);
Assert::count(1, $store->list('contact'));
Assert::same([], $store->list('partner'));
Assert::exception(static fn () => $store->save('contact:1', 'contact', ['personId' => 1], 0), DomainException::class);
$store->save('contact:1', 'contact', ['personId' => 1, 'roles' => ['volunteer']], 1);
Assert::exception(static fn () => $store->save('contact:1', 'contact', ['personId' => 1, 'roles' => ['staff']], 1), DomainException::class);
Assert::same(['volunteer'], $store->get('contact:1')['roles']);
Assert::same(2, $store->get('contact:1')['revision']);
Assert::null($store->get('missing'));
Assert::exception(static fn () => $store->save('contact:2', 'contact', ['large' => str_repeat('a', 300000)], 0), InvalidArgumentException::class);
Assert::exception(static fn () => $store->remove('contact:1', 1), DomainException::class);
$store->remove('contact:1', 2);
Assert::null($store->get('contact:1'));
