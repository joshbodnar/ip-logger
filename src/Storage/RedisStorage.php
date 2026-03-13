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

        $this->redis->setex($key, self::DEFAULT_TTL, serialize($data));
        $this->redis->sAdd($this->keyPrefix . 'ids', $key);
    }

    /**
     * @return array<int, LogEntry>
     *
     * PERFORMANCE NOTE: This method is efficient compared to naive key enumeration.
     * It uses a Redis Set (`{$this->keyPrefix}ids`) to track all log entry keys,
     * avoiding expensive KEYS pattern matching. This approach limits memory and
     * network overhead when retrieving many log entries.
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

    public function isBanned(string $ip): bool
    {
        return $this->redis->exists($this->keyPrefix . 'banned:' . $ip);
    }

    public function banIp(string $ip): void
    {
        $this->redis->setex($this->keyPrefix . 'banned:' . $ip, self::DEFAULT_TTL, '1');
    }

    public function unbanIp(string $ip): void
    {
        $this->redis->del($this->keyPrefix . 'banned:' . $ip);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        $keys = $this->redis->keys($this->keyPrefix . 'banned:*');
        if (empty($keys)) {
            return [];
        }

        return array_map(
            fn(string $key) => str_replace($this->keyPrefix . 'banned:', '', $key),
            $keys
        );
    }

    /**
     * Clear all banned IP addresses
     *
     * NOTE: This operation uses KEYS command to enumerate all banned IP keys,
     * which can be slow/expensive for Redis instances with many keys.
     * Additionally, there is a brief window where newly banned IPs may be
     * missed if added between the enumeration and deletion steps.
     */
    public function clearBans(): void
    {
        $keys = $this->redis->keys($this->keyPrefix . 'banned:*');
        if (!empty($keys)) {
            $this->redis->del($keys);
        }
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $this->redis->zAdd(
            $this->keyPrefix . 'rate_limit:' . $ip,
            time(),
            (string) time()
        );
        $this->redis->zRemRangeByScore(
            $this->keyPrefix . 'rate_limit:' . $ip,
            '0',
            (string) (time() - $ttlSeconds)
        );
    }

    public function getRequestCount(string $ip): int
    {
        // Count all entries in the sorted set, which should only contain entries within the TTL window
        // as old entries are removed in recordRequest
        return (int) $this->redis->zCount(
            $this->keyPrefix . 'rate_limit:' . $ip,
            '-inf',
            '+inf'
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

        $timestamp = null;
        if (isset($decoded['timestamp'])) {
            $parsed = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ISO8601, $decoded['timestamp']);
            if ($parsed !== false) {
                $timestamp = $parsed;
            }
        }

        return new LogEntry($decoded['ip'], $decoded['userAgent'] ?? null, $timestamp);
    }
}
