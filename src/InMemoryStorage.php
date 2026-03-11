<?php

declare(strict_types=1);

namespace IpLogger;

use IpLogger\Entity\LogEntry;
use IpLogger\Storage\StorageInterface;

final class InMemoryStorage implements StorageInterface
{
    /**
     * @var array<int, LogEntry>
     */
    private array $entries = [];

    /**
     * @var array<string, true>
     */
    private array $bannedIps = [];

    /**
     * @var array<string, array<int, int>>
     */
    private array $requestCounts = [];

    public function save(LogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        return $this->entries;
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        return array_values(
            array_filter(
                $this->entries,
                fn(LogEntry $entry) => $entry->getIp() === $ip
            )
        );
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function isBanned(string $ip): bool
    {
        return isset($this->bannedIps[$ip]);
    }

    public function banIp(string $ip): void
    {
        $this->bannedIps[$ip] = true;
    }

    public function unbanIp(string $ip): void
    {
        unset($this->bannedIps[$ip]);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        return array_keys($this->bannedIps);
    }

    public function clearBans(): void
    {
        $this->bannedIps = [];
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $now = time();

        if (!isset($this->requestCounts[$ip])) {
            $this->requestCounts[$ip] = [];
        }

        $this->requestCounts[$ip][] = $now;

        $this->requestCounts[$ip] = array_filter(
            $this->requestCounts[$ip],
            fn(int $timestamp) => $timestamp > ($now - $ttlSeconds)
        );
    }

    public function getRequestCount(string $ip): int
    {
        return count($this->requestCounts[$ip] ?? []);
    }
}
