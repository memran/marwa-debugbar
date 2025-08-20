<?php

require_once '../vendor/autoload.php';
//session_start();
putenv('DEBUGBAR_ENABLED=1');

$bar = new \Marwa\DebugBar\DebugBar(true);
// (optional but recommended in dev)
$bar->registerExceptionHandlers();

// Lazily register collectors by class (no instantiation yet)
$bar->collectors()->register(\Marwa\DebugBar\Collectors\TimelineCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\MemoryCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\PhpCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\RequestCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\KpiCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\VarDumperCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\LogCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\DbQueryCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\SessionCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\ExceptionCollector::class);


$bar->mark("All Collection Booted");
$bar->addDump("Mohammad Emran");
$bar->addDump([1,2,3,4],'users');

$bar->log("info","Welcome to Marwa Debugbar!",[1,2,3,4,5,6]);
$bar->log("Warning","error has occured");

//Simulate Query
$start = microtime(true);
usleep(4000);
$dur = (microtime(true) - $start) * 1000;
$bar->addQuery('SELECT * FROM users WHERE id=?', [101], $dur, 'mysql');
//db_dump('SELECT * FROM password WHERE id=?', "test");

//Simulate session
// $_SESSION['user_id'] = 123;
// $_SESSION['username'] = 'john_doe';
// $_SESSION['logged_in'] = true;
// $_SESSION['cart_items'] = ['item1', 'item2'];


try 
{
    echo $x;
}catch(\Exception $e){
    //throw new \Exception("Session is not started : ".$e->message,404);
    $bar->addException($e);
}

//unset($_SESSION['flash_message']);

//session_destroy();

// or auto-discover all collectors in a folder/namespace
//$bar->collectors()->autoDiscover(__DIR__.'/src/Collectors', 'Marwa\\DebugBar\\Collectors');

// In your HTML response:
echo (new \Marwa\DebugBar\Renderer($bar))->render();