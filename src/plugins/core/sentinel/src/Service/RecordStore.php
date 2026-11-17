<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

interface RecordStore
{
    public function get(string $key): ?array;
    public function list(string $kind): array;
    public function save(string $key, string $kind, array $data, int $expectedRevision): array;
    public function remove(string $key, int $expectedRevision): void;
}
