<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Plugins;

use Psr\SimpleCache\CacheInterface;

final class CachePlugin extends AbstractPlugin
{
    private array $events = [];
    public function __construct(private readonly ?CacheInterface $cache = null) {}
    public function name(): string
    {
        return 'cache';
    }

    public function log(string $type, string $key, ?bool $hit = null, mixed $value = null): void
    {
        $this->events[] = [
            'time' => microtime(true),
            'type' => strtoupper($type),
            'key' => $key,
            'hit' => $hit,
            'value' => $value
        ];
    }

    public function extendPayload(array $payload): array
    {
        return ['cache' => [
            'driver' => $this->cache ? get_class($this->cache) : null,
            'events' => $this->events
        ]];
    }

    public function tabs(): array
    {
        $renderer = <<<'JS'
function renderCache(d) {
  const c = d.cache || {};
  const rows = (c.events||[]).map(e => `
    <tr>
      <td>${escapeHtml(e.type)}</td>
      <td class="mw-mono">${escapeHtml(e.key)}</td>
      <td>${e.hit===null ? '' : (e.hit ? '<span class="badge badge-success">HIT</span>' : '<span class="badge badge-error">MISS</span>')}</td>
      <td class="mw-mono">${escapeHtml(JSON.stringify(e.value))}</td>
    </tr>`).join('');
  return `<div>Driver: ${escapeHtml(c.driver||'N/A')}</div>
    <table class="mw"><thead><tr><th>Type</th><th>Key</th><th>Hit?</th><th>Value</th></tr></thead><tbody>${rows}</tbody></table>`;
}
JS;
        return [['key' => 'cache', 'title' => 'Cache', 'icon' => '📦', 'order' => 350, 'renderer' => $renderer]];
    }
}
