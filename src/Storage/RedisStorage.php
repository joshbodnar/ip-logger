<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class RedisStorage implements StorageInterface
{
    private \Redis $redis;

    private string $keyPrefix;

    private const DEFAULT_TTL = 2592000;

    public function __construct(\Redis $redis, string $keyPrefix = 'ip_logger:')
    {
        $this->redis = $redis;
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

        $this->redis->setex($key, self::DEFAULT_TTL, $serialized);
        $this->redis->sAdd($this->keyPrefix . 'ids', $key);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        $keys = $this->redis->sMembers($this->keyPrefix . 'ids');
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
        $allKeys = $this->redis->sMembers($this->keyPrefix . 'ids');
        if (empty($allKeys)) {
            return [];
        }

        $matchingKeys = array_filter(
            $allKeys,
            fn(string $key) => $this->redis->exists($key) &&
                $this->matchesIp($this->redis->get($key), $ip)
        );

        return $this->fetchEntries(array_values($matchingKeys));
    }

    public function clear(): void
    {
        $keys = $this->redis->sMembers($this->keyPrefix . 'ids');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
        $this->redis->del($this->keyPrefix . 'ids');
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
            $data = $this->redis->get($key);
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

    private function matchesIp(?string $data, string $ip): bool
    {
        if ($data === false || $data === null) {
            return false;
        }

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
