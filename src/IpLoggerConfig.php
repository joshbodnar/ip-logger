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
        $this->rateLimitMaxRequests = $rateLimitMaxRequests;

        return $this;
    }

    public function getRateLimitWindowSeconds(): int
    {
        return $this->rateLimitWindowSeconds;
    }

    public function setRateLimitWindowSeconds(int $rateLimitWindowSeconds): self
    {
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
