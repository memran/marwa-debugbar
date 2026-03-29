<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class PhpCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'php';
    }

    public static function label(): string
    {
        return 'PHP';
    }

    public static function icon(): string
    {
        return '🐘';
    }

    public static function order(): int
    {
        return 160;
    }

    public function collect(DebugState $state): array
    {
        unset($state);

        return [
            'version' => PHP_VERSION,
            'opcache_enabled' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
            'extensions' => get_loaded_extensions(),
        ];
    }

    public function renderHtml(array $data): string
    {
        $extensions = '';
        foreach ((array) ($data['extensions'] ?? []) as $extension) {
            $extensions .= '<span class="mw-badgel" style="margin: 0 6px 6px 0;">' . $this->e((string) $extension) . '</span>';
        }

        return <<<HTML
<div class="mw-grid-3" style="margin-bottom:12px">
  {$this->stat('Version', $this->e((string) ($data['version'] ?? '')))}
  {$this->stat('Opcache', !empty($data['opcache_enabled']) ? 'Yes' : 'No')}
</div>
<div>
  <div style="font-size:12px;color:#9ca3af;margin-bottom:6px">Extensions</div>
  <div>{$extensions}</div>
</div>
HTML;
    }
}
