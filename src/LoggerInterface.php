<?php

declare(strict_types=1);

namespace IpLogger;

use IpLogger\Entity\LogEntry;

interface LoggerInterface
{
    public function log(string $ip, ?string $userAgent = null): LogEntry;

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array;

    public function getByIp(string $ip): array;

    public function clear(): void;
}
