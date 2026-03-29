<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$bar = debugbar();
$bar->mark('example');
$bar->addDump(['user' => 'Mohammad Emran', 'roles' => ['maintainer', 'developer']], 'User');
$bar->log('info', 'Welcome to Marwa DebugBar', ['feature' => 'demo']);

$start = microtime(true);
usleep(4000);
$bar->addQuery('SELECT * FROM users WHERE id = ?', [101], (microtime(true) - $start) * 1000, 'mysql');

try {
    throw new RuntimeException('Example exception');
} catch (Throwable $exception) {
    $bar->addException($exception);
}

echo (new \Marwa\DebugBar\Renderer($bar))->render();
