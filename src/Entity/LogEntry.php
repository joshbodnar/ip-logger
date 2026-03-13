<?php

declare(strict_types=1);

namespace IpLogger\Entity;

final class LogEntry
{
    private string $ip;

    private ?string $userAgent;

    private \DateTimeImmutable $timestamp;

    public function __construct(string $ip, ?string $userAgent = null, ?\DateTimeImmutable $timestamp = null)
    {
        $this->ip = $ip;
        $this->userAgent = $userAgent;
        $this->timestamp = $timestamp ?? new \DateTimeImmutable();
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }
}
