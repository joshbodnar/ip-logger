# IP-Logger

A PHP package for logging IP addresses with flexible storage options.

## Installation

Add this to your `composer.json`:

```json
{
    "require": {
        "joshbodnar/ip-logger": "^1.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/joshbodnar/ip-logger"
        }
    ]
}
```

Or require directly from the command line:

```bash
composer require joshbodnar/ip-logger:dev-master --repository='{"type":"vcs","url":"https://github.com/joshbodnar/ip-logger"}'
```

### Without Packagist (Alternative)

If you don't want to use Packagist, add the repository directly to your project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/joshbodnar/ip-logger"
        }
    ],
    "require": {
        "joshbodnar/ip-logger": "^1.0"
    }
}
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

// Using factory method (creates connection internally)
$storage = SqliteStorage::create('/path/to/database.db');

// Or pass existing PDO instance
$pdo = new \PDO('sqlite:/path/to/database.db');
$storage = new SqliteStorage($pdo);

$logger = new IpLogger($storage);
```

#### MySQL Storage

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;

// Using factory method (creates connection internally)
$storage = MySqlStorage::create(
    host: 'localhost',
    database: 'ip_logs',
    username: 'root',
    password: 'password',
    port: 3306
);

// Or pass existing PDO instance from your framework
$storage = new MySqlStorage($frameworkPdo);

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

## Framework Integration

The storage classes accept framework-provided connections, allowing you to reuse existing database/caching infrastructure.

### Laravel

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;
use IpLogger\Storage\SqliteStorage;
use IpLogger\Storage\RedisStorage;

// Use Laravel's PDO connection
$pdo = \DB::connection()->getPdo();
$storage = new MySqlStorage($pdo);

// Or SQLite
$storage = new SqliteStorage(\DB::connection()->getPdo());

// Redis - use Laravel's Redis facade
$redis = \Redis::connection()->client();
$storage = new RedisStorage($redis);

$logger = new IpLogger($storage);
```

### Yii2

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;

// Use Yii2's PDO connection
$pdo = \Yii::$app->db->pdo;
$storage = new MySqlStorage($pdo);

// Redis
$redis = \Yii::$app->redis;
$storage = new RedisStorage($redis);

$logger = new IpLogger($storage);
```

### Symfony

```php
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;

// Using Doctrine DBAL connection
$connection = $entityManager->getConnection();
$pdo = $connection->getNativeConnection();
$storage = new MySqlStorage($pdo);

// Using Symfony's Redis
$redis = $this->container->get('snc_redis.default');
$storage = new RedisStorage($redis);

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
