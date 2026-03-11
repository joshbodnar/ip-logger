<?php

declare(strict_types=1);

namespace IpLogger\Client;

final class ServerArrayClientInfo implements ClientInfoInterface
{
    private const PROXY_HEADERS = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_CLUSTER_CLIENT_IP',
    ];

    private const TRUSTED_PROXY_IPS = [];

    private array $server;

    public function __construct(?array $server = null)
    {
        $this->server = $server ?? $_SERVER;
    }

    public function getIp(): ?string
    {
        foreach (self::PROXY_HEADERS as $header) {
            $ip = $this->getIpFromHeader($header);
            if ($ip !== null) {
                return $ip;
            }
        }

        return $this->getDirectIp();
    }

    public function getUserAgent(): ?string
    {
        return $this->server['HTTP_USER_AGENT'] ?? null;
    }

    private function getIpFromHeader(string $header): ?string
    {
        if (!isset($this->server[$header])) {
            return null;
        }

        $ips = explode(',', $this->server[$header]);
        $ip = trim($ips[0]);

        if ($this->isValidIp($ip)) {
            if ($this->isTrustedProxyIp($ip)) {
                return $this->getDirectIp();
            }

            return $ip;
        }

        return null;
    }

    private function getDirectIp(): ?string
    {
        $keys = ['REMOTE_ADDR', 'HTTP_REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (isset($this->server[$key]) && $this->isValidIp($this->server[$key])) {
                return $this->server[$key];
            }
        }

        return null;
    }

    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    private function isTrustedProxyIp(string $ip): bool
    {
        if (empty(self::TRUSTED_PROXY_IPS)) {
            return false;
        }

        return in_array($ip, self::TRUSTED_PROXY_IPS, true);
    }
}
