<?php

declare(strict_types=1);

namespace IpLogger;

use IpLogger\Client\ClientInfoInterface;
use IpLogger\Entity\LogEntry;
use IpLogger\Exception\InvalidIpException;
use IpLogger\Exception\IpBannedException;
use IpLogger\Exception\RateLimitExceededException;
use IpLogger\Storage\StorageInterface;

final class IpLogger implements LoggerInterface
{
    private StorageInterface $storage;

    private IpLoggerConfig $config;

    public function __construct(?StorageInterface $storage = null, ?IpLoggerConfig $config = null)
    {
        $this->storage = $storage ?? new InMemoryStorage();
        $this->config = $config ?? new IpLoggerConfig();
    }

    public function log(string $ip, ?string $userAgent = null): LogEntry
    {
        $this->validateIp($ip);
        $this->checkBan($ip);
        $this->checkRateLimit($ip);

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

    public function isBanned(string $ip): bool
    {
        return $this->storage->isBanned($ip);
    }

    public function banIp(string $ip): void
    {
        $this->validateIp($ip);
        $this->storage->banIp($ip);
    }

    public function unbanIp(string $ip): void
    {
        $this->storage->unbanIp($ip);
    }

    /**
     * @return array<int, string>
     */
    public function getBannedIps(): array
    {
        return $this->storage->getBannedIps();
    }

    public function getConfig(): IpLoggerConfig
    {
        return $this->config;
    }

    public function setConfig(IpLoggerConfig $config): self
    {
        $this->config = $config;

        return $this;
    }

    private function checkBan(string $ip): void
    {
        if ($this->config->isBanEnabled() && $this->storage->isBanned($ip)) {
            throw new IpBannedException($ip);
        }
    }

    private function checkRateLimit(string $ip): void
    {
        if (!$this->config->isRateLimitEnabled()) {
            return;
        }

        $this->storage->recordRequest($ip, $this->config->getRateLimitWindowSeconds());
        $count = $this->storage->getRequestCount($ip);

        if ($count > $this->config->getRateLimitMaxRequests()) {
            throw new RateLimitExceededException(
                $ip,
                $this->config->getRateLimitMaxRequests(),
                $this->config->getRateLimitWindowSeconds()
            );
        }
    }

    private function validateIp(string $ip): void
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new InvalidIpException("Invalid IP address: {$ip}");
        }
    }
}
