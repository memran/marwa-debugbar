<?php

declare(strict_types=1);

use Marwa\DebugBar\DebugBar;

if (!function_exists('debugbar')) {
    function debugbar(): ?DebugBar
    {
        global $mw_debugbar;
        if(!$mw_debugbar)
        {
            $mw_debugbar = new DebugBar((getenv('DEBUGBAR_ENABLED') ?: '0') === '1');
            return $GLOBALS['mw_debugbar'] = $mw_debugbar;
        }
        return $mw_debugbar;

    }
}

if (!function_exists('db_dump')) {
    function db_dump(mixed $value, ?string $name = null): void
    {
        if (function_exists('dump')) {
            dump($value, $name);
            return;
        }
        if ($db = debugbar()) {
            //$safe = htmlspecialchars(print_r($value, true), ENT_QUOTES, 'UTF-8');
            $db->addDump($value,$name);
        }
    }
}