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
}
