<?php

declare(strict_types=1);

namespace IpLogger\Storage;

use IpLogger\Entity\LogEntry;
use InvalidArgumentException;
use RuntimeException;

final class SqliteStorage implements StorageInterface
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->initializeSchema();
    }

    public static function create(string $databasePath): self
    {
        $pdo = new \PDO("sqlite:{$databasePath}", null, null, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
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
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->rowsToEntries($rows);
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        $stmt = $this->pdo->prepare('SELECT ip, user_agent, timestamp FROM ip_logs WHERE ip = :ip ORDER BY id DESC');
        $stmt->execute(['ip' => $ip]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->rowsToEntries($rows);
    }

    public function clear(): void
    {
        $this->pdo->exec('DELETE FROM ip_logs');
    }

    private function initializeSchema(): void
    {
        $this->pdo->exec('
            CREATE TABLE IF NOT EXISTS ip_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip TEXT NOT NULL,
                user_agent TEXT,
                timestamp DATETIME NOT NULL
            )
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
