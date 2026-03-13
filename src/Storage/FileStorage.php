<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class FileStorage implements StorageInterface
{
    private string $filePath;

    private string $metaFilePath;

    private int $maxFileSizeBytes;

    private int $maxFiles;

    private ?array $metaCache = null;

    public function __construct(
        string $filePath,
        ?int $maxFileSizeBytes = null,
        ?int $maxFiles = null
    ) {
        $this->filePath = $filePath;
        $this->metaFilePath = $filePath . '.meta.json';
        $this->maxFileSizeBytes = $maxFileSizeBytes ?? 10 * 1024 * 1024;
        $this->maxFiles = $maxFiles ?? 5;
        $this->ensureDirectoryExists();
    }

    public function save(LogEntry $entry): void
    {
        $this->checkRotation();

        $data = json_encode([
            'ip' => $entry->getIp(),
            'userAgent' => $entry->getUserAgent(),
            'timestamp' => $entry->getTimestamp()->format(\DateTimeInterface::ISO8601),
        ]);

        if ($data === false) {
            throw new RuntimeException('Failed to encode log entry');
        }

        $line = $data . "\n";

        $fp = fopen($this->filePath, 'a');
        if ($fp === false) {
            throw new RuntimeException("Failed to open log file: {$this->filePath}");
        }

        try {
            if (flock($fp, LOCK_EX) === false) {
                throw new RuntimeException('Failed to acquire file lock');
            }

            fwrite($fp, $line);

            flock($fp, LOCK_UN);
        } finally {
            fclose($fp);
        }
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        $entries = [];

        $pattern = preg_replace('/\.json$/', '*.json', $this->filePath);
        $files = glob($pattern);

        if ($files === false) {
            return [];
        }

        sort($files);

        foreach ($files as $file) {
            $entries = array_merge($entries, $this->readFile($file));
        }

        return $entries;
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        return array_filter(
            $this->getAll(),
            fn(LogEntry $entry) => $entry->getIp() === $ip
        );
    }

    public function clear(): void
    {
        $pattern = preg_replace('/\.json$/', '*.json', $this->filePath);
        $files = glob($pattern);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            unlink($file);
        }
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
    private function readFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }

        $content = file_get_contents($filePath);
        if ($content === false || $content === '') {
            return [];
        }

        $entries = [];
        foreach (explode("\n", trim($content)) as $line) {
            if ($line === '') {
                continue;
            }

            $data = json_decode($line, true);
            if (!is_array($data) || !isset($data['ip'])) {
                continue;
            }

            $entries[] = new LogEntry($data['ip'], $data['userAgent'] ?? null);
        }

        return $entries;
    }

    private function checkRotation(): void
    {
        if (!file_exists($this->filePath)) {
            return;
        }

        $size = filesize($this->filePath);
        if ($size === false || $size < $this->maxFileSizeBytes) {
            return;
        }

        $this->rotateFile();
    }

    private function rotateFile(): void
    {
        $basePath = preg_replace('/\.json$/', '', $this->filePath);
        $timestamp = date('Y-m-d-His');

        $rotatedPath = "{$basePath}.{$timestamp}.json";

        rename($this->filePath, $rotatedPath);

        $this->cleanOldFiles();
    }

    private function cleanOldFiles(): void
    {
        $pattern = preg_replace('/\.json$/', '.*.json', $this->filePath);
        $files = glob($pattern);

        if ($files === false || count($files) <= $this->maxFiles) {
            return;
        }

        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $toDelete = array_slice($files, $this->maxFiles);

        foreach ($toDelete as $file) {
            unlink($file);
        }
    }

    private function readMeta(): array
    {
        if ($this->metaCache !== null) {
            return $this->metaCache;
        }

        // Use file locking to prevent race conditions
        $lockFile = $this->metaFilePath . '.lock';
        $lockHandle = fopen($lockFile, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException("Failed to open lock file: {$lockFile}");
        }

        // Acquire exclusive lock to ensure atomic read
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new RuntimeException("Failed to acquire lock for reading metadata");
        }

        try {
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
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
        }
    }

    private function writeMeta(array $meta): void
    {
        $json = json_encode($meta, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException('Failed to encode metadata');
        }

        // Use file locking to prevent race conditions
        $lockFile = $this->metaFilePath . '.lock';
        $lockHandle = fopen($lockFile, 'c+');
        if ($lockHandle === false) {
            throw new RuntimeException("Failed to open lock file: {$lockFile}");
        }

        // Acquire exclusive lock to ensure atomic write
        if (!flock($lockHandle, LOCK_EX)) {
            fclose($lockHandle);
            throw new RuntimeException("Failed to acquire lock for writing metadata");
        }

        try {
            // Ensure directory exists before writing
            $this->ensureDirectoryExists();

            if (file_put_contents($this->metaFilePath, $json, LOCK_EX) === false) {
                throw new RuntimeException("Failed to write metadata file: {$this->metaFilePath}");
            }
        } finally {
            flock($lockHandle, LOCK_UN);
            fclose($lockHandle);
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
