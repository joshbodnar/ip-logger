# IP-Logger

PHP package for logging IP addresses with flexible storage options.

## Installation

Add to `composer.json`:

```json
{
    "repositories": [{
        "type": "vcs",
        "url": "https://github.com/joshbodnar/ip-logger"
    }],
    "require": {
        "joshbodnar/ip-logger": "^1.0"
    }
}
```

Or via command line:

```bash
composer require joshbodnar/ip-logger:dev-master --repository='{"type":"vcs","url":"https://github.com/joshbodnar/ip-logger"}'
```

## Usage

```php
$logger = new IpLogger();
$logger->log('192.168.1.1', 'Mozilla/5.0');

// Auto-detect from request
$clientInfo = new Psr7ClientInfo($request);
$logger->logFromClientInfo($clientInfo);

$logger->getAll();
$logger->getByIp('192.168.1.1');
$logger->clear();
```

## Storage

| Type | Usage |
|------|-------|
| In-Memory | `$logger = new IpLogger();` |
| File | `new FileStorage('/path/logs.json')` |
| SQLite | `SqliteStorage::create('/path/db.sqlite')` or `new SqliteStorage($pdo)` |
| MySQL | `MySqlStorage::create($host, $db, $user, $pass)` or `new MySqlStorage($pdo)` |
| Redis | `new RedisStorage($redis)` |
| Memcached | `new MemcachedStorage($memcached)` |

## Framework Integration

Pass your framework's existing connections:

```php
// Laravel
$storage = new MySqlStorage(DB::connection()->getPdo());
$storage = new RedisStorage(Redis::connection()->client());

// Yii2
$storage = new MySqlStorage(Yii::$app->db->pdo);

// Symfony
$storage = new MySqlStorage($entityManager->getConnection()->getNativeConnection());
```

## Requirements

- PHP 8.1+

## Development

```bash
composer install
composer test
composer lint
composer phpstan
```
