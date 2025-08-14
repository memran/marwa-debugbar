<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Collectors\MemoryCollector;
use Marwa\DebugBar\Collectors\PhpCollector;
use Marwa\DebugBar\Collectors\RequestCollector;
use Marwa\DebugBar\Collectors\RequestMetricsCollector;
use Marwa\DebugBar\Config\VarDumperConfig;
use Marwa\DebugBar\VarDumper\Bridge as VarDumperBridge;
use Marwa\DebugBar\Plugins\SessionPlugin;
use Marwa\DebugBar\Plugins\CachePlugin;
use Marwa\DebugBar\Plugins\HeuristicsPlugin;
use Marwa\DebugBar\Storage\FileStorage;
use Marwa\DebugBar\Config\HistoryConfig;

putenv('DEBUGBAR_ENABLED=1');

$mw_debugbar = new DebugBar((getenv('DEBUGBAR_ENABLED') ?: '0') === '1');
$GLOBALS['mw_debugbar'] = $mw_debugbar;

// collectors
$metrics = new RequestMetricsCollector(microtime(true));
$mw_debugbar->addCollector(new MemoryCollector())
    ->addCollector(new PhpCollector())
    ->addCollector(new RequestCollector())
    ->addCollector($metrics);

// VarDumper
VarDumperBridge::register($mw_debugbar, new VarDumperConfig(theme: 'dark', maxDumps: 200));

// plugins
$mw_debugbar->plugins()->register(new SessionPlugin(), true);
$cachePlugin = new CachePlugin(null);
$mw_debugbar->plugins()->register($cachePlugin, true);
$mw_debugbar->plugins()->register(new HeuristicsPlugin(), true);

// history
$mw_debugbar->setHistory(
    new FileStorage(__DIR__ . '/../runtime/debugbar'),
    new HistoryConfig(enabled: true, maxSnapshots: 300, uiListLimit: 30)
);

// Simulated actions
$mw_debugbar->mark('bootstrap_done');
$mw_debugbar->log('info', 'DebugBar initialized');
$metrics->incLog('info');

// Simulate query
$start = microtime(true);
usleep(4000);
$dur = (microtime(true) - $start) * 1000;
$mw_debugbar->addQuery('SELECT * FROM users WHERE id=?', [101], $dur, 'mysql');
$metrics->incQuery($dur);

// Simulate cache
$cachePlugin->log('GET', 'user:101', false, null);
$cachePlugin->log('SET', 'user:101', null, ['id' => 101, 'name' => 'Emran']);
$cachePlugin->log('GET', 'user:101', true, ['id' => 101, 'name' => 'Emran']);

// End metrics (in middleware in real apps)
$metrics->setRoute('/demo');
$metrics->setStatus(200);
$metrics->setResponseBytes(null);
$metrics->finish();
