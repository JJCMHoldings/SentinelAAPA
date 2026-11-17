<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

final class Newsletter
{
    public function __construct(private Operations $operations, private \Closure $request)
    {
    }

    public static function mappings(string $json): array
    {
        $mapping = json_decode($json ?: '{}', true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($mapping)) {
            throw new \InvalidArgumentException('Use a team ID to audience ID JSON object.');
        }
        foreach ($mapping as $groupId => $audience) {
            Operations::id((string) $groupId);
            if (!is_string($audience) || !preg_match('/^[a-zA-Z0-9]{1,32}$/D', $audience)) {
                throw new \InvalidArgumentException('Choose a valid Mailchimp audience.');
            }
        }
        return $mapping;
    }

    public function sync(array $contacts, string $audience): array
    {
        if (!preg_match('/^[a-zA-Z0-9]{1,32}$/D', $audience)) {
            throw new \InvalidArgumentException('Choose a valid Mailchimp audience.');
        }
        $result = ['pending' => 0, 'existing' => 0, 'skipped' => 0, 'failed' => 0];
        $seen = [];
        foreach ($contacts as $contact) {
            try {
                $email = Operations::email($contact['email']);
            } catch (\InvalidArgumentException) {
                $result['skipped']++;
                continue;
            }
            if (isset($seen[$email])) {
                $result['skipped']++;
                continue;
            }
            $seen[$email] = true;
            $path = 'lists/' . $audience . '/members/' . md5($email);
            try {
                $remote = ($this->request)('get', $path, []);
                if (!$this->operations->hasConsent($email)) {
                    if ($this->operations->consentStatus($email) === false
                        && ($remote['ok'] ?? false) && ($remote['data']['status'] ?? '') === 'subscribed') {
                        $withdrawal = ($this->request)('patch', $path, ['status' => 'unsubscribed']);
                        $result[($withdrawal['ok'] ?? false) ? 'skipped' : 'failed']++;
                    } else {
                        $result[($remote['ok'] ?? false) || ($remote['code'] ?? 0) === 404 ? 'skipped' : 'failed']++;
                    }
                    continue;
                }
                if (($remote['ok'] ?? false) === true) {
                    $result[($remote['data']['status'] ?? '') === 'subscribed' ? 'existing' : 'skipped']++;
                    continue;
                }
                if (($remote['code'] ?? 0) !== 404) {
                    $result['failed']++;
                    continue;
                }
                // status_if_new preserves a suppression that appears between GET and PUT.
                $created = ($this->request)('put', $path, ['email_address' => $email, 'status_if_new' => 'pending']);
                $result[($created['ok'] ?? false) ? 'pending' : 'failed']++;
            } catch (\Throwable) {
                $result['failed']++;
            }
        }
        return $result;
    }
}
