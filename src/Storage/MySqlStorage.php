<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use RuntimeException;

final class MySqlStorage implements StorageInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeSchema();
    }

    public static function create(
        string $host,
        string $database,
        string $username,
        string $password,
        int $port = 3306
    ): self {
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

        $pdo = new \PDO($dsn, $username, $password, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return new self($pdo);
    }

    public function save(LogEntry $entry): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO ip_logs (ip, user_agent, timestamp) VALUES (:ip, :userAgent, :timestamp)'
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
        $stmt = $this->pdo->query('SELECT ip, user_agent, timestamp FROM ip_logs ORDER BY id DESC');
        $rows = $stmt->fetchAll();

        return $this->rowsToEntries($rows);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        $stmt = $this->pdo->prepare('SELECT ip, user_agent, timestamp FROM ip_logs WHERE ip = :ip ORDER BY id DESC');
        $stmt->execute(['ip' => $ip]);
        $rows = $stmt->fetchAll();

        return $this->rowsToEntries($rows);
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM ip_logs');
    }

    public function isBanned(string $ip): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM banned_ips WHERE ip = :ip');
        $stmt->execute(['ip' => $ip]);

        return $stmt->fetch() !== false;
    }

    public function banIp(string $ip): void
    {
        $stmt = $this->pdo->prepare('INSERT IGNORE INTO banned_ips (ip) VALUES (:ip)');
        $stmt->execute(['ip' => $ip]);
    }

    public function unbanIp(string $ip): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM banned_ips WHERE ip = :ip');
        $stmt->execute(['ip' => $ip]);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        $stmt = $this->pdo->query('SELECT ip FROM banned_ips');
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return $rows;
    }

    public function clearBans(): void
    {
        $this->pdo->exec('DELETE FROM banned_ips');
    }

    public function recordRequest(string $ip, int $ttlSeconds): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rate_limits (ip, timestamp) VALUES (:ip, :timestamp)'
        );
        $stmt->execute([
            'ip' => $ip,
            'timestamp' => time(),
        ]);

        $stmt = $this->pdo->prepare(
            'DELETE FROM rate_limits WHERE ip = :ip AND timestamp < :cutoff'
        );
        $stmt->execute([
            'ip' => $ip,
            'cutoff' => time() - $ttlSeconds,
        ]);
    }

    public function getRequestCount(string $ip): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rate_limits WHERE ip = :ip');
        $stmt->execute(['ip' => $ip]);

        return (int) $stmt->fetchColumn();
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS ip_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255),
                timestamp DATETIME NOT NULL,
                INDEX idx_ip (ip),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS banned_ips (
                ip VARCHAR(45) PRIMARY KEY
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');

        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS rate_limits (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                ip VARCHAR(45) NOT NULL,
                timestamp INT NOT NULL,
                INDEX idx_ip (ip),
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ');
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
