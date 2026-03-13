<?php

declare(strict_types=1);

namespace IpLogger;

final class IpLoggerConfig
{
    private bool $banEnabled = false;

    private int $rateLimitMaxRequests = 0;

    private int $rateLimitWindowSeconds = 60;

    private bool $rateLimitEnabled = false;

    public function isBanEnabled(): bool
    {
        return $this->banEnabled;
    }

    public function setBanEnabled(bool $banEnabled): self
    {
        $this->banEnabled = $banEnabled;

        return $this;
    }

    public function getRateLimitMaxRequests(): int
    {
        return $this->rateLimitMaxRequests;
    }

    public function setRateLimitMaxRequests(int $rateLimitMaxRequests): self
    {
        if ($rateLimitMaxRequests < 0) {
            throw new \InvalidArgumentException('Rate limit max requests must be non-negative');
        }

        if ($rateLimitMaxRequests > 1000000) {
            throw new \InvalidArgumentException('Rate limit max requests must be less than 1,000,000');
        }

        $this->rateLimitMaxRequests = $rateLimitMaxRequests;

        return $this;
    }

    public function getRateLimitWindowSeconds(): int
    {
        return $this->rateLimitWindowSeconds;
    }

    public function setRateLimitWindowSeconds(int $rateLimitWindowSeconds): self
    {
        if ($rateLimitWindowSeconds <= 0) {
            throw new \InvalidArgumentException('Rate limit window seconds must be positive');
        }

        // Maximum of 1 year in seconds
        if ($rateLimitWindowSeconds > 31536000) {
            throw new \InvalidArgumentException(
                'Rate limit window seconds must be less than 1 year (31536000 seconds)'
            );
        }

        $this->rateLimitWindowSeconds = $rateLimitWindowSeconds;

        return $this;
    }

    public function isRateLimitEnabled(): bool
    {
        return $this->rateLimitEnabled;
    }

    public function setRateLimitEnabled(bool $rateLimitEnabled): self
    {
        $this->rateLimitEnabled = $rateLimitEnabled;

        return $this;
    }
}
