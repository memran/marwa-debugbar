# Marwa DebugBar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/memran/marwa-debugbar.svg)](https://packagist.org/packages/memran/marwa-debugbar)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-debugbar.svg)](https://packagist.org/packages/memran/marwa-debugbar)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg?logo=php&logoColor=white)](https://packagist.org/packages/memran/marwa-debugbar)
[![CI Workflow](https://img.shields.io/github/actions/workflow/status/memran/marwa-debugbar/ci.yml?branch=main&label=CI)](https://github.com/memran/marwa-debugbar/actions/workflows/ci.yml)
[![PHPStan Level](https://img.shields.io/badge/PHPStan-level%208-31c653.svg)](https://phpstan.org/)
[![PHPUnit](https://img.shields.io/badge/PHPUnit-tested-0d5c63.svg?logo=phpunit&logoColor=white)](https://phpunit.de/)

A lightweight, framework-agnostic debug bar for PHP applications. It collects timing, logs, SQL queries, dumps, exceptions, request data, session data, and runtime KPIs without requiring a specific framework.

## Why This Package

`memran/marwa-debugbar` is designed for plain PHP, small custom frameworks, and package-level integrations where you want:

- a self-contained in-browser debug bar
- explicit collector registration
- no framework lock-in
- low ceremony instrumentation
- extension points for your own collectors

Use it for local development and diagnostics. Do not treat it as production monitoring.

## Requirements

- PHP 8.1+
- `ext-json`

Optional integrations:

- `psr/log` for forwarding logs to a PSR-3 logger
- `symfony/var-dumper` for richer dump formatting
- `twig/twig` for the optional Twig dump extension

## Installation

```bash
composer require memran/marwa-debugbar --dev
```

## Quick Start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Marwa\DebugBar\Collectors\AlertCollector;
use Marwa\DebugBar\Collectors\DbQueryCollector;
use Marwa\DebugBar\Collectors\ExceptionCollector;
use Marwa\DebugBar\Collectors\KpiCollector;
use Marwa\DebugBar\Collectors\LogCollector;
use Marwa\DebugBar\Collectors\MemoryCollector;
use Marwa\DebugBar\Collectors\PhpCollector;
use Marwa\DebugBar\Collectors\RequestCollector;
use Marwa\DebugBar\Collectors\TimelineCollector;
use Marwa\DebugBar\Collectors\VarDumperCollector;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;

$bar = new DebugBar((getenv('DEBUGBAR_ENABLED') ?: '0') === '1');

$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(AlertCollector::class);
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(VarDumperCollector::class);
$bar->collectors()->register(LogCollector::class);
$bar->collectors()->register(ExceptionCollector::class);
$bar->collectors()->register(DbQueryCollector::class);
$bar->collectors()->register(MemoryCollector::class);
$bar->collectors()->register(PhpCollector::class);
$bar->collectors()->register(RequestCollector::class);

$bar->mark('bootstrap');
$bar->log('info', 'Debug bar mounted', ['userId' => 42]);
$bar->addQuery('SELECT * FROM users WHERE id = ?', [42], 3.42, 'mysql');
$bar->addDump(['id' => 42, 'name' => 'Ada'], 'User payload');
$bar->mark('controller_done');

echo (new Renderer($bar))->render();
```

## Tutorial

### 1. Create the debug bar

```php
use Marwa\DebugBar\DebugBar;

$bar = new DebugBar(true);
```

The constructor accepts a single flag:

- `true`: start collecting immediately
- `false`: keep the bar dormant until you call `enable()`

### 2. Register the collectors you want

Collectors are opt-in. Register only what you need.

```php
use Marwa\DebugBar\Collectors\AlertCollector;
use Marwa\DebugBar\Collectors\DbQueryCollector;
use Marwa\DebugBar\Collectors\KpiCollector;
use Marwa\DebugBar\Collectors\LogCollector;
use Marwa\DebugBar\Collectors\RequestCollector;
use Marwa\DebugBar\Collectors\TimelineCollector;
use Marwa\DebugBar\Collectors\VarDumperCollector;

$collectors = $bar->collectors();

$collectors->register(KpiCollector::class);
$collectors->register(AlertCollector::class);
$collectors->register(TimelineCollector::class);
$collectors->register(VarDumperCollector::class);
$collectors->register(LogCollector::class);
$collectors->register(DbQueryCollector::class);
$collectors->register(RequestCollector::class);
```

### 3. Instrument your request lifecycle

```php
$bar->mark('bootstrap');

$bar->log('info', 'Loading dashboard', ['tenant' => 'acme']);

$bar->addQuery(
    'SELECT * FROM invoices WHERE tenant_id = ? ORDER BY created_at DESC',
    ['acme'],
    12.84,
    'mysql'
);

$bar->addDump($currentUser, 'Authenticated user');

try {
    $response = $controller->handle($request);
} catch (\Throwable $e) {
    $bar->addException($e);
    throw $e;
}

$bar->mark('response_ready');
```

### 4. Render it at the end of the response

```php
use Marwa\DebugBar\Renderer;

$renderer = new Renderer($bar);

echo $html;
echo $renderer->render();
```

### 5. Use helper functions if you prefer a global entry point

The package autoloads two helpers:

```php
$bar = debugbar();
$bar->enable();

db_dump($payload, 'Payload');
```

## Configuration

### Base toggle

The package does not force a configuration system. The simplest approach is environment variables:

```bash
DEBUGBAR_ENABLED=1
```

Recommended policy:

- enable only in local or explicitly trusted environments
- disable in staging and production unless you fully control access

### Alert thresholds

If you register `AlertCollector`, these environment variables are supported:

| Variable | Default | Meaning |
| --- | ---: | --- |
| `DEBUGBAR_ALERTS_ENABLED` | `1` | Turns alert evaluation on or off |
| `DEBUGBAR_SLOW_QUERY_MS` | `100` | Threshold for a slow SQL query |
| `DEBUGBAR_SLOW_REQUEST_MS` | `1000` | Threshold for total request duration |
| `DEBUGBAR_SLOW_SPAN_MS` | `250` | Threshold for delta between consecutive timeline marks |
| `DEBUGBAR_HIGH_MEMORY_MB` | `64` | Threshold for peak memory usage |
| `DEBUGBAR_LARGE_RESPONSE_BYTES` | `1048576` | Threshold for buffered response size |

Severity rules are deterministic:

- `warning` when the metric reaches the threshold
- `critical` when the metric is at least `2x` the threshold

Example:

- `140 ms` query with `DEBUGBAR_SLOW_QUERY_MS=100` => `warning`
- `220 ms` query with `DEBUGBAR_SLOW_QUERY_MS=100` => `critical`

## Built-In Collectors

| Collector | Key | Purpose |
| --- | --- | --- |
| `KpiCollector` | `kpi` | Request KPIs such as duration, SQL time, status, memory, and response size |
| `AlertCollector` | `alerts` | Performance warnings for slow queries, spans, request duration, memory, and response size |
| `TimelineCollector` | `timeline` | Chronological marks and deltas between marks |
| `VarDumperCollector` | `dumps` | Dumps added through `addDump()` or `db_dump()` |
| `LogCollector` | `logs` | Structured log entries added through `log()` |
| `ExceptionCollector` | `exceptions` | Captured exceptions and stack traces |
| `DbQueryCollector` | `queries` | SQL statements, params, durations, and connection names |
| `MemoryCollector` | `memory` | Current and peak PHP memory usage |
| `PhpCollector` | `php` | PHP version, extensions, and opcache status |
| `RequestCollector` | `request` | Sanitized request metadata, headers, input, cookies, files, and server values |
| `SessionCollector` | `session` | Sanitized session metadata and attributes |
| `CacheCollector` | `cache` | Placeholder/example collector for cache metrics |

### Example registration sets

Minimal:

```php
$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(LogCollector::class);
```

Full diagnostics:

```php
$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(AlertCollector::class);
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(VarDumperCollector::class);
$bar->collectors()->register(LogCollector::class);
$bar->collectors()->register(ExceptionCollector::class);
$bar->collectors()->register(DbQueryCollector::class);
$bar->collectors()->register(MemoryCollector::class);
$bar->collectors()->register(PhpCollector::class);
$bar->collectors()->register(RequestCollector::class);
$bar->collectors()->register(SessionCollector::class);
```

## Public API Reference

### `DebugBar`

#### `__construct(bool $enabled = false)`

Create a new bar.

```php
$bar = new DebugBar();
$enabledBar = new DebugBar(true);
```

#### `enable(): void`

Start collecting data.

```php
$bar->enable();
```

#### `disable(): void`

Stop collecting new state.

```php
$bar->disable();
```

#### `isEnabled(): bool`

Check whether collection is active.

```php
if ($bar->isEnabled()) {
    $bar->log('debug', 'Collector active');
}
```

#### `setLogger(LoggerInterface $logger): void`

Mirror log calls to your PSR-3 logger.

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('debugbar');
$logger->pushHandler(new StreamHandler('php://stdout'));

$bar->setLogger($logger);
```

#### `setMaxDumps(int $maxDumps): void`

Cap retained dump entries.

```php
$bar->setMaxDumps(50);
```

#### `getStartTime(): float`

Return the request start timestamp recorded by the bar.

```php
$start = $bar->getStartTime();
```

#### `elapsedMilliseconds(): float`

Return current elapsed time from the bar start.

```php
$elapsed = $bar->elapsedMilliseconds();
```

#### `collectors(): CollectorManager`

Access the collector registry.

```php
$manager = $bar->collectors();
$manager->register(KpiCollector::class);
```

#### `mark(string $label): void`

Create a timeline mark.

```php
$bar->mark('bootstrap');
$bar->mark('router_resolved');
$bar->mark('response_sent');
```

#### `log(string $level, string $message, array $context = []): void`

Add a structured log record.

```php
$bar->log('warning', 'Rate limit nearly reached', ['remaining' => 3]);
```

#### `addQuery(string $sql, array $params = [], float $durationMs = 0.0, ?string $connection = null): void`

Add a SQL query record.

```php
$bar->addQuery(
    'SELECT * FROM users WHERE email = ?',
    ['ada@example.com'],
    8.37,
    'mysql'
);
```

#### `addDump(mixed $value, ?string $name = null, ?string $file = null, ?int $line = null): void`

Add a dump entry.

```php
$bar->addDump($user, 'Current user');
$bar->addDump($payload, 'Payload', __FILE__, __LINE__);
```

#### `addException(Throwable $exception): void`

Record an exception manually.

```php
try {
    $service->run();
} catch (\Throwable $e) {
    $bar->addException($e);
}
```

#### `registerExceptionHandlers(bool $capturePhpErrorsAsExceptions = true): void`

Capture uncaught exceptions and optionally PHP errors.

```php
$bar->registerExceptionHandlers();
```

Disable PHP error conversion if you only want uncaught exceptions:

```php
$bar->registerExceptionHandlers(false);
```

#### `state(): DebugState`

Expose the immutable state passed into collectors.

```php
$state = $bar->state();
$queryCount = count($state->queries);
```

### `CollectorManager`

#### `register(string $collectorClass, bool $enabled = true): void`

Register a collector class.

```php
$bar->collectors()->register(KpiCollector::class);
$bar->collectors()->register(SessionCollector::class, enabled: false);
```

#### `setEnabled(string $key, bool $enabled): void`

Enable or disable a registered collector by key.

```php
$bar->collectors()->setEnabled('session', true);
```

#### `autoDiscover(string $directory, string $baseNamespace): int`

Scan a directory for collector classes.

```php
$count = $bar->collectors()->autoDiscover(
    __DIR__ . '/src/DebugCollectors',
    'App\\DebugCollectors'
);
```

#### `renderAll(DebugState $state): array`

Render all enabled collectors and return their metadata, HTML, and collected data. This is mainly useful for advanced integrations and tests.

```php
$rows = $bar->collectors()->renderAll($bar->state());
```

#### `metadata(): array`

Return enabled collector metadata without rendering.

```php
$tabs = $bar->collectors()->metadata();
```

### `Renderer`

#### `__construct(DebugBar $debugBar)`

Create the renderer.

```php
$renderer = new Renderer($bar);
```

#### `render(): string`

Render the full HTML and JavaScript for the debug bar.

```php
echo $renderer->render();
```

### Global helper functions

#### `debugbar(): DebugBar`

Resolve a shared global debug bar instance.

```php
$bar = debugbar();
$bar->enable();
```

#### `db_dump(mixed $value, ?string $name = null): void`

Shortcut for `debugbar()->addDump(...)`.

```php
db_dump($requestData, 'Request Data');
```

### `Collector` contract

Implement this interface to create your own tab.

#### `Collector::key(): string`

Return a unique tab key.

```php
public static function key(): string
{
    return 'jobs';
}
```

#### `Collector::label(): string`

Return the visible tab label.

```php
public static function label(): string
{
    return 'Jobs';
}
```

#### `Collector::icon(): string`

Return a small icon string.

```php
public static function icon(): string
{
    return '⚙️';
}
```

#### `Collector::order(): int`

Return the sort order.

```php
public static function order(): int
{
    return 210;
}
```

#### `collect(DebugState $state): array`

Collect tab data from the immutable state.

```php
public function collect(DebugState $state): array
{
    return ['jobs' => $this->jobs];
}
```

#### `renderHtml(array $data): string`

Return the tab HTML.

```php
public function renderHtml(array $data): string
{
    return '<div>Queued jobs: ' . count($data['jobs']) . '</div>';
}
```

## Custom Collector Example

```php
<?php

namespace App\DebugCollectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class FeatureFlagCollector implements Collector
{
    public static function key(): string
    {
        return 'feature_flags';
    }

    public static function label(): string
    {
        return 'Flags';
    }

    public static function icon(): string
    {
        return '🚩';
    }

    public static function order(): int
    {
        return 240;
    }

    public function collect(DebugState $state): array
    {
        unset($state);

        return [
            'items' => [
                'new_checkout' => true,
                'beta_dashboard' => false,
            ],
        ];
    }

    public function renderHtml(array $data): string
    {
        $items = $data['items'] ?? [];

        return '<pre>' . htmlspecialchars(print_r($items, true), ENT_QUOTES, 'UTF-8') . '</pre>';
    }
}
```

Register it:

```php
$bar->collectors()->register(\App\DebugCollectors\FeatureFlagCollector::class);
```

## Alerts Tab

`AlertCollector` adds a dedicated `Alerts` tab that summarizes slow runtime behavior in one place.

It detects:

- slow queries
- slow total request duration
- slow timeline spans between consecutive marks
- high peak memory usage
- large buffered response size

Each alert includes:

- severity
- type
- message
- metric value
- context

If alerts are disabled with `DEBUGBAR_ALERTS_ENABLED=0`, the tab renders a disabled message instead of findings.

## Request and Session Redaction

`RequestCollector` and `SessionCollector` redact common secret-bearing keys such as:

- `password`
- `token`
- `authorization`
- `cookie`
- `csrf`
- `secret`

Uploaded file metadata is summarized without exposing temporary paths.

## Twig Integration

If you use Twig, register the bundled extension:

```php
use Marwa\DebugBar\Extensions\TwigDumpExtension;

$twig->addExtension(new TwigDumpExtension($bar));
```

Then inside Twig templates:

```twig
{{ db_dump(user, 'Current user') }}
```

## Example Bootstrap

The repository ships with a small example app under `example/`.

Serve it locally with:

```bash
php -S 127.0.0.1:8000 -t example/public
```

## Project Layout

```text
src/
  Collectors/   Built-in collectors and shared HTML helpers
  Contracts/    Collector interface and exceptions
  Core/         Immutable runtime state passed to collectors
  Extensions/   Optional integrations such as Twig
example/        Minimal demo bootstrap and public entrypoint
tests/          PHPUnit coverage for core behavior and regressions
```

## Development

Install dependencies:

```bash
composer install
```

Run the local quality checks:

```bash
composer test
composer run lint
composer run analyze
```

Run the combined CI gate:

```bash
composer run ci
```

Apply automatic coding-standard fixes:

```bash
composer run fix
```

## Testing and Quality

- PHPUnit covers core state management, collectors, rendering, and redaction behavior
- PHPStan runs at level 8
- PHPCS enforces PSR-12 across `src/`, `tests/`, and `example/`
- GitHub Actions runs validation on pushes and pull requests

## Security Guidance

- keep the bar disabled outside local or explicitly trusted environments
- avoid rendering the bar for public traffic
- remember that even redacted collectors may still reveal development details
- treat alerts as local diagnostics, not production monitoring

## Contributing

See [AGENTS.md](AGENTS.md) for repository-specific contribution guidelines, coding conventions, and review expectations.
