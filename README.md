# IP-Logger

A PHP package for logging IP addresses with flexible storage options.

## Installation

```bash
composer require ip-logger/package
```

## Usage

### Basic Usage (In-Memory)

```php
use IpLogger\IpLogger;
use IpLogger\Entity\LogEntry;

$logger = new IpLogger();

$entry = $logger->log('192.168.1.1', 'Mozilla/5.0');

$allEntries = $logger->getAll();
$filteredEntries = $logger->getByIp('192.168.1.1');

$logger->clear();
```

### Storage Options

#### File Storage

```php
use IpLogger\IpLogger;
use IpLogger\Storage\FileStorage;

$storage = new FileStorage('/path/to/logs.json');
$logger = new IpLogger($storage);
```

#### SQLite Storage

```php
use IpLogger\IpLogger;
use IpLogger\Storage\SqliteStorage;

$storage = new SqliteStorage('/path/to/database.db');
$logger = new IpLogger($storage);
```

#### MySQL Storage

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;

$storage = new MySqlStorage(
    host: 'localhost',
    database: 'ip_logs',
    username: 'root',
    password: 'password',
    port: 3306
);
$logger = new IpLogger($storage);
```

#### Redis Storage (requires Redis extension)

```php
use IpLogger\IpLogger;
use IpLogger\Storage\RedisStorage;

$redis = new \Redis();
$redis->connect('127.0.0.1');

$storage = new RedisStorage($redis);
$logger = new IpLogger($storage);
```

#### Memcached Storage (requires Memcached extension)

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MemcachedStorage;

$memcached = new \Memcached();
$memcached->addServer('127.0.0.1', 11211);

$storage = new MemcachedStorage($memcached);
$logger = new IpLogger($storage);
```

## API

### IpLogger

- `log(string $ip, ?string $userAgent = null): LogEntry` - Log an IP address
- `logFromClientInfo(ClientInfoInterface $clientInfo): LogEntry` - Log from client info (auto-detects IP and user agent)
- `getAll(): array<int, LogEntry>` - Get all logged entries
- `getByIp(string $ip): array<int, LogEntry>` - Get entries for a specific IP
- `clear(): void` - Clear all entries

### Client Info (Auto-Detection)

#### Using Server Array

```php
use IpLogger\IpLogger;
use IpLogger\Client\ServerArrayClientInfo;

$clientInfo = new ServerArrayClientInfo($_SERVER);
$logger = new IpLogger();
$entry = $logger->logFromClientInfo($clientInfo);
```

#### Using PSR-7 Request

```php
use IpLogger\IpLogger;
use IpLogger\Client\Psr7ClientInfo;

// $request is a PSR-7 ServerRequestInterface
$clientInfo = new Psr7ClientInfo($request);
$logger = new IpLogger();
$entry = $logger->logFromClientInfo($clientInfo);
```

The client info extractor automatically handles proxy headers:
- `X-Forwarded-For`
- `X-Real-IP`
- `Client-IP`
- `X-Cluster-Client-IP`

### LogEntry

- `getIp(): string` - Get the IP address
- `getUserAgent(): ?string` - Get the user agent
- `getTimestamp(): \DateTimeImmutable` - Get the timestamp

## Requirements

- PHP 8.1+

## Development

```bash
composer install          # Install dependencies
composer test             # Run tests
composer lint             # Code style check
composer phpstan          # Static analysis
```
