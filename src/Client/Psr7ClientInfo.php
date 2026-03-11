<?php

declare(strict_types=1);

namespace IpLogger\Client;

use Psr\Http\Message\ServerRequestInterface;

final class Psr7ClientInfo implements ClientInfoInterface
{
    private const PROXY_HEADERS = [
        'X-Forwarded-For',
        'X-Real-IP',
        'Client-IP',
        'X-Cluster-Client-IP',
    ];

    private const TRUSTED_PROXY_IPS = [];

    private ?string $ip;

    private ?string $userAgent;

    public function __construct(ServerRequestInterface $request)
    {
        $this->ip = $this->extractIp($request);
        $this->userAgent = $request->getHeaderLine('User-Agent') ?: null;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    private function extractIp(ServerRequestInterface $request): ?string
    {
        foreach (self::PROXY_HEADERS as $header) {
            $ip = $this->getIpFromHeader($request, $header);
            if ($ip !== null) {
                return $ip;
            }
        }

        return $this->getServerIp($request);
    }

    private function getIpFromHeader(ServerRequestInterface $request, string $header): ?string
    {
        $value = $request->getHeaderLine($header);
        if ($value === '') {
            return null;
        }

        $ips = explode(',', $value);
        $ip = trim($ips[0]);

        if ($this->isValidIp($ip)) {
            if ($this->isTrustedProxyIp($ip)) {
                return $this->getServerIp($request);
            }

            return $ip;
        }

        return null;
    }

    private function getServerIp(ServerRequestInterface $request): ?string
    {
        $serverParams = $request->getServerParams();
        $keys = ['REMOTE_ADDR', 'HTTP_REMOTE_ADDR'];

        foreach ($keys as $key) {
            if (isset($serverParams[$key]) && $this->isValidIp($serverParams[$key])) {
                return $serverParams[$key];
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
