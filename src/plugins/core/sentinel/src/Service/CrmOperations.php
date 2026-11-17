<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

use ChurchCRM\dto\SystemConfig;
use ChurchCRM\model\ChurchCRM\EventQuery;
use ChurchCRM\model\ChurchCRM\GroupQuery;
use ChurchCRM\model\ChurchCRM\Person2group2roleP2g2rQuery;
use ChurchCRM\model\ChurchCRM\PersonQuery;
use ChurchCRM\model\ChurchCRM\User;
use ChurchCRM\Service\EmailComposerService;
use Propel\Runtime\ActiveQuery\Criteria;

final class CrmOperations
{
    public function __construct(private Operations $operations)
    {
    }

    public static function lookup(string $type, string $search): array
    {
        $query = match ($type) {
            'contacts' => PersonQuery::create()->filterByLastName('%' . $search . '%', Criteria::LIKE)
                ->_or()->filterByFirstName('%' . $search . '%', Criteria::LIKE)->orderByLastName(),
            'teams' => GroupQuery::create()->filterByName('%' . $search . '%', Criteria::LIKE)->orderByName(),
            'events' => EventQuery::create()->filterByTitle('%' . $search . '%', Criteria::LIKE)->orderByStart(Criteria::DESC),
        };
        $rows = [];
        foreach ($query->limit(100)->find() as $record) {
            $rows[] = ['id' => $record->getId(), 'name' => match ($type) {
                'contacts' => $record->getFullName(), 'teams' => $record->getName(), 'events' => $record->getTitle(),
            }];
        }
        return $rows;
    }

    public static function personExists(int $id): bool { return PersonQuery::create()->findPk($id) !== null; }
    public static function eventExists(int $id): bool { return EventQuery::create()->findPk($id) !== null; }

    public static function requirePerson(int $id): void
    {
        if (!self::personExists($id)) {
            throw new \InvalidArgumentException('Contact not found.');
        }
    }

    public static function requireEvent(int $id): void
    {
        if (!self::eventExists($id)) {
            throw new \InvalidArgumentException('Event not found.');
        }
    }

    public function recipients(array $input): array
    {
        $scope = $input['scope'] ?? '';
        $id = Operations::id($input['id'] ?? null);
        if ($scope === 'team') {
            if (GroupQuery::create()->findPk($id) === null) {
                throw new \InvalidArgumentException('Team not found.');
            }
            $ids = Person2group2roleP2g2rQuery::create()->filterByGroupId($id)->select('PersonId')->find()->toArray();
        } elseif ($scope === 'event') {
            self::requireEvent($id);
            $ids = array_column(array_filter($this->operations->assignments($id),
                static fn ($row) => !in_array($row['status'], ['declined', 'cancelled'], true)), 'personId');
        } else {
            throw new \InvalidArgumentException('Choose a team or an event.');
        }
        if (count($ids) > EmailComposerService::MAX_RECIPIENTS) {
            throw new \InvalidArgumentException('Use Mailchimp for audiences larger than 500.');
        }
        return (new EmailComposerService())->resolveRecipients($ids, []);
    }

    public function email(array $input, User $user): array
    {
        if (!SystemConfig::isEmailEnabled()) {
            throw new \InvalidArgumentException('Configure ChurchCRM sender identity and SMTP before sending.');
        }
        $subject = Operations::text($input, 'subject', 255, true);
        $body = Operations::text($input, 'body', 20000, true);
        if (strpbrk($subject, "\r\n") !== false) {
            throw new \InvalidArgumentException('Use a single line subject.');
        }
        $resolved = $this->recipients($input);
        if (($input['preview'] ?? true) !== false) {
            return $resolved;
        }
        if ($resolved['recipients'] === []) {
            return $resolved + ['batch' => null];
        }
        $batch = (new EmailComposerService())->queue($resolved['recipients'], $subject, $body, $user);
        return $resolved + ['batch' => $batch];
    }

    public function feed(string $artwork): array
    {
        return $this->operations->publicEvents(static function (int $id): bool {
            $event = EventQuery::create()->findPk($id);
            return $event !== null && (int) $event->getInActive() === 0;
        }, $artwork);
    }
}
