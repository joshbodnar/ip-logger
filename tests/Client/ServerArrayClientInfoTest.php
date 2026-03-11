<?php

declare(strict_types=1);

namespace IpLogger\Tests\Client;

use IpLogger\Client\ServerArrayClientInfo;
use PHPUnit\Framework\TestCase;

final class ServerArrayClientInfoTest extends TestCase
{
    public function testGetDirectIp(): void
    {
        $server = ['REMOTE_ADDR' => '192.168.1.1'];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('192.168.1.1', $clientInfo->getIp());
    }

    public function testGetIpv6(): void
    {
        $server = ['REMOTE_ADDR' => '2001:0db8:85a3:0000:0000:8a2e:0370:7334'];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $clientInfo->getIp());
    }

    public function testGetIpFromXForwardedFor(): void
    {
        $server = [
            'HTTP_X_FORWARDED_FOR' => '10.0.0.1, 192.168.1.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('10.0.0.1', $clientInfo->getIp());
    }

    public function testGetIpFromXRealIp(): void
    {
        $server = [
            'HTTP_X_REAL_IP' => '10.0.0.1',
            'REMOTE_ADDR' => '127.0.0.1',
        ];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('10.0.0.1', $clientInfo->getIp());
    }

    public function testGetUserAgent(): void
    {
        $server = [
            'REMOTE_ADDR' => '192.168.1.1',
            'HTTP_USER_AGENT' => 'Mozilla/5.0',
        ];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('Mozilla/5.0', $clientInfo->getUserAgent());
    }

    public function testReturnsNullWhenNoIp(): void
    {
        $server = [];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertNull($clientInfo->getIp());
    }

    public function testReturnsNullForInvalidIpInHeader(): void
    {
        $server = [
            'HTTP_X_FORWARDED_FOR' => 'invalid-ip',
            'REMOTE_ADDR' => '192.168.1.1',
        ];
        $clientInfo = new ServerArrayClientInfo($server);

        $this->assertSame('192.168.1.1', $clientInfo->getIp());
    }

    public function testDefaultsToServerGlobal(): void
    {
        $clientInfo = new ServerArrayClientInfo();

        $this->assertNull($clientInfo->getIp());
    }
}
