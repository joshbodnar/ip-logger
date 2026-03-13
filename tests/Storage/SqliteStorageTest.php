<?php

declare(strict_types=1);

namespace IpLogger\Tests\Storage;

use IpLogger\Storage\SqliteStorage;
use IpLogger\Entity\LogEntry;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension pdo_sqlite
 */
final class SqliteStorageTest extends TestCase
{
    private string $tempDb;

    protected function setUp(): void
    {
        $this->tempDb = tempnam(sys_get_temp_dir(), 'ip_log_') . '.db';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempDb)) {
            unlink($this->tempDb);
        }
    }

    public function testSaveAndGetAll(): void
    {
        $storage = SqliteStorage::create($this->tempDb);

        $entry1 = new LogEntry('192.168.1.1');
        $entry2 = new LogEntry('10.0.0.1');

        $storage->save($entry1);
        $storage->save($entry2);

        $entries = $storage->getAll();

        $this->assertCount(2, $entries);
    }

    public function testGetByIp(): void
    {
        $storage = SqliteStorage::create($this->tempDb);

        $storage->save(new LogEntry('192.168.1.1'));
        $storage->save(new LogEntry('192.168.1.2'));
        $storage->save(new LogEntry('192.168.1.1'));

        $entries = $storage->getByIp('192.168.1.1');

        $this->assertCount(2, $entries);
    }

    public function testClear(): void
    {
        $storage = SqliteStorage::create($this->tempDb);

        $storage->save(new LogEntry('192.168.1.1'));
        $storage->save(new LogEntry('10.0.0.1'));

        $storage->clear();

        $this->assertCount(0, $storage->getAll());
    }

    public function testGetAllEmpty(): void
    {
        $storage = SqliteStorage::create($this->tempDb);

        $entries = $storage->getAll();

        $this->assertCount(0, $entries);
    }

    public function testPersistence(): void
    {
        $entry = new LogEntry('192.168.1.1', 'TestAgent');

        $storage1 = SqliteStorage::create($this->tempDb);
        $storage1->save($entry);

        $storage2 = SqliteStorage::create($this->tempDb);
        $entries = $storage2->getAll();

        $this->assertCount(1, $entries);
        $this->assertSame('192.168.1.1', $entries[0]->getIp());
        $this->assertSame('TestAgent', $entries[0]->getUserAgent());
    }
    
    public function testCreateRejectsInvalidTableNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');
        
        // This should throw an exception because of the semicolon
        SqliteStorage::create($this->tempDb, 'ip_logs; DROP TABLE users;');
    }
}
