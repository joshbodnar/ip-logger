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

    /** @var array<string, true> */
    private array $trustedProxyIps;

    private ?string $ip;

    private ?string $userAgent;

    /**
     * @param array<string> $trustedProxyIps List of trusted proxy IP addresses
     */
    public function __construct(ServerRequestInterface $request, array $trustedProxyIps = [])
    {
        $this->trustedProxyIps = array_fill_keys($trustedProxyIps, true);
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
            $result = $this->getIpFromHeader($request, $header);
            // If we got a specific IP, return it
            if ($result !== null) {
                return $result;
            }

            // If header exists but all IPs are trusted proxies, return null
            // rather than continuing to other headers or falling back to direct IP
            if ($request->getHeaderLine($header) !== '' && $this->allIpsAreTrustedInHeader($request, $header)) {
                return null;
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

        if (!empty($this->trustedProxyIps)) {
            $trustedIps = array_filter($ips, fn(string $ip) => $this->isTrustedProxyIp($ip));
            $untrustedIps = array_diff($ips, $trustedIps);

            // Return first untrusted IP if any exist
            if (!empty($untrustedIps)) {
                return reset($untrustedIps);
            }

            // All IPs are trusted - return null to indicate we can't identify client IP
            return null;
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
        if (empty($this->trustedProxyIps)) {
            return false;
        }

        return isset($this->trustedProxyIps[$ip]);
    }

    private function allIpsAreTrustedInHeader(ServerRequestInterface $request, string $header): bool
    {
        $value = $request->getHeaderLine($header);
        if ($value === '') {
            return false;
        }

        $ips = array_map('trim', explode(',', $value));
        $ips = array_filter($ips, fn(string $ip) => $this->isValidIp($ip));

        if (empty($ips)) {
            return false;
        }

        $trustedIps = array_filter($ips, fn(string $ip) => $this->isTrustedProxyIp($ip));
        return count($trustedIps) === count($ips) && !empty($this->trustedProxyIps);
    }
}
