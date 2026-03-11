<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class FileStorage implements StorageInterface
{
    private string $filePath;

    private string $metaFilePath;

    private ?array $cache = null;

    private ?array $metaCache = null;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->metaFilePath = $filePath . '.meta.json';
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

    public function isBanned(string $ip): bool
    {
        $meta = $this->readMeta();

        return isset($meta['banned'][$ip]);
    }

    public function banIp(string $ip): void
    {
        $meta = $this->readMeta();
        $meta['banned'][$ip] = true;
        $this->writeMeta($meta);
    }

    public function unbanIp(string $ip): void
    {
        $meta = $this->readMeta();
        unset($meta['banned'][$ip]);
        $this->writeMeta($meta);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        $meta = $this->readMeta();

        return array_keys($meta['banned'] ?? []);
    }

    public function clearBans(): void
    {
        $meta = $this->readMeta();
        $meta['banned'] = [];
        $this->writeMeta($meta);
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $meta = $this->readMeta();
        $now = time();

        if (!isset($meta['requests'][$ip])) {
            $meta['requests'][$ip] = [];
        }

        $meta['requests'][$ip][] = $now;

        $meta['requests'][$ip] = array_filter(
            $meta['requests'][$ip],
            fn(int $timestamp) => $timestamp > ($now - $ttlSeconds)
        );

        $this->writeMeta($meta);
    }

    public function getRequestCount(string $ip): int
    {
        $meta = $this->readMeta();

        return count($meta['requests'][$ip] ?? []);
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

    private function readMeta(): array
    {
        if ($this->metaCache !== null) {
            return $this->metaCache;
        }

        if (!file_exists($this->metaFilePath)) {
            return ['banned' => [], 'requests' => []];
        }

        $content = file_get_contents($this->metaFilePath);
        if ($content === false || $content === '') {
            return ['banned' => [], 'requests' => []];
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return ['banned' => [], 'requests' => []];
        }

        $this->metaCache = $data;

        return $data;
    }

    private function writeMeta(array $meta): void
    {
        $json = json_encode($meta, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Failed to encode metadata');
        }

        if (file_put_contents($this->metaFilePath, $json) === false) {
            throw new RuntimeException("Failed to write metadata file: {$this->metaFilePath}");
        }

        $this->metaCache = null;
    }

    private function ensureDirectoryExists(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException("Cannot create log directory: {$dir}");
        }
    }
}
