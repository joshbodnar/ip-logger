<?php

declare(strict_types=1);

namespace IpLogger;

use IpLogger\Client\ClientInfoInterface;
use IpLogger\Entity\LogEntry;
use IpLogger\Exception\InvalidIpException;
use IpLogger\Storage\StorageInterface;

final class IpLogger implements LoggerInterface
{
    private StorageInterface $storage;

    public function __construct(?StorageInterface $storage = null)
    {
        $this->storage = $storage ?? new InMemoryStorage();
    }

    public function log(string $ip, ?string $userAgent = null): LogEntry
    {
        $this->validateIp($ip);

        $entry = new LogEntry($ip, $userAgent);
        $this->storage->save($entry);

        return $entry;
    }

    public function logFromClientInfo(ClientInfoInterface $clientInfo): LogEntry
    {
        $ip = $clientInfo->getIp();
        if ($ip === null) {
            throw new InvalidIpException('Could not determine client IP address');
        }

        return $this->log($ip, $clientInfo->getUserAgent());
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getAll(): array
    {
        return $this->storage->getAll();
    }

    /**
     * @return array<int, LogEntry>
     */
    public function getByIp(string $ip): array
    {
        return $this->storage->getByIp($ip);
    }

    public function clear(): void
    {
        $this->storage->clear();
    }

    private function validateIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address: {$ip}");
        }
    }
}
