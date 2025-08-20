# Marwa DebugBar

[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-debugbar.svg)](https://packagist.org/packages/memran/marwa-debugbar)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-debugbar)](https://php.net)
[![License](https://img.shields.io/packagist/l/memran/marwa-debugbar)](LICENSE)

A **framework-agnostic**, lightweight, floating **Debug Bar** for PHP 8.1+.  
Built for clarity, speed, and extensibility with **lazy collectors**.

[![Marwa Debugbar Screenshot](https://i.postimg.cc/rpdf9XYR/Screenshot-2025-08-16-at-7-42-26-PM.jpg)](https://postimg.cc/McS0W4wz)

## ✨ Features

- **Lazy Collector System** — register by class, instantiated only when rendering
- **Server-side HTML tabs** — each collector renders its own HTML
- **Timeline** — request marks with deltas + total time
- **VarDumper** — pretty dumps captured into a tab
- **Logs** — PSR-3 style logs with context and badges
- **DB Queries** — SQL, bindings, connection, timings
- **Session** — status, metadata, and data
- **History** — browse JSON snapshots from disk (and/or via endpoint)
- **Modern floating UI** — toggle with a button or `~`

## 📦 Install

```bash
composer require memran/marwa-debugbar --dev

## Installation

```bash
composer require marwa/debugbar --dev
```

## Quick Start
```bash 
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;
use Marwa\DebugBar\Collectors\{
    TimelineCollector, VarDumperCollector, LogCollector, DbQueryCollector, SessionCollector
};

$bar = new DebugBar(($_ENV['DEBUGBAR_ENABLED'] ?? '0') === '1');

// Lazy collector registration (no instantiation yet)
$bar->collectors()->register(TimelineCollector::class);
$bar->collectors()->register(VarDumperCollector::class);
$bar->collectors()->register(LogCollector::class);
$bar->collectors()->register(DbQueryCollector::class);
$bar->collectors()->register(SessionCollector::class);

// Or auto-discover all collectors in the namespace/folder
// $bar->collectors()->autoDiscover(__DIR__.'/src/Collectors', 'Marwa\\DebugBar\\Collectors');

// During your request lifecycle:
$bar->mark('bootstrap');
// ...do work...
$bar->mark('controller');
// ...do work...
$bar->mark('view');

// Add logs/queries/dumps from your app:
$bar->log('info', 'DebugBar mounted', ['userId' => 42]);
$bar->addQuery('SELECT * FROM users WHERE id=?', [42], 12.34, 'mysql');

// Symfony VarDumper: will render on the Dumps tab
dump(['id'=>42, 'name'=>'Ada']);

// Inject into HTML (at the end of your response):
echo (new Renderer($bar))->render();
```
