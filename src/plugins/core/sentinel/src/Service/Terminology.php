<?php

namespace ChurchCRM\Plugins\Sentinel\Service;

final class Terminology
{
    public const DEFAULTS = [
        'People' => 'Contacts', 'Person' => 'Contact', 'Persons' => 'Contacts',
        'Groups' => 'Teams', 'Group' => 'Team', 'Ministries' => 'Programs', 'Ministry' => 'Program',
        'Events' => 'AAPA Events', 'Communication' => 'Email',
        'Add New Person' => 'Add New Contact', 'Person Listing' => 'Contact Listing',
    ];

    public static function mapping(string $json): array
    {
        $overrides = trim($json) === '' ? [] : json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($overrides) || array_diff_key($overrides, self::DEFAULTS) !== []) {
            throw new \InvalidArgumentException('Choose terminology keys from the documented mapping.');
        }
        foreach ($overrides as $value) {
            if (!is_string($value) || trim($value) === '' || strlen($value) > 80 || strip_tags($value) !== $value) {
                throw new \InvalidArgumentException('Terminology must contain plain text labels of up to 80 characters.');
            }
        }
        return array_replace(self::DEFAULTS, $overrides);
    }

    public static function replace(string $text, array $mapping): string
    {
        $keys = array_keys($mapping);
        usort($keys, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        return preg_replace_callback('/\b(' . implode('|', array_map(static fn ($key) => preg_quote($key, '/'), $keys)) . ')\b/u',
            static fn ($match) => $mapping[$match[0]], $text);
    }
}
