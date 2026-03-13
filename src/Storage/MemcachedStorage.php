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

        $this->memcached->set($key, serialize($data), self::DEFAULT_TTL);
    }

    /**
     * @return array<int, LogEntry>
     *
     * WARNING: This operation uses Memcached::getAllKeys() which retrieves
     * ALL keys in the Memcached instance. This can be very slow/expensive
     * when the Memcached instance contains many keys (>100000).
     *
     * For better performance, consider:
     * 1. Using a dedicated Memcached instance for IP logging
     * 2. Restricting the Memcached instance to only this application's keys
     * 3. Limiting the number of log entries stored in Memcached
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
     * Retrieves list of banned IP addresses
     *
     * WARNING: This operation uses Memcached::getAllKeys() which retrieves
     * ALL keys in the Memcached instance. This can be very slow/expensive
     * when the Memcached instance contains many keys (>100000).
     *
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        // WARNING: getAllKeys() retrieves ALL keys in Memcached, which can be expensive
        // on servers with many keys. Consider using a dedicated Memcached instance
        // or restricting access to a specific namespace/slab for better performance.
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

    /**
     * Clear all banned IP addresses
     *
     * NOTE: This operation enumerates keys matching the banned IP pattern,
     * which can be slow/expensive. Additionally, there is a brief window
     * during which newly banned IPs may be missed if added between the
     * enumeration and deletion steps.
     *
     * For applications requiring atomic ban list clearing, consider using
     * RedisStorage instead which has better atomic operation support.
     */
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

    /**
     * Record a request for rate limiting
     *
     * NOTE: This operation is eventually consistent rather than strictly atomic.
     * Under high concurrent load, request counts may occasionally be slightly
     * inaccurate due to read-modify-write race conditions. For applications
     * requiring strict rate limiting accuracy, consider using RedisStorage instead.
     *
     * @param string $ip IP address making the request
     * @param int $ttlSeconds Time-to-live for tracking requests
     */
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
     * Retrieve log entry keys by enumerating all Memcached keys
     *
     * WARNING: This uses getAllKeys() which retrieves ALL keys in Memcached,
     * which can be very slow/expensive when Memcached contains many keys.
     * This method should only be used on dedicated Memcached instances or
     * when key namespaces are properly isolated.
     *
     * @return array<int, string>
     */
    private function getLogKeys(): array
    {
        // WARNING: getAllKeys() retrieves ALL keys in Memcached, which can be expensive
        // on servers with many keys. Consider using a dedicated Memcached instance
        // or restricting access to a specific namespace/slab for better performance.
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
