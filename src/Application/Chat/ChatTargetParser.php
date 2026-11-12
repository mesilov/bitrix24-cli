<?php

declare(strict_types=1);

namespace Bitrix24\CLI\Application\Chat;

use Bitrix24\CLI\Application\Failure;

/**
 * Parses a chat destination without network access.
 *
 * Typed IDs: chatN, sgN, group:N, task:N. A bare number is ambiguous and rejected.
 * URL allowlist (HTTPS, the host is checked against the selected connection later):
 *  - /online/?IM_DIALOG=chatN|sgN[&IM_MESSAGE=M]
 *  - /company/personal/user/U/tasks/task/view/T/
 *  - /workgroups/group/G/tasks/task/view/T/
 *  - /workgroups/group/G/
 */
final class ChatTargetParser
{
    private const ID = '[1-9][0-9]{0,17}';

    private const TARGET_KEYS = ['IM_DIALOG', 'IM_MESSAGE'];

    public function parse(mixed $value): ChatTarget
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw Failure::usage('Provide TARGET as a URL or a typed ID: chatN, sgN, group:N or task:N.');
        }

        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x20\x7f]/', $value) === 1) {
            throw Failure::usage('Provide TARGET without whitespace or control characters.');
        }

        if (preg_match('~^https?://~i', $value) === 1) {
            return $this->url($value);
        }

        return $this->typed($value);
    }

    private function typed(string $value): ChatTarget
    {
        if (preg_match('/^[0-9]+$/D', $value) === 1) {
            throw Failure::usage('A bare numeric TARGET is ambiguous; use chatN, sgN, group:N or task:N.');
        }

        foreach (['chat' => ChatTarget::KIND_CHAT, 'sg' => ChatTarget::KIND_PROJECT, 'group:' => ChatTarget::KIND_PROJECT, 'task:' => ChatTarget::KIND_TASK] as $prefix => $kind) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(' . self::ID . ')$/D', $value, $match) === 1) {
                return new ChatTarget($kind, (int) $match[1], 'id');
            }
        }

        throw Failure::usage('Unsupported TARGET; use a supported portal URL or chatN, sgN, group:N, task:N.');
    }

    private function url(string $value): ChatTarget
    {
        $url = parse_url($value);
        if (!is_array($url) || strtolower($url['scheme'] ?? '') !== 'https') {
            throw Failure::usage('Provide the portal URL over HTTPS.');
        }

        if (isset($url['user']) || isset($url['pass'])) {
            throw Failure::usage('A TARGET URL must not contain user information.');
        }

        if (isset($url['port']) && $url['port'] !== 443) {
            throw Failure::usage('A TARGET URL must not use a nonstandard port.');
        }

        $host = rtrim(strtolower($url['host'] ?? ''), '.');
        if (preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/D', $host) !== 1) {
            throw Failure::usage('A TARGET URL must contain a valid portal hostname.');
        }

        $query = $this->query($url['query'] ?? '');
        $path = $url['path'] ?? '/';
        $dialog = $query['IM_DIALOG'] ?? null;
        $message = $query['IM_MESSAGE'] ?? null;

        if (preg_match('~^/online/?$~D', $path) === 1) {
            if ($dialog === null) {
                throw Failure::usage('The messenger URL has no IM_DIALOG chat destination.');
            }

            if (preg_match('/^[0-9]+$/D', $dialog) === 1) {
                throw Failure::usage('Personal dialogs are not supported; use a group, project or task chat.');
            }

            if (preg_match('/^(chat|sg)(' . self::ID . ')$/D', $dialog, $match) !== 1) {
                throw Failure::usage('Unsupported IM_DIALOG value; expected chatN or sgN.');
            }

            if ($message !== null && preg_match('/^' . self::ID . '$/D', $message) !== 1) {
                throw Failure::usage('Unsupported IM_MESSAGE value; expected a positive message ID.');
            }

            return new ChatTarget($match[1] === 'chat' ? ChatTarget::KIND_CHAT : ChatTarget::KIND_PROJECT, (int) $match[2], 'url', $host, $message === null ? null : (int) $message);
        }

        if ($dialog !== null || $message !== null) {
            throw Failure::usage('The URL selects conflicting destinations; pass one chat, project or task.');
        }

        // Task paths are matched before the general project path.
        if (preg_match('~^/company/personal/user/' . self::ID . '/tasks/task/view/(' . self::ID . ')/?$~D', $path, $match) === 1
            || preg_match('~^/workgroups/group/' . self::ID . '/tasks/task/view/(' . self::ID . ')/?$~D', $path, $match) === 1) {
            return new ChatTarget(ChatTarget::KIND_TASK, (int) $match[1], 'url', $host);
        }

        if (preg_match('~^/workgroups/group/(' . self::ID . ')/?$~D', $path, $match) === 1) {
            return new ChatTarget(ChatTarget::KIND_PROJECT, (int) $match[1], 'url', $host);
        }

        throw Failure::usage('Unsupported portal URL form; use a messenger chat, project or task link, or a typed ID.');
    }

    /** @return array<string, string> Only destination keys; other navigation parameters are ignored. */
    private function query(string $query): array
    {
        $found = [];
        foreach ($query === '' ? [] : explode('&', $query) as $pair) {
            [$key, $raw] = array_pad(explode('=', $pair, 2), 2, '');
            $key = rawurldecode($key);
            if (!in_array(strtoupper($key), self::TARGET_KEYS, true)) {
                continue;
            }

            if ($key !== strtoupper($key) || array_key_exists($key, $found)) {
                throw Failure::usage('The URL repeats or misspells a destination parameter; pass one destination.');
            }

            $decoded = rawurldecode(str_replace('+', ' ', $raw));
            if (str_contains($decoded, '%') || preg_match('/[\x00-\x20\x7f]/', $decoded) === 1) {
                throw Failure::usage('The URL destination parameter has ambiguous encoding.');
            }

            $found[$key] = $decoded;
        }

        return $found;
    }
}
