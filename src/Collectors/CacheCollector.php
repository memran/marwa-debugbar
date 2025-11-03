<?php 

namespace Marwa\DebugBar\Collectors;
use Marwa\DebugBar\Contracts\Collector;

final class CacheCollector implements Collector
{
    public static function key(): string { return 'cache'; }
    public static function label(): string { return 'Cache'; }
    public static function icon(): string { return '📦'; }
    public static function order(): int { return 350; }

    public function collect(\Marwa\DebugBar\Core\DebugState $state): array {
        // pull from your own in-memory metrics or global logs
        return ['driver' => 'array', 'hits' => 10, 'misses' => 2];
    }

    public function renderHtml(array $d): string {
        $esc = fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
        return "<table class='mw'><tbody>
          <tr><th>Driver</th><td>{$esc($d['driver'])}</td></tr>
          <tr><th>Hits</th><td>{$esc($d['hits'])}</td></tr>
          <tr><th>Misses</th><td>{$esc($d['misses'])}</td></tr>
        </tbody></table>";
    }
}
