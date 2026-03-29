# Marwa DebugBar

A lightweight, framework-agnostic debug bar for PHP applications. It collects request timing, logs, queries, dumps, session data, and runtime diagnostics without requiring a framework.

## Requirements

- PHP 8.1+
- `ext-json`

Optional integrations:

- `psr/log` for forwarding logs to a PSR-3 logger
- `symfony/var-dumper` for richer dump formatting
- `twig/twig` for the optional Twig extension

## Installation

```bash
composer require memran/marwa-debugbar --dev
```

## Quick Start

```php
<?php

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

$bar = new DebugBar(($_ENV['DEBUGBAR_ENABLED'] ?? '0') === '1');

$bar->collectors()->register(KpiCollector::class);
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
db_dump(['id' => 42, 'name' => 'Ada'], 'User payload');

echo (new Renderer($bar))->render();
```

## Security Defaults

Request and session collectors redact common secret-bearing keys such as `password`, `token`, `authorization`, `cookie`, and `csrf`. Uploaded file metadata is summarized without exposing temporary paths. Keep the debug bar disabled outside local or explicitly trusted environments.

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

```bash
composer test          # PHPUnit
composer run lint      # PHPCS
composer run analyse   # PHPStan
composer run ci        # lint + tests + static analysis
composer run fix       # PHPCBF auto-fixes
```

The example app can be served locally with:

```bash
php -S 127.0.0.1:8000 -t example/public
```

## Testing and Quality

- PHPUnit covers core state management, renderer output, collector ordering, and redaction behavior.
- PHPStan runs at level 8.
- PHPCS enforces PSR-12 across `src/`, `tests/`, and `example/`.
- GitHub Actions runs validation on pushes and pull requests.

## Configuration Notes

- Use `DEBUGBAR_ENABLED=1` only in development or similarly controlled environments.
- `DebugBar::setLogger()` lets you mirror log entries to an application logger.
- `DebugBar::registerExceptionHandlers()` captures uncaught exceptions and fatal errors for development-time inspection.
- `DebugBar::setMaxDumps()` limits retained dump entries to prevent unbounded memory growth.

## Contributing

See [AGENTS.md](AGENTS.md) for repository-specific contribution guidelines, coding conventions, and review expectations.
