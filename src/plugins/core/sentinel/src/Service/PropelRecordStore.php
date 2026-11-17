<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

use ChurchCRM\Plugins\Sentinel\Model\SentinelRecord;
use ChurchCRM\Plugins\Sentinel\Model\SentinelRecordQuery;
use ChurchCRM\Plugins\Sentinel\Model\Map\SentinelRecordTableMap;
use Propel\Runtime\Exception\PropelException;
use Propel\Runtime\Propel;

final class PropelRecordStore implements RecordStore
{
    public function __construct()
    {
        Propel::getServiceContainer()->initDatabaseMaps(['default' => [SentinelRecordTableMap::class]]);
    }

    public function get(string $key): ?array
    {
        $row = SentinelRecordQuery::create()->findPk($key);
        return $row === null ? null : $this->decode($row);
    }

    public function list(string $kind): array
    {
        $result = [];
        foreach (SentinelRecordQuery::create()->filterByKind($kind)->orderByRecordKey()->find() as $row) {
            $result[] = $this->decode($row);
        }
        return $result;
    }

    public function save(string $key, string $kind, array $data, int $expectedRevision): array
    {
        unset($data['revision'], $data['key']);
        $payload = json_encode($data, JSON_THROW_ON_ERROR);
        if (strlen($payload) > 262144) {
            throw new \InvalidArgumentException('Record is too large.');
        }
        if ($expectedRevision === 0) {
            try {
                (new SentinelRecord())->setRecordKey($key)->setKind($kind)->setPayload($payload)->setRevision(1)->save();
            } catch (PropelException $e) {
                if ($this->get($key) !== null) {
                    throw new \DomainException('This record changed. Reload before saving.', 409, $e);
                }
                throw $e;
            }
        } else {
            $changed = SentinelRecordQuery::create()->filterByRecordKey($key)->filterByKind($kind)
                ->filterByRevision($expectedRevision)->update(['Payload' => $payload, 'Revision' => $expectedRevision + 1]);
            if ($changed !== 1) {
                throw new \DomainException('This record changed. Reload before saving.', 409);
            }
        }
        return $data + ['key' => $key, 'revision' => $expectedRevision + 1];
    }

    private function decode(SentinelRecord $row): array
    {
        return json_decode($row->getPayload(), true, 512, JSON_THROW_ON_ERROR)
            + ['key' => $row->getRecordKey(), 'revision' => $row->getRevision()];
    }

    public function remove(string $key, int $expectedRevision): void
    {
        $changed = SentinelRecordQuery::create()->filterByRecordKey($key)->filterByRevision($expectedRevision)->delete();
        if ($changed !== 1) {
            throw new \DomainException('This record changed. Reload before deleting.', 409);
        }
    }
}
