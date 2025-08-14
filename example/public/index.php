<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$user = ['id' => 101, 'name' => 'Emran', 'roles' => ['founder', 'engineer']];
dump($user, 'User 1'); // VarDumper -> Dumps tab
dump($user, 'User 2'); // VarDumper -> Dumps tab


echo "<!doctype html><html><head><meta charset='utf-8'><title>DebugBar Demo</title>
 <script src=\"https://cdn.tailwindcss.com\"></script>
 </head><body>";
echo "<h1>Marwa DebugBar Demo</h1>";
echo (new \Marwa\DebugBar\Renderer(debugbar()))->render();
echo "</body></html>";
