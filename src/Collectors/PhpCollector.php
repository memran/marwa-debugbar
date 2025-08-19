<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class PhpCollector implements Collector
{
    use CollectorsTrait;
    public function name(): string
    {
        return 'php';
    }
    public function collect(): array
    {
        return [
            'version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
        ];
    }

   
    public  function renderHTML(array $p): string
    {
        $php = $p['php'] ?? [];
        $exts = '';
        foreach ((array)($php['extensions'] ?? []) as $ex) {
            $exts .= '<span class="mw-badgel" style="margin: 0 6px 6px 0; display:inline-block">'.$this->e((string)$ex).'</span>';
        }
        return <<<HTML
<div class="mw-grid-3" style="margin-bottom:12px">
  {$this->stat('Version', $this->e((string)($php['version'] ?? '')))}
  {$this->stat('Opcache', !empty($php['opcache_enabled']) ? 'Yes' : 'No')}
</div>
<div>
  <div style="font-size:12px;color:#9ca3af;margin-bottom:6px">Extensions</div>
  <div>{$exts}</div>
</div>
HTML;
    }
}
