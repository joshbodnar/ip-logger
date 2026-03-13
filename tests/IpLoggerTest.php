<?php

declare(strict_types=1);

namespace IpLogger\Tests;

use IpLogger\IpLogger;
use IpLogger\IpLoggerConfig;
use IpLogger\Client\ClientInfoInterface;
use IpLogger\Exception\InvalidIpException;
use IpLogger\Exception\IpBannedException;
use IpLogger\Exception\RateLimitExceededException;
use PHPUnit\Framework\TestCase;

final class IpLoggerTest extends TestCase
{
    private IpLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new IpLogger();
    }

    public function testLogValidIpv4Address(): void
    {
        $entry = $this->logger->log('192.168.1.1');

        $this->assertSame('192.168.1.1', $entry->getIp());
    }

    public function testLogValidIpv6Address(): void
    {
        $entry = $this->logger->log('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $entry->getIp());
    }

    public function testLogWithUserAgent(): void
    {
        $entry = $this->logger->log('192.168.1.1', 'Mozilla/5.0');

        $this->assertSame('Mozilla/5.0', $entry->getUserAgent());
    }

    public function testLogInvalidIpThrowsException(): void
    {
        $this->expectException(InvalidIpException::class);

        $this->logger->log('invalid-ip');
    }

    public function testGetAllReturnsAllEntries(): void
    {
        $this->logger->log('192.168.1.1');
        $this->logger->log('10.0.0.1');
        $this->logger->log('172.16.0.1');

        $entries = $this->logger->getAll();

        $this->assertCount(3, $entries);
    }

    public function testGetByIpFiltersEntries(): void
    {
        $this->logger->log('192.168.1.1');
        $this->logger->log('192.168.1.2');
        $this->logger->log('192.168.1.1');

        $entries = $this->logger->getByIp('192.168.1.1');

        $this->assertCount(2, $entries);
    }

    public function testClearRemovesAllEntries(): void
    {
        $this->logger->log('192.168.1.1');
        $this->logger->log('10.0.0.1');

        $this->logger->clear();

        $this->assertCount(0, $this->logger->getAll());
    }

    public function testLogFromClientInfo(): void
    {
        $clientInfo = $this->createMock(ClientInfoInterface::class);
        $clientInfo->method('getIp')->willReturn('192.168.1.1');
        $clientInfo->method('getUserAgent')->willReturn('Mozilla/5.0');

        $entry = $this->logger->logFromClientInfo($clientInfo);

        $this->assertSame('192.168.1.1', $entry->getIp());
        $this->assertSame('Mozilla/5.0', $entry->getUserAgent());
    }

    public function testLogFromClientInfoThrowsWhenNoIp(): void
    {
        $clientInfo = $this->createMock(ClientInfoInterface::class);
        $clientInfo->method('getIp')->willReturn(null);

        $this->expectException(InvalidIpException::class);
        $this->expectExceptionMessage('Could not determine client IP address');

        $this->logger->logFromClientInfo($clientInfo);
    }

    public function testBanIp(): void
    {
        $this->logger->banIp('192.168.1.100');

        $this->assertTrue($this->logger->isBanned('192.168.1.100'));
        $this->assertFalse($this->logger->isBanned('192.168.1.1'));
    }

    public function testUnbanIp(): void
    {
        $this->logger->banIp('192.168.1.100');
        $this->logger->unbanIp('192.168.1.100');

        $this->assertFalse($this->logger->isBanned('192.168.1.100'));
    }

    public function testGetBannedIps(): void
    {
        $this->logger->banIp('192.168.1.100');
        $this->logger->banIp('10.0.0.1');

        $banned = $this->logger->getBannedIps();

        $this->assertCount(2, $banned);
        $this->assertContains('192.168.1.100', $banned);
        $this->assertContains('10.0.0.1', $banned);
    }

    public function testBanPreventsLogging(): void
    {
        $config = (new IpLoggerConfig())->setBanEnabled(true);
        $logger = new IpLogger(null, $config);

        $logger->banIp('192.168.1.100');

        $this->expectException(IpBannedException::class);
        $this->expectExceptionMessage('IP address is banned');

        $logger->log('192.168.1.100');
    }

    public function testRateLimiting(): void
    {
        $config = (new IpLoggerConfig())
            ->setRateLimitEnabled(true)
            ->setRateLimitMaxRequests(2)
            ->setRateLimitWindowSeconds(60);

        $logger = new IpLogger(null, $config);

        $logger->log('192.168.1.1');
        $logger->log('192.168.1.1');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $logger->log('192.168.1.1');
    }

    public function testRateLimitExceptionContainsDetails(): void
    {
        $config = (new IpLoggerConfig())
            ->setRateLimitEnabled(true)
            ->setRateLimitMaxRequests(5)
            ->setRateLimitWindowSeconds(120);

        $logger = new IpLogger(null, $config);

        $logger->log('192.168.1.1');
        $logger->log('192.168.1.1');
        $logger->log('192.168.1.1');
        $logger->log('192.168.1.1');
        $logger->log('192.168.1.1');

        try {
            $logger->log('192.168.1.1');
        } catch (RateLimitExceededException $e) {
            $this->assertSame('192.168.1.1', $e->getIp());
            $this->assertSame(5, $e->getMaxRequests());
            $this->assertSame(120, $e->getWindowSeconds());
            return;
        }

        $this->fail('Expected RateLimitExceededException');
    }

    public function testIpBannedExceptionContainsIp(): void
    {
        $config = (new IpLoggerConfig())->setBanEnabled(true);
        $logger = new IpLogger(null, $config);

        $logger->banIp('10.0.0.1');

        try {
            $logger->log('10.0.0.1');
        } catch (IpBannedException $e) {
            $this->assertSame('10.0.0.1', $e->getIp());
            return;
        }

        $this->fail('Expected IpBannedException');
    }

    public function testConfigValidationRejectsNegativeMaxRequests(): void
    {
        $config = new IpLoggerConfig();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit max requests must be non-negative');
        
        $config->setRateLimitMaxRequests(-1);
    }

    public function testConfigValidationRejectsNegativeWindowSeconds(): void
    {
        $config = new IpLoggerConfig();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit window seconds must be positive');
        
        $config->setRateLimitWindowSeconds(-1);
    }

    public function testConfigValidationRejectsZeroWindowSeconds(): void
    {
        $config = new IpLoggerConfig();
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rate limit window seconds must be positive');
        
        $config->setRateLimitWindowSeconds(0);
    }

    public function testConfigValidationAllowsValidValues(): void
    {
        $config = new IpLoggerConfig();
        
        $config->setRateLimitMaxRequests(100);
        $config->setRateLimitWindowSeconds(60);
        
        $this->assertSame(100, $config->getRateLimitMaxRequests());
        $this->assertSame(60, $config->getRateLimitWindowSeconds());
    }
}
