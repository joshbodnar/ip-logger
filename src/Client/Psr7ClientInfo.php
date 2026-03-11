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

        $ips = array_map('trim', explode(',', $value));
        $ips = array_filter($ips, fn(string $ip) => $this->isValidIp($ip));

        if (empty($ips)) {
            return null;
        }

        if (!empty(self::TRUSTED_PROXY_IPS)) {
            $trustedIps = array_filter($ips, fn(string $ip) => $this->isTrustedProxyIp($ip));

            if (count($trustedIps) === count($ips)) {
                return null;
            }

            $untrustedIps = array_diff($ips, $trustedIps);

            return reset($untrustedIps);
        }

        return reset($ips);
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
