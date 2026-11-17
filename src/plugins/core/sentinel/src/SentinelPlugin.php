<?php

namespace ChurchCRM\Plugins\Sentinel;

use ChurchCRM\dto\SystemURLs;
use ChurchCRM\Plugin\AbstractPlugin;
use ChurchCRM\Plugin\Hook\HookManager;
use ChurchCRM\Plugin\Hooks;
use ChurchCRM\Plugins\Sentinel\Service\Operations;
use ChurchCRM\Plugins\Sentinel\Service\PropelRecordStore;
use ChurchCRM\Plugins\Sentinel\Service\Terminology;
use ChurchCRM\Utils\InputUtils;
use ChurchCRM\Utils\SQLUtils;
use Propel\Runtime\Propel;

final class SentinelPlugin extends AbstractPlugin
{
    private static ?self $instance = null;

    public function __construct(string $basePath = '')
    {
        parent::__construct($basePath);
        self::$instance = $this;
    }

    public static function getInstance(): ?self { return self::$instance; }
    public function getId(): string { return 'sentinel'; }
    public function getName(): string { return 'Sentinel'; }
    public function getDescription(): string { return dgettext('sentinel', 'Contacts, partners, events and ministry operations.'); }
    public function getType(): string { return 'core'; }
    public function getAuthor(): string { return 'JJCM Holdings'; }
    public function getMinimumCRMVersion(): string { return '7.8.5'; }
    public function getSettingsUrl(): ?string { return 'plugins/sentinel'; }

    public function boot(): void
    {
        HookManager::addAction(Hooks::EVENT_DELETED, static function (int $eventId): void {
            $store = new PropelRecordStore();
            $publication = $store->get('publication:' . $eventId);
            if ($publication !== null) {
                $store->remove($publication['key'], $publication['revision']);
            }
            foreach ($store->list('assignment') as $row) {
                if ($row['eventId'] === $eventId) {
                    $store->remove($row['key'], $row['revision']);
                }
            }
        });
    }

    public function activate(): void
    {
        SQLUtils::sqlImport($this->basePath . '/schema/install.sql', Propel::getWriteConnection('default'));
    }

    public function operations(): Operations
    {
        return new Operations(new PropelRecordStore());
    }

    public function setting(string $key): string
    {
        if (!in_array($key, ['organizationName', 'terminology', 'mailchimpApiKey', 'mailchimpMappings', 'newsletterUrl'], true)) {
            throw new \InvalidArgumentException('Unknown Sentinel setting.');
        }
        return $this->getConfigValue($key);
    }

    public function getSettingsSchema(): array
    {
        return $this->getManifest()['settings'];
    }

    public function getMenuItems(): array
    {
        return $this->getManifest()['menuItems'];
    }

    public function getClientConfig(): array
    {
        return ['terminology' => Terminology::mapping($this->setting('terminology'))];
    }

    public function getFooterContent(): string
    {
        return '<script defer src="' . InputUtils::escapeAttribute(SystemURLs::getRootPath()
            . '/plugins/core/sentinel/assets/sentinel.js?v=' . $this->getVersion()) . '"></script>';
    }
}
