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

    /** @var array<string, true> */
    private array $trustedProxyIps;

    private array $server;

    public function __construct(?array $server = null, array $trustedProxyIps = [])
    {
        $this->server = $server ?? $_SERVER;
        $this->trustedProxyIps = array_fill_keys($trustedProxyIps, true);
    }

    public function getIp(): ?string
    {
        foreach (self::PROXY_HEADERS as $header) {
            $result = $this->getIpFromHeader($header);
            // If we got a specific IP, return it
            if ($result !== null) {
                return $result;
            }

            // If header exists but all IPs are trusted proxies, return null
            // rather than continuing to other headers or falling back to direct IP
            if (isset($this->server[$header]) && $this->allIpsAreTrustedInHeader($header)) {
                return null;
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

        $ips = array_map('trim', explode(',', $this->server[$header]));
        $ips = array_filter($ips, fn(string $ip) => $this->isValidIp($ip));

        if (empty($ips)) {
            return null;
        }

        if (!empty($this->trustedProxyIps)) {
            $trustedIps = array_filter($ips, fn(string $ip) => $this->isTrustedProxyIp($ip));
            $untrustedIps = array_diff($ips, $trustedIps);

            // Return first untrusted IP if any exist
            if (!empty($untrustedIps)) {
                return reset($untrustedIps);
            }

            // All IPs are trusted - return null to indicate we can't identify client IP
            // The higher level logic will catch this case and return null
            return null;
        }

        return reset($ips);
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
        if (empty($this->trustedProxyIps)) {
            return false;
        }

        return isset($this->trustedProxyIps[$ip]);
    }

    private function allIpsAreTrustedInHeader(string $header): bool
    {
        if (!isset($this->server[$header])) {
            return false;
        }

        $ips = array_map('trim', explode(',', $this->server[$header]));
        $ips = array_filter($ips, fn(string $ip) => $this->isValidIp($ip));

        if (empty($ips)) {
            return false;
        }

        $trustedIps = array_filter($ips, fn(string $ip) => $this->isTrustedProxyIp($ip));
        return count($trustedIps) === count($ips) && !empty($this->trustedProxyIps);
    }
}
