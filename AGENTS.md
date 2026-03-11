# AGENTS.md - IP-Logger PHP Package

## Project Overview

This is a PHP package for logging IP addresses with flexible storage options. It follows PSR standards and uses modern PHP practices.

## Directory Structure

```
src/
├── IpLogger.php                 # Main logger implementation
├── InMemoryStorage.php          # Default in-memory storage
├── LoggerInterface.php          # Logger contract
├── Entity/
│   └── LogEntry.php             # Log entry entity
├── Exception/
│   └── InvalidIpException.php   # Custom exception
├── Client/
│   ├── ClientInfoInterface.php # Client info contract
│   ├── ServerArrayClientInfo.php   # Extract from $_SERVER
│   └── Psr7ClientInfo.php      # Extract from PSR-7 request
└── Storage/
    ├── StorageInterface.php     # Storage contract
    ├── FileStorage.php          # JSON file storage
    ├── SqliteStorage.php        # SQLite database storage
    ├── MySqlStorage.php         # MySQL database storage
    ├── RedisStorage.php         # Redis cache storage
    └── MemcachedStorage.php     # Memcached cache storage
```

## Commands

### Development Setup

```bash
composer install          # Install dependencies
```

### Code Quality

```bash
composer lint             # Run code style checks (PHP_CodeSniffer)
composer lint:fix         # Fix code style issues automatically
composer phpstan          # Run static analysis
composer phpstan:baseline # Generate/update baseline for static analysis
```

### Testing

```bash
composer test             # Run all tests (PHPUnit)
vendor/bin/phpunit        # Run tests directly
vendor/bin/phpunit --filter TestName  # Run single test by name
vendor/bin/phpunit --filter testMethodName  # Run single test method
vendor/bin/phpunit tests/SomeTest.php  # Run single test file
vendor/bin/phpunit --list-tests  # List all available tests
```

### Building

```bash
composer build            # Build the package (if scripts defined)
```

## Code Style Guidelines

### General Principles

- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) extended coding style
- Aim for PSR-4 autoloading
- Use PHP 8.1+ features when appropriate

### Naming Conventions

- **Classes/Interfaces/Traits**: `PascalCase` (e.g., `IpLogger`, `LoggerInterface`)
- **Methods**: `camelCase` (e.g., `logIp()`, `getClientIp()`)
- **Properties**: `camelCase` (e.g., `$ipAddress`, `$timestamp`)
- **Constants**: `UPPER_S_CASE` (e.g., `DEFAULT_FORMAT`)
- **Files**: Match class name (e.g., `src/IpLogger.php`)

### Imports

- Use absolute class names, not relative paths
- Group imports: first `use` statements (alphabetically), then blank line, then class definitions
- Avoid fully qualified class names in code; import them instead

```php
use InvalidArgumentException;
use IpLogger\LoggerInterface;
use IpLogger\Entity\LogEntry;

class IpLogger implements LoggerInterface
{
    // ...
}
```

### Types

- Use strict types: `declare(strict_types=1);` at the top of every file
- Declare return types on all methods
- Use union types (PHP 8.0+) where appropriate
- Use `?Type` for nullable types or `?` prefix (PHP 8.1+)

```php
declare(strict_types=1);

public function log(string $ip, ?string $userAgent = null): LogEntry
{
    // ...
}
```

### Formatting

- Indentation: 4 spaces (no tabs)
- Line length: ~120 characters max
- Use blank lines to separate logical code blocks
- One blank line between `use` statements and class definition
- Add blank line before `return` statements (except in early returns)

### Error Handling

- Use exceptions for error conditions
- Use appropriate exception types:
  - `InvalidArgumentException` for invalid input
  - `RuntimeException` for runtime errors
  - Custom exceptions for domain-specific errors
- Throw exceptions early, validate inputs at method entry points

```php
public function __construct(string $logPath)
{
    if (!is_dir($logPath) && !mkdir($logPath, 0755, true)) {
        throw new RuntimeException("Cannot create log directory: {$logPath}");
    }
    $this->logPath = $logPath;
}
```

### DocBlocks

- Document public API methods with DocBlocks
- Include `@param` and `@return` types (even when using PHP 8+ typed properties)
- Use meaningful descriptions

```php
/**
 * Logs an IP address with optional metadata.
 *
 * @param string      $ip        The IP address to log
 * @param string|null  $userAgent Optional user agent string
 *
 * @return LogEntry The created log entry
 *
 * @throws InvalidArgumentException If IP address is invalid
 */
public function log(string $ip, ?string $userAgent = null): LogEntry
{
    // ...
}
```

### Testing Guidelines

- Test files go in `tests/` directory
- Name test classes `*Test` (e.g., `IpLoggerTest`)
- Name test methods `testMethodName` or `itDoesSomething` (choose one style, be consistent)
- Use descriptive assertion messages
- Test one thing per test method
- Use `@requires` annotation for tests requiring specific PHP extensions

```php
/**
 * @requires extension pdo_sqlite
 */
final class SqliteStorageTest extends TestCase
{
    // ...
}
```

```php
public function testValidIpv4Address(): void
{
    $entry = $this->logger->log('192.168.1.1');
    
    $this->assertSame('192.168.1.1', $entry->getIp());
}
```

### Additional Best Practices

- Keep classes focused (Single Responsibility Principle)
- Use dependency injection
- Avoid static methods except for factories/helpers
- Use interfaces for abstractions
- Write small, testable methods
- Avoid magic methods (`__get`, `__set`) unless necessary
- Use enums (PHP 8.1+) for fixed sets of values
- Storage implementations should follow `StorageInterface`
- Use constructor injection for dependencies
- Validate external resources (files, databases, cache connections) early
