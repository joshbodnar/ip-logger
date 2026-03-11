<?php

declare(strict_types=1);

namespace IpLogger\Tests\Storage;

use IpLogger\Storage\FileStorage;
use IpLogger\Entity\LogEntry;
use PHPUnit\Framework\TestCase;

final class FileStorageTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'ip_log_');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testSaveAndGetAll(): void
    {
        $storage = new FileStorage($this->tempFile);

        $entry1 = new LogEntry('192.168.1.1');
        $entry2 = new LogEntry('10.0.0.1');

        $storage->save($entry1);
        $storage->save($entry2);

        $entries = $storage->getAll();

        $this->assertCount(2, $entries);
    }

    public function testGetByIp(): void
    {
        $storage = new FileStorage($this->tempFile);

        $storage->save(new LogEntry('192.168.1.1'));
        $storage->save(new LogEntry('192.168.1.2'));
        $storage->save(new LogEntry('192.168.1.1'));

        $entries = $storage->getByIp('192.168.1.1');

        $this->assertCount(2, $entries);
    }

    public function testClear(): void
    {
        $storage = new FileStorage($this->tempFile);

        $storage->save(new LogEntry('192.168.1.1'));
        $storage->save(new LogEntry('10.0.0.1'));

        $storage->clear();

        $this->assertCount(0, $storage->getAll());
    }

    public function testGetAllEmpty(): void
    {
        $storage = new FileStorage($this->tempFile);

        $entries = $storage->getAll();

        $this->assertCount(0, $entries);
    }

    public function testPersistence(): void
    {
        $entry = new LogEntry('192.168.1.1', 'TestAgent');

        $storage1 = new FileStorage($this->tempFile);
        $storage1->save($entry);

        $storage2 = new FileStorage($this->tempFile);
        $entries = $storage2->getAll();

        $this->assertCount(1, $entries);
        $this->assertSame('192.168.1.1', $entries[0]->getIp());
        $this->assertSame('TestAgent', $entries[0]->getUserAgent());
    }
}
