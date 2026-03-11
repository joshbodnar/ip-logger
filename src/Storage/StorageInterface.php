<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;

interface StorageInterface
{
    public function save(LogEntry $entry): void;

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array;

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array;

    public function clear(): void;

    public function isBanned(string $ip): bool;

    public function banIp(string $ip): void;

    public function unbanIp(string $ip): void;

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array;

    public function clearBans(): void;

    public function recordRequest(string $ip, int $ttlSeconds): void;

    public function getRequestCount(string $ip): int;
}
