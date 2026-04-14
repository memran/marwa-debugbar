<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

putenv('DEBUGBAR_ENABLED=1');

$bar = debugbar();
$bar->enable();

$bar->collectors()->register(\Marwa\DebugBar\Collectors\KpiCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\AlertCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\TimelineCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\VarDumperCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\LogCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\ExceptionCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\DbQueryCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\MemoryCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\PhpCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\RequestCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\SessionCollector::class);

$bar->mark('bootstrap');
$bar->log('info', 'DebugBar example booted');
