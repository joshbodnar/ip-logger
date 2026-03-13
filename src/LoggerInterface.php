<?php

declare(strict_types=1);

namespace IpLogger;

use IpLogger\Entity\LogEntry;
use IpLogger\Client\ClientInfoInterface;

interface LoggerInterface
{
    public function log(string $ip, ?string $userAgent = null): LogEntry;

    public function logFromClientInfo(ClientInfoInterface $clientInfo): LogEntry;

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
}
