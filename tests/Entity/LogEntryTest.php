<?php

declare(strict_types=1);

namespace IpLogger\Tests\Entity;

use IpLogger\Entity\LogEntry;
use PHPUnit\Framework\TestCase;

final class LogEntryTest extends TestCase
{
    public function testCreateLogEntryWithIpOnly(): void
    {
        $entry = new LogEntry('192.168.1.1');

        $this->assertSame('192.168.1.1', $entry->getIp());
        $this->assertNull($entry->getUserAgent());
        $this->assertInstanceOf(\DateTimeImmutable::class, $entry->getTimestamp());
    }

    public function testCreateLogEntryWithUserAgent(): void
    {
        $entry = new LogEntry('192.168.1.1', 'Mozilla/5.0');

        $this->assertSame('192.168.1.1', $entry->getIp());
        $this->assertSame('Mozilla/5.0', $entry->getUserAgent());
    }

    public function testCreateLogEntryWithIpv6(): void
    {
        $entry = new LogEntry('2001:0db8:85a3:0000:0000:8a2e:0370:7334');

        $this->assertSame('2001:0db8:85a3:0000:0000:8a2e:0370:7334', $entry->getIp());
    }
}
