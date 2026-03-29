<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$bar = debugbar();
$bar->mark('public-demo');
db_dump(['id' => 101, 'name' => 'Emran', 'roles' => ['founder', 'engineer']], 'User');

echo "<!doctype html><html><head><meta charset='utf-8'><title>DebugBar Demo</title></head><body>";
echo '<h1>Marwa DebugBar Demo</h1>';
echo (new \Marwa\DebugBar\Renderer($bar))->render();
echo '</body></html>';
