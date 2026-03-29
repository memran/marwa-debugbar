<?php

declare(strict_types=1);

use Marwa\DebugBar\DebugBar;

if (!function_exists('debugbar')) {
    function debugbar(): DebugBar
    {
        /** @var mixed $instance */
        $instance = $GLOBALS['mw_debugbar'] ?? null;

        if (!$instance instanceof DebugBar) {
            $instance = new DebugBar((getenv('DEBUGBAR_ENABLED') ?: '0') === '1');
            $GLOBALS['mw_debugbar'] = $instance;
        }

        return $instance;
    }
}

if (!function_exists('db_dump')) {
    function db_dump(mixed $value, ?string $name = null): void
    {
        debugbar()->addDump($value, $name);
    }
}
