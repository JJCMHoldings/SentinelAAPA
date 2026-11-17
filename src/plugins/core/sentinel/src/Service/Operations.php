<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

use DateTimeImmutable;

final class Operations
{
    public function __construct(private RecordStore $store)
    {
    }

    public static function text(array $input, string $key, int $max = 255, bool $required = false): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value) || strlen($value) > $max || ($required && trim($value) === '')) {
            throw new \InvalidArgumentException('Check the field: ' . $key);
        }
        return trim($value);
    }

    public static function id(mixed $value): int
    {
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value < 1) {
            throw new \InvalidArgumentException('Choose a valid record.');
        }
        return (int) $value;
    }

    public static function revision(array $input): int
    {
        $value = $input['revision'] ?? null;
        if ((!is_int($value) && !(is_string($value) && ctype_digit($value))) || (int) $value < 0) {
            throw new \InvalidArgumentException('Reload this record before saving.');
        }
        return (int) $value;
    }

    public function contacts(): array
    {
        return $this->store->list('contact');
    }

    public function contact(int $personId, array $input, int $actor): array
    {
        $roles = $input['roles'] ?? [];
        if (!is_array($roles) || count(array_filter($roles, 'is_string')) !== count($roles)
            || array_diff($roles, ['staff', 'volunteer']) !== []) {
            throw new \InvalidArgumentException('Choose staff or volunteer roles.');
        }
        return $this->store->save('contact:' . $personId, 'contact', [
            'personId' => $personId, 'roles' => array_values(array_unique($roles)),
            'updatedBy' => $actor, 'updatedAt' => gmdate('c'),
        ], self::revision($input));
    }

    public function partners(): array
    {
        return $this->store->list('partner');
    }

    public function partner(string $id, array $input, int $actor, callable $personExists, callable $eventExists): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $id)) {
            throw new \InvalidArgumentException('Choose a valid partner.');
        }
        $contactIds = $this->ids($input['contactIds'] ?? [], $personExists);
        $history = $input['history'] ?? [];
        if (!is_array($history) || count($history) > 200) {
            throw new \InvalidArgumentException('Limit the partner history to 200 entries.');
        }
        $cleanHistory = [];
        foreach ($history as $entry) {
            if (!is_array($entry)) {
                throw new \InvalidArgumentException('Check partner history.');
            }
            $eventId = self::id($entry['eventId'] ?? null);
            if (!$eventExists($eventId)) {
                throw new \InvalidArgumentException('A linked event no longer exists.');
            }
            $cleanHistory[] = ['eventId' => $eventId, 'description' => self::text($entry, 'description', 1000, true)];
        }
        return $this->store->save('partner:' . $id, 'partner', [
            'id' => $id, 'name' => self::text($input, 'name', 160, true),
            'type' => self::text($input, 'type', 80, true), 'location' => self::text($input, 'location', 255),
            'contactIds' => $contactIds, 'notes' => self::text($input, 'notes', 10000), 'history' => $cleanHistory,
            'updatedBy' => $actor, 'updatedAt' => gmdate('c'),
        ], self::revision($input));
    }

    public function publications(): array
    {
        return $this->store->list('publication');
    }

    public function draft(int $eventId, array $input, int $actor): array
    {
        $old = $this->store->get('publication:' . $eventId);
        $start = self::date(self::text($input, 'start', 40, true));
        $end = self::date(self::text($input, 'end', 40, true));
        if ($end < $start) {
            throw new \InvalidArgumentException('The end must follow the start.');
        }
        $image = self::text($input, 'imageUrl', 2048);
        if ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL) || parse_url($image, PHP_URL_SCHEME) !== 'https')) {
            throw new \InvalidArgumentException('Use an HTTPS image URL.');
        }
        $draft = [
            'title' => self::text($input, 'title', 160, true),
            'description' => self::text($input, 'description', 10000),
            'location' => self::text($input, 'location', 500),
            'start' => $start->format('c'), 'end' => $end->format('c'), 'imageUrl' => $image,
        ];
        return $this->store->save('publication:' . $eventId, 'publication', [
            'eventId' => $eventId, 'state' => 'draft', 'draft' => $draft,
            'approved' => $old['approved'] ?? null, 'approvedBy' => $old['approvedBy'] ?? null,
            'approvedAt' => $old['approvedAt'] ?? null,
            'updatedBy' => $actor, 'updatedAt' => gmdate('c'),
        ], self::revision($input));
    }

    public function publish(int $eventId, int $revision, int $actor, bool $withdraw = false): array
    {
        $record = $this->store->get('publication:' . $eventId);
        if ($record === null) {
            throw new \InvalidArgumentException('Save the public details before approval.');
        }
        $record['state'] = $withdraw ? 'withdrawn' : 'published';
        $record['approved'] = $withdraw ? null : $record['draft'];
        $record['approvedBy'] = $actor;
        $record['approvedAt'] = gmdate('c');
        return $this->store->save('publication:' . $eventId, 'publication', $record, $revision);
    }

    public function publicEvents(callable $eventIsPubliclyEligible, string $defaultArtwork): array
    {
        $events = [];
        foreach ($this->publications() as $record) {
            if (!is_array($record['approved'] ?? null) || !$eventIsPubliclyEligible($record['eventId'])) {
                continue;
            }
            $approved = $record['approved'];
            $events[] = [
                'id' => $record['eventId'], 'title' => $approved['title'],
                'description' => $approved['description'], 'location' => $approved['location'],
                'start' => $approved['start'], 'end' => $approved['end'],
                'imageUrl' => $approved['imageUrl'] ?: $defaultArtwork,
            ];
        }
        usort($events, static fn ($a, $b) => new DateTimeImmutable($a['start']) <=> new DateTimeImmutable($b['start']));
        return $events;
    }

    public function assignments(int $eventId): array
    {
        return array_values(array_filter($this->store->list('assignment'), static fn ($row) => $row['eventId'] === $eventId));
    }

    public function assign(int $eventId, int $personId, array $input, int $actor): array
    {
        $status = $input['status'] ?? 'invited';
        if (!in_array($status, ['invited', 'confirmed', 'declined', 'completed', 'cancelled'], true)) {
            throw new \InvalidArgumentException('Choose a valid assignment status.');
        }
        return $this->store->save('assignment:' . $eventId . ':' . $personId, 'assignment', [
            'eventId' => $eventId, 'personId' => $personId,
            'role' => self::text($input, 'role', 160, true), 'status' => $status,
            'updatedBy' => $actor, 'updatedAt' => gmdate('c'),
        ], self::revision($input));
    }

    public function consent(string $email, array $input, int $actor): array
    {
        $email = self::email($email);
        if (!is_bool($input['granted'] ?? null)) {
            throw new \InvalidArgumentException('Record an explicit consent choice.');
        }
        $key = 'consent:' . hash('sha256', $email);
        $old = $this->store->get($key);
        $history = $old['history'] ?? [];
        $entry = ['granted' => $input['granted'], 'source' => self::text($input, 'source', 1000, true),
            'at' => gmdate('c'), 'by' => $actor];
        $history[] = $entry;
        return $this->store->save($key, 'consent', [
            'email' => $email, 'granted' => $input['granted'], 'history' => $history,
        ], self::revision($input));
    }

    public function hasConsent(string $email): bool
    {
        return $this->consentStatus($email) === true;
    }

    public function consentStatus(string $email): ?bool
    {
        $record = $this->store->get('consent:' . hash('sha256', self::email($email)));
        return $record['granted'] ?? null;
    }

    public function consents(): array
    {
        return $this->store->list('consent');
    }

    public static function email(string $email): string
    {
        $email = strtolower(trim($email));
        if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Enter a valid email address.');
        }
        return $email;
    }

    private function ids(mixed $input, callable $exists): array
    {
        if (!is_array($input) || count($input) > 500) {
            throw new \InvalidArgumentException('Choose up to 500 contacts.');
        }
        $ids = [];
        foreach ($input as $value) {
            $id = self::id($value);
            if (!$exists($id)) {
                throw new \InvalidArgumentException('A linked contact no longer exists.');
            }
            $ids[] = $id;
        }
        return array_values(array_unique($ids));
    }

    private static function date(string $value): DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new \InvalidArgumentException('Use an ISO date and time with a timezone offset.');
        }
        $date = new DateTimeImmutable($value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($errors !== false && ($errors['warning_count'] || $errors['error_count'])) {
            throw new \InvalidArgumentException('Enter a valid event date.');
        }
        return $date;
    }
}
