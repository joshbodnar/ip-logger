<?php

declare(strict_types=1);

namespace IpLogger\Exception;

final class RateLimitExceededException extends \RuntimeException
{
    private string $ip;

    private int $maxRequests;

    private int $windowSeconds;

    public function __construct(string $ip, int $maxRequests, int $windowSeconds)
    {
        $this->ip = $ip;
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        parent::__construct(
            "Rate limit exceeded for IP: {$ip} ({$maxRequests} requests per {$windowSeconds} seconds)"
        );
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getMaxRequests(): int
    {
        return $this->maxRequests;
    }

    public function getWindowSeconds(): int
    {
        return $this->windowSeconds;
    }
}
