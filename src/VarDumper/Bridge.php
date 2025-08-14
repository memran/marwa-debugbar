<?php

declare(strict_types=1);

namespace Marwa\DebugBar\VarDumper;

use Marwa\DebugBar\Config\VarDumperConfig;
use Marwa\DebugBar\DebugBar;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

final class Bridge
{
    private function __construct() {}

    public static function register(DebugBar $debugBar, ?VarDumperConfig $config = null): void
    {
        $cfg = $config ?? new VarDumperConfig();

        $cloner = new VarCloner();
        $cloner->setMaxItems($cfg->maxItems);
        $cloner->setMaxString($cfg->maxString);

        $dumper = new HtmlDumper();
        $dumper->setTheme($cfg->theme);

        VarDumper::setHandler(function ($var, ?string $label = null) use ($debugBar, $cloner, $dumper) {
            $data = $cloner->cloneVar($var);
            ob_start();
            $dumper->dump($data);
            $html = (string)ob_get_clean();
            [$file, $line] = self::caller();
            $debugBar->addDump($html, $label, $file, $line);
        });
    }

    private static function caller(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $f) {
            $fn = $f['function'] ?? '';
            $cl = $f['class'] ?? '';
            if (str_contains($cl ?? '', 'Symfony\\Component\\VarDumper') || $fn === 'dump') continue;
            $file = $f['file'] ?? null;
            $line = $f['line'] ?? null;
            if ($file && $line) return [$file, $line];
        }
        return [null, null];
    }
}
