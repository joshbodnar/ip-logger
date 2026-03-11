<?php

declare(strict_types=1);

namespace IpLogger\Exception;

final class IpBannedException extends \RuntimeException
{
    private string $ip;

    public function __construct(string $ip)
    {
        $this->ip = $ip;

        parent::__construct("IP address is banned: {$ip}");
    }

    public function getIp(): string
    {
        return $this->ip;
    }
}
