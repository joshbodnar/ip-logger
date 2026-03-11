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

// Ban an IP
$logger->banIp('192.168.1.100');
$logger->isBanned('192.168.1.100');
$logger->unbanIp('192.168.1.100');
```

## Configuration

```php
$config = (new IpLoggerConfig())
    ->setBanEnabled(true)                        // Enable IP banning
    ->setRateLimitEnabled(true)                  // Enable rate limiting
    ->setRateLimitMaxRequests(100)               // Max requests per window
    ->setRateLimitWindowSeconds(60);              // Window size in seconds

$logger = new IpLogger($storage, $config);
```

When bans or rate limiting are enabled, `log()` and `logFromClientInfo()` will throw specific exceptions.

## Exceptions

| Exception | Description |
|-----------|-------------|
| `InvalidIpException` | Thrown when IP address is invalid |
| `IpBannedException` | Thrown when IP is banned (extends `RuntimeException`) |
| `RateLimitExceededException` | Thrown when rate limit exceeded (extends `RuntimeException`) |

### Exception Usage

```php
use IpLogger\IpLogger;
use IpLogger\Exception\IpBannedException;
use IpLogger\Exception\RateLimitExceededException;
use IpLogger\Exception\InvalidIpException;

try {
    $logger->logFromClientInfo($clientInfo);
} catch (IpBannedException $e) {
    echo "Banned IP: " . $e->getIp();
} catch (RateLimitExceededException $e) {
    echo "Rate limited - max " . $e->getMaxRequests() . " per " . $e->getWindowSeconds() . " seconds";
} catch (InvalidIpException $e) {
    echo "Invalid IP: " . $e->getMessage();
}
```

## Client IP Detection

Automatically handles proxy headers:
- `X-Forwarded-For`
- `X-Real-IP`
- `Client-IP`
- `X-Cluster-Client-IP`

Supports comma-separated IPs (e.g., `"203.0.113.1, 198.51.100.1"`) - returns the leftmost (original client) IP.

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

## Yii2 Integration

### 1. As a Yii2 Component

Register as a application component in `config/main.php`:

```php
'components' => [
    'ipLogger' => [
        'class' => \IpLogger\IpLogger::class,
        'storage' => new \IpLogger\Storage\MySqlStorage(
            \Yii::$app->db->pdo,
            'ip_logs',
            'banned_ips',
            'rate_limits'
        ),
        'config' => (new \IpLogger\IpLoggerConfig())
            ->setBanEnabled(true)
            ->setRateLimitEnabled(true)
            ->setRateLimitMaxRequests(60)
            ->setRateLimitWindowSeconds(60),
    ],
]
```

Usage in controllers:
```php
$logger = \Yii::$app->ipLogger;

try {
    $logger->logFromClientInfo(new \IpLogger\Client\Psr7ClientInfo(\Yii::$app->request));
} catch (\IpLogger\Exception\InvalidIpException $e) {
    // IP is banned or rate limited
    \Yii::$app->response->statusCode = 429;
    return $this->asJson(['error' => $e->getMessage()]);
}
```

### 2. As a Lifecycle Behavior (Recommended)

Create a behavior that logs IPs on every request:

```php
<?php

namespace app\components;

use IpLogger\IpLogger;
use IpLogger\IpLoggerConfig;
use IpLogger\Client\Psr7ClientInfo;
use IpLogger\Storage\MySqlStorage;
use yii\base\Behavior;
use yii\web\Controller;
use yii\web\Request;

class IpLoggingBehavior extends Behavior
{
    public bool $logEnabled = true;
    
    public bool $banEnabled = true;
    
    public int $rateLimitMaxRequests = 60;
    
    public int $rateLimitWindowSeconds = 60;
    
    private ?IpLogger $logger = null;
    
    public function events(): array
    {
        return [
            Controller::EVENT_BEFORE_ACTION => 'beforeAction',
        ];
    }
    
    public function beforeAction($event): void
    {
        if (!$this->logEnabled) {
            return;
        }
        
        try {
            $this->getLogger()->logFromClientInfo(
                new Psr7ClientInfo(\Yii::$app->request)
            );
        } catch (\IpLogger\Exception\InvalidIpException $e) {
            // Handle banned/rate-limited IP
            \Yii::$app->response->statusCode = 429;
            \Yii::$app->response->data = [
                'error' => 'Too many requests or IP banned',
            ];
            \Yii::$app->end();
        }
    }
    
    private function getLogger(): IpLogger
    {
        if ($this->logger === null) {
            $storage = new MySqlStorage(\Yii::$app->db->pdo);
            
            $config = (new IpLoggerConfig())
                ->setBanEnabled($this->banEnabled)
                ->setRateLimitEnabled(true)
                ->setRateLimitMaxRequests($this->rateLimitMaxRequests)
                ->setRateLimitWindowSeconds($this->rateLimitWindowSeconds);
            
            $this->logger = new IpLogger($storage, $config);
        }
        
        return $this->logger;
    }
}
```

Attach to controllers or base controller:

```php
// In a controller
public function behaviors(): array
{
    return [
        'ipLog' => [
            'class' => \app\components\IpLoggingBehavior::class,
            'banEnabled' => true,
            'rateLimitMaxRequests' => 100,
            'rateLimitWindowSeconds' => 60,
        ],
    ];
}
```

### 3. Yii2 Module Wrapper

Create a full module for more control:

```php
<?php

namespace app\modules\ipmanager;

use IpLogger\IpLogger;
use IpLogger\IpLoggerConfig;
use IpLogger\Storage\MySqlStorage;
use IpLogger\Exception\InvalidIpException;
use yii\base\Module;
use yii\web\Controller;

class IpManagerModule extends Module
{
    public IpLogger $logger;
    
    public function init(): void
    {
        parent::init();
        
        $storage = new MySqlStorage(\Yii::$app->db->pdo);
        
        $config = (new IpLoggerConfig())
            ->setBanEnabled(true)
            ->setRateLimitEnabled(true)
            ->setRateLimitMaxRequests(100)
            ->setRateLimitWindowSeconds(60);
        
        $this->logger = new IpLogger($storage, $config);
    }
    
    public function logCurrentRequest(): bool
    {
        try {
            $clientInfo = new \IpLogger\Client\ServerArrayClientInfo($_SERVER);
            $this->logger->logFromClientInfo($clientInfo);
            return true;
        } catch (InvalidIpException $e) {
            return false;
        }
    }
}
```

Register in `config/main.php`:
```php
'modules' => [
    'ip-manager' => [
        'class' => 'app\modules\ipmanager\IpManagerModule',
    ],
]
```

### 4. Complete Usage Examples

**Check if IP is banned:**
```php
$isBanned = \Yii::$app->ipLogger->isBanned('192.168.1.100');
```

**Ban an IP:**
```php
\Yii::$app->ipLogger->banIp('192.168.1.100');
```

**Unban an IP:**
```php
\Yii::$app->ipLogger->unbanIp('192.168.1.100');
```

**Get all banned IPs:**
```php
$bannedIps = \Yii::$app->ipLogger->getBannedIps();
```

**Get logs for specific IP:**
```php
$logs = \Yii::$app->ipLogger->getByIp('192.168.1.1');
```

**Get all logs:**
```php
$allLogs = \Yii::$app->ipLogger->getAll();
```

### 5. Using with Yii2 REST API

```php
<?php

namespace app\controllers;

use yii\rest\Controller;
use IpLogger\Client\Psr7ClientInfo;

class SiteController extends Controller
{
    public function behaviors(): array
    {
        $behaviors = parent::behaviors();
        
        $behaviors['ipLog'] = [
            'class' => \app\components\IpLoggingBehavior::class,
            'banEnabled' => true,
            'rateLimitMaxRequests' => 30,
            'rateLimitWindowSeconds' => 60,
        ];
        
        return $behaviors;
    }
    
    public function actionIndex(): array
    {
        return ['status' => 'ok'];
    }
}
```

### 6. Console Command for IP Management

```php
<?php

namespace app\commands;

use yii\console\Controller;
use IpLogger\IpLogger;
use IpLogger\Storage\MySqlStorage;

class IpController extends Controller
{
    private IpLogger $logger;
    
    public function init(): void
    {
        parent::init();
        $this->logger = new IpLogger(new MySqlStorage(\Yii::$app->db->pdo));
    }
    
    public function actionBan(string $ip): void
    {
        $this->logger->banIp($ip);
        echo "IP {$ip} has been banned.\n";
    }
    
    public function actionUnban(string $ip): void
    {
        $this->logger->unbanIp($ip);
        echo "IP {$ip} has been unbanned.\n";
    }
    
    public function actionListBanned(): void
    {
        $banned = $this->logger->getBannedIps();
        
        if (empty($banned)) {
            echo "No banned IPs.\n";
            return;
        }
        
        echo "Banned IPs:\n";
        foreach ($banned as $ip) {
            echo "  - {$ip}\n";
        }
    }
    
    public function actionStats(): void
    {
        $all = $this->logger->getAll();
        $banned = $this->logger->getBannedIps();
        
        echo "Total logged IPs: " . count($all) . "\n";
        echo "Banned IPs: " . count($banned) . "\n";
    }
}
```

Run commands:
```bash
# Ban an IP
php yii ip/ban 192.168.1.100

# Unban an IP  
php yii ip/unban 192.168.1.100

# List banned IPs
php yii ip/list-banned

# Show stats
php yii ip/stats
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
