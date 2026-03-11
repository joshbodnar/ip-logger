<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class MySqlStorage implements StorageInterface
{
    private \PDO $pdo;

    private string $tableName;

    private string $bannedTableName;

    private string $rateLimitTableName;

    public function __construct(\PDO $pdo, ?string $tableName = null)
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName ?? 'ip_logs';
        $this->bannedTableName = 'banned_ips';
        $this->rateLimitTableName = 'rate_limits';
        $this->initializeSchema();
    }

    public static function create(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306,
        ?string $tableName = null
    ): self {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return new self($pdo, $tableName);
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

        return $this->rowsToEntries($stmt->fetchAll());
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

        return $this->rowsToEntries($stmt->fetchAll());
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
        $stmt = $this->pdo->prepare("INSERT IGNORE INTO {$this->bannedTableName} (ip) VALUES (:ip)");
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
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255),
                timestamp DATETIME NOT NULL,
                INDEX idx_ip (ip),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->bannedTableName} (
                ip VARCHAR(45) PRIMARY KEY
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->rateLimitTableName} (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                timestamp INT NOT NULL,
                INDEX idx_ip (ip),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
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
