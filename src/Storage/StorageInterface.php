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
}
