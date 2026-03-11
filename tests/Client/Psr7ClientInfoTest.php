<?php

declare(strict_types=1);

namespace IpLogger\Tests\Client;

use IpLogger\Client\Psr7ClientInfo;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

final class Psr7ClientInfoTest extends TestCase
{
    public function testGetDirectIp(): void
    {
        $request = $this->createMockRequest([
            'REMOTE_ADDR' => '192.168.1.1',
        ]);

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('192.168.1.1', $clientInfo->getIp());
    }

    public function testGetIpv6(): void
    {
        $request = $this->createMockRequest([
            'REMOTE_ADDR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334',
        ]);

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $clientInfo->getIp());
    }

    public function testGetIpFromXForwardedFor(): void
    {
        $request = $this->createMockRequest(
            ['REMOTE_ADDR' => '127.0.0.1'],
            ['X-Forwarded-For' => '10.0.0.1, 192.168.1.1']
        );

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('10.0.0.1', $clientInfo->getIp());
    }

    public function testGetIpFromXRealIp(): void
    {
        $request = $this->createMockRequest(
            ['REMOTE_ADDR' => '127.0.0.1'],
            ['X-Real-IP' => '10.0.0.1']
        );

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('10.0.0.1', $clientInfo->getIp());
    }

    public function testGetUserAgent(): void
    {
        $request = $this->createMockRequest(
            ['REMOTE_ADDR' => '192.168.1.1'],
            ['User-Agent' => 'Mozilla/5.0']
        );

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('Mozilla/5.0', $clientInfo->getUserAgent());
    }

    public function testReturnsNullWhenNoIp(): void
    {
        $request = $this->createMockRequest([]);

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertNull($clientInfo->getIp());
    }

    public function testReturnsNullForInvalidIpInHeader(): void
    {
        $request = $this->createMockRequest(
            ['REMOTE_ADDR' => '192.168.1.1'],
            ['X-Forwarded-For' => 'invalid-ip']
        );

        $clientInfo = new Psr7ClientInfo($request);

        $this->assertSame('192.168.1.1', $clientInfo->getIp());
    }

    private function createMockRequest(array $serverParams, array $headers = []): ServerRequestInterface
    {
        $request = $this->createMock(ServerRequestInterface::class);
        
        $request->method('getServerParams')->willReturn($serverParams);
        $request->method('getHeaderLine')->willReturnMap([
            ['X-Forwarded-For', $headers['X-Forwarded-For'] ?? ''],
            ['X-Real-IP', $headers['X-Real-IP'] ?? ''],
            ['Client-IP', $headers['Client-IP'] ?? ''],
            ['X-Cluster-Client-IP', $headers['X-Cluster-Client-IP'] ?? ''],
            ['User-Agent', $headers['User-Agent'] ?? ''],
        ]);

        return $request;
    }
}
