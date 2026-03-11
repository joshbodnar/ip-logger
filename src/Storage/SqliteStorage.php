<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class SqliteStorage implements StorageInterface
{
    private \PDO $pdo;

    private string $tableName;

    private string $bannedTableName;

    private string $rateLimitTableName;

    public function __construct(
        \PDO $pdo,
        ?string $tableName = null,
        ?string $bannedTableName = null,
        ?string $rateLimitTableName = null
    ) {
        $this->pdo = $pdo;
        $this->tableName = $tableName ?? 'ip_logs';
        $this->bannedTableName = $bannedTableName ?? 'banned_ips';
        $this->rateLimitTableName = $rateLimitTableName ?? 'rate_limits';
        $this->initializeSchema();
    }

    public static function create(
        string $databasePath,
        ?string $tableName = null,
        ?string $bannedTableName = null,
        ?string $rateLimitTableName = null
    ): self {
        $pdo = new \PDO("sqlite:{$databasePath}", null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);

        return new self($pdo, $tableName, $bannedTableName, $rateLimitTableName);
    }

    public function save(LogEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->tableName} (ip, user_agent, timestamp) VALUES (:ip, :userAgent, :timestamp)"
        );

        $stmt->execute([
            'ip' => $entry->getIp(),
            'userAgent' => $entry->getUserAgent(),
            'timestamp' => $entry->getTimestamp()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query("SELECT ip, user_agent, timestamp FROM {$this->tableName} ORDER BY id DESC");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->rowsToEntries($rows);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT ip, user_agent, timestamp FROM {$this->tableName} WHERE ip = :ip ORDER BY id DESC"
        );
        $stmt->execute(['ip' => $ip]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->rowsToEntries($rows);
    }

    public function clear(): void
    {
        $this->pdo->exec("DELETE FROM {$this->tableName}");
    }

    public function isBanned(string $ip): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->bannedTableName} WHERE ip = :ip");
        $stmt->execute(['ip' => $ip]);

        return $stmt->fetch() !== false;
    }

    public function banIp(string $ip): void
    {
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO {$this->bannedTableName} (ip) VALUES (:ip)");
        $stmt->execute(['ip' => $ip]);
    }

    public function unbanIp(string $ip): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->bannedTableName} WHERE ip = :ip");
        $stmt->execute(['ip' => $ip]);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        $stmt = $this->pdo->query("SELECT ip FROM {$this->bannedTableName}");

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function clearBans(): void
    {
        $this->pdo->exec("DELETE FROM {$this->bannedTableName}");
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$this->rateLimitTableName} (ip, timestamp) VALUES (:ip, :timestamp)"
        );
        $stmt->execute([
            'ip' => $ip,
            'timestamp' => time(),
        ]);

        $stmt = $this->pdo->prepare(
            "DELETE FROM {$this->rateLimitTableName} WHERE ip = :ip AND timestamp < :cutoff"
        );
        $stmt->execute([
            'ip' => $ip,
            'cutoff' => time() - $ttlSeconds,
        ]);
    }

    public function getRequestCount(string $ip): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->rateLimitTableName} WHERE ip = :ip");
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                user_agent TEXT,
                timestamp DATETIME NOT NULL
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->bannedTableName} (
                ip TEXT PRIMARY KEY
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->rateLimitTableName} (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                timestamp INTEGER NOT NULL
            )
        ");
    }

    /**
     * @param array<int, array<string, string|null>> $rows
     *
     * @return array<int, LogEntry>
     */
    private function rowsToEntries(array $rows): array
    {
        $entries = [];
        foreach ($rows as $row) {
            $entry = new LogEntry($row['ip'], $row['user_agent'] ?? null);
            $entries[] = $entry;
        }

        return $entries;
    }
}
