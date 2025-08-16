<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$renderer=new \Marwa\DebugBar\Renderer(debugbar());
$user = ['id' => 101, 'name' => 'Emran', 'roles' => ['founder', 'engineer']];
dump($user, 'User 1'); // VarDumper -> Dumps tabs


echo "<!doctype html><html><head><meta charset='utf-8'><title>DebugBar Demo</title>
 </head><body>";
echo "<h1>Marwa DebugBar Demo</h1>";
echo  $renderer->render();
echo "</body></html>";

