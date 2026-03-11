<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class MemcachedStorage implements StorageInterface
{
    private \Memcached $memcached;

    private string $keyPrefix;

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

        $this->memcached->set($key, $serialized, 2592000);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        $keys = $this->memcached->getAllKeys();
        if ($keys === false) {
            return [];
        }

        $matchingKeys = array_filter(
            $keys,
            fn(string $key) => str_starts_with($key, $this->keyPrefix)
        );

        if (empty($matchingKeys)) {
            return [];
        }

        return $this->fetchEntries(array_values($matchingKeys));
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        $allKeys = $this->memcached->getAllKeys();
        if ($allKeys === false) {
            return [];
        }

        $matchingKeys = [];
        foreach ($allKeys as $key) {
            if (!str_starts_with($key, $this->keyPrefix)) {
                continue;
            }

            $data = $this->memcached->get($key);
            if ($data !== false && $this->matchesIp($data, $ip)) {
                $matchingKeys[] = $key;
            }
        }

        return $this->fetchEntries($matchingKeys);
    }

    public function clear(): void
    {
        $keys = $this->memcached->getAllKeys();
        if ($keys === false) {
            return;
        }

        $matchingKeys = array_filter(
            $keys,
            fn(string $key) => str_starts_with($key, $this->keyPrefix)
        );

        if (!empty($matchingKeys)) {
            $this->memcached->deleteMulti($matchingKeys);
        }
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
