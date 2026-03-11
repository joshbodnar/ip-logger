<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class MemcachedStorage implements StorageInterface
{
    private \Memcached $memcached;

    private string $keyPrefix;

    private const DEFAULT_TTL = 2592000;

    public function __construct(\Memcached $memcached, string $keyPrefix = 'ip_logger:')
    {
        $this->memcached = $memcached;
        $this->keyPrefix = $keyPrefix;
    }

    public function save(LogEntry $entry): void
    {
        $id = uniqid('ip_log_', true);
        $key = $this->keyPrefix . $id;

        $data = [
            'ip' => $entry->getIp(),
            'userAgent' => $entry->getUserAgent(),
            'timestamp' => $entry->getTimestamp()->format(\DateTimeInterface::ISO8601),
        ];

        $serialized = serialize($data);
        if ($serialized === false) {
            throw new RuntimeException('Failed to serialize log entry');
        }

        $this->memcached->set($key, $serialized, self::DEFAULT_TTL);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        $keys = $this->getLogKeys();
        if (empty($keys)) {
            return [];
        }

        return $this->fetchEntries($keys);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        $keys = $this->getLogKeys();
        if (empty($keys)) {
            return [];
        }

        $matchingKeys = [];
        foreach ($keys as $key) {
            $data = $this->memcached->get($key);
            if ($data !== false && $this->matchesIp($data, $ip)) {
                $matchingKeys[] = $key;
            }
        }

        return $this->fetchEntries($matchingKeys);
    }

    public function clear(): void
    {
        $keys = $this->getLogKeys();
        if (!empty($keys)) {
            $this->memcached->deleteMulti($keys);
        }
    }

    public function isBanned(string $ip): bool
    {
        return $this->memcached->get($this->keyPrefix . 'banned:' . $ip) !== false;
    }

    public function banIp(string $ip): void
    {
        $this->memcached->set($this->keyPrefix . 'banned:' . $ip, '1', self::DEFAULT_TTL);
    }

    public function unbanIp(string $ip): void
    {
        $this->memcached->delete($this->keyPrefix . 'banned:' . $ip);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        $allKeys = $this->memcached->getAllKeys();
        if ($allKeys === false) {
            return [];
        }

        $bannedKeys = array_filter(
            $allKeys,
            fn(string $key) => str_starts_with($key, $this->keyPrefix . 'banned:')
        );

        return array_map(
            fn(string $key) => str_replace($this->keyPrefix . 'banned:', '', $key),
            $bannedKeys
        );
    }

    public function clearBans(): void
    {
        $bannedIps = $this->getBannedIps();
        $keys = array_map(
            fn(string $ip) => $this->keyPrefix . 'banned:' . $ip,
            $bannedIps
        );

        if (!empty($keys)) {
            $this->memcached->deleteMulti($keys);
        }
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $key = $this->keyPrefix . 'rate:' . $ip;
        $existing = $this->memcached->get($key);

        $requests = [];
        if ($existing !== false) {
            $requests = is_array($existing) ? $existing : [];
        }

        $requests[] = time();
        $requests = array_filter($requests, fn(int $ts) => $ts > (time() - $ttlSeconds));

        $this->memcached->set($key, $requests, $ttlSeconds);
    }

    public function getRequestCount(string $ip): int
    {
        $key = $this->keyPrefix . 'rate:' . $ip;
        $existing = $this->memcached->get($key);

        if ($existing === false) {
            return 0;
        }

        return is_array($existing) ? count($existing) : 0;
    }

    /**
     * @return array<int, string>
     */
    private function getLogKeys(): array
    {
        $allKeys = $this->memcached->getAllKeys();
        if ($allKeys === false) {
            return [];
        }

        return array_filter(
            $allKeys,
            fn(string $key) => preg_match('/^' . preg_quote($this->keyPrefix, '/') . 'ip_log_/', $key)
        );
    }

    /**
     * @param array<int, string> $keys
     *
     * @return array<int, LogEntry>
     */
    private function fetchEntries(array $keys): array
    {
        $entries = [];
        foreach ($keys as $key) {
            $data = $this->memcached->get($key);
            if ($data === false) {
                continue;
            }

            $entry = $this->deserializeEntry($data);
            if ($entry !== null) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    private function matchesIp(string $data, string $ip): bool
    {
        $entry = $this->deserializeEntry($data);

        return $entry !== null && $entry->getIp() === $ip;
    }

    private function deserializeEntry(string $data): ?LogEntry
    {
        $decoded = unserialize($data, ['allowed_classes' => false]);
        if (!is_array($decoded) || !isset($decoded['ip'])) {
            return null;
        }

        return new LogEntry($decoded['ip'], $decoded['userAgent'] ?? null);
    }
}
