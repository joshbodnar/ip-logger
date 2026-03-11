<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class FileStorage implements StorageInterface
{
    private string $filePath;

    private ?array $cache = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->ensureDirectoryExists();
    }

    public function save(LogEntry $entry): void
    {
        $entries = $this->readAll();
        $entries[] = $entry;
        $this->writeAll($entries);
        $this->cache = null;
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        return $this->readAll();
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        return array_values(
            array_filter(
                $this->readAll(),
                fn(LogEntry $entry) => $entry->getIp() === $ip
            )
        );
    }

    public function clear(): void
    {
        $this->writeAll([]);
        $this->cache = null;
    }

    /**
     * @return array<int, LogEntry>
     */
    private function readAll(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return [];
        }

        $entries = [];
        foreach ($data as $item) {
            $entry = new LogEntry($item['ip'], $item['userAgent'] ?? null);
            $entries[] = $entry;
        }

        $this->cache = $entries;

        return $entries;
    }

    /**
     * @param array<int, LogEntry> $entries
     */
    private function writeAll(array $entries): void
    {
        $data = array_map(
            fn(LogEntry $entry) => [
                'ip' => $entry->getIp(),
                'userAgent' => $entry->getUserAgent(),
                'timestamp' => $entry->getTimestamp()->format(\DateTimeInterface::ISO8601),
            ],
            $entries
        );

        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Failed to encode log entries');
        }

        if (file_put_contents($this->filePath, $json) === false) {
            throw new RuntimeException("Failed to write to log file: {$this->filePath}");
        }
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create log directory: {$dir}");
        }
    }
}
