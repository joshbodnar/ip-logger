<?php

declare(strict_types=1);

namespace IpLogger\Tests\Storage;

use IpLogger\InMemoryStorage;
use IpLogger\Entity\LogEntry;
use PHPUnit\Framework\TestCase;

final class InMemoryStorageTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    public function testSaveAndGetAll(): void
    {
        $entry1 = new LogEntry('192.168.1.1');
        $entry2 = new LogEntry('10.0.0.1');

        $this->storage->save($entry1);
        $this->storage->save($entry2);

        $entries = $this->storage->getAll();

        $this->assertCount(2, $entries);
    }

    public function testGetByIp(): void
    {
        $this->storage->save(new LogEntry('192.168.1.1'));
        $this->storage->save(new LogEntry('192.168.1.2'));
        $this->storage->save(new LogEntry('192.168.1.1'));

        $entries = $this->storage->getByIp('192.168.1.1');

        $this->assertCount(2, $entries);
    }

    public function testClear(): void
    {
        $this->storage->save(new LogEntry('192.168.1.1'));
        $this->storage->save(new LogEntry('10.0.0.1'));

        $this->storage->clear();

        $this->assertCount(0, $this->storage->getAll());
    }

    public function testGetAllEmpty(): void
    {
        $entries = $this->storage->getAll();

        $this->assertCount(0, $entries);
    }

    public function testGetByIpEmpty(): void
    {
        $entries = $this->storage->getByIp('192.168.1.1');

        $this->assertCount(0, $entries);
    }
}
