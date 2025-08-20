<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

/**
 * KPI Collector
 *
 * Shows quick-glance Request KPIs: total duration, SQL count/time,
 * logs count, dumps count, peak memory, route, status, response size, etc.
 *
 * It derives:
 *  - duration: from last mark or "now" if no marks
 *  - sql_count/sql_time_ms: from state->queries
 *  - logs_count, dumps_count: from state arrays
 *  - memory_peak_mb: from PHP peak usage
 *  - route, status, response_bytes: best-effort from $_SERVER when available
 */
final class KpiCollector implements Collector
{
    use HtmlKit;
    public static function key(): string   { return 'kpi'; }
    public static function label(): string { return 'KPIs'; }
    public static function icon(): string  { return '📊'; }
    public static function order(): int    { return 100; }

    public function collect(DebugState $state): array
    {
        // Duration
        $endTs = $this->lastMarkTs($state) ?? microtime(true);
        $durationMs = round(($endTs - $state->requestStart) * 1000, 2);

        // SQLs
        $sqlCount = \count($state->queries);
        $sqlTimeMs = 0.0;
        foreach ($state->queries as $q) {
            $sqlTimeMs += (float)($q['duration_ms'] ?? 0.0);
        }
        $sqlTimeMs = round($sqlTimeMs, 2);

        // Logs & Dumps
        $logsCount  = \count($state->logs);
        $dumpsCount = \count($state->dumps);

        // Memory
        $peakMb = round(\memory_get_peak_usage(true) / 1048576, 2);

        // Best-effort request meta (don’t hard-depend on a Request collector)
        $server = $_SERVER ?? [];
        $route  = $server['REQUEST_URI'] ?? ($server['argv'][0] ?? 'CLI');
        $status = (int)($server['REDIRECT_STATUS'] ?? ($server['http_response_code'] ?? 200));
        $respBytes = $this->detectResponseSize();

        return [
            'duration_ms'      => $durationMs,
            'sql_count'        => $sqlCount,
            'sql_time_ms'      => $sqlTimeMs,
            'logs_count'       => $logsCount,
            'dumps_count'      => $dumpsCount,
            'memory_peak_mb'   => $peakMb,
            'route'            => $route,
            'status'           => $status,
            'response_bytes'   => $respBytes,
            'response_human'   => $this->humanBytes($respBytes),
        ];
    }

    public function renderHtml(array $d): string
    {
        return $this->kpiRow($d);
        //$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        /*$pill = static fn(string $label, string $val) =>
            '<div class="pill"><span class="pill-label">'.$esc($label).':</span> <b>'.$esc($val).'</b></div>';
        */
        // $pills = [
        //     $this->kpiRow('Duration', number_format((float)($d['duration_ms'] ?? 0), 2) . ' ms'),
        //     $this->pill('SQL', ($d['sql_count'] ?? 0) . ' in ' . number_format((float)($d['sql_time_ms'] ?? 0), 2) . ' ms'),
        //     $this->pill('Logs', (string)($d['logs_count'] ?? 0)),
        //     $this->pill('Dumps', (string)($d['dumps_count'] ?? 0)),
        //     $this->pill('Peak Mem', number_format((float)($d['memory_peak_mb'] ?? 0), 2) . ' MB'),
        //     $this->pill('Status', (string)($d['status'] ?? '')),
        //     $this->pill('Route', (string)($d['route'] ?? '-')),
        //     $this->pill('Resp', (string)($d['response_human'] ?? '-')),
        // ];

        // return '<div class="kpi-wrap">'.implode('', $pills).'</div>';
    }
     private function kpiRow(array $pairs): string
    {
        $out = '';
        foreach ($pairs as $label=>$value) {
    
            $out .= '<div class="mw-kpi"><span class="k">'.$this->e($label).'</span><span class="v">'.$this->e($value).'</span></div>';
        }
        return $out;
    }

    private function lastMarkTs(DebugState $state): ?float
    {
        if (empty($state->marks)) return null;
        $max = null;
        foreach ($state->marks as $m) {
            $t = (float)($m['t'] ?? 0);
            if ($max === null || $t > $max) $max = $t;
        }
        return $max;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B','KB','MB','GB','TB'];
        $i = (int)floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return number_format($bytes / (1024 ** $i), $i === 0 ? 0 : 2) . ' ' . $units[$i];
    }

    /**
     * Try to estimate response size if output buffering is active.
     * If not available, return 0 (UI will show '-').
     */
    private function detectResponseSize(): int
    {
        // If output buffering is on, use length of top buffer.
        $lev = ob_get_level();
        if ($lev > 0) {
            $len = 0;
            // Sum all active buffers to approximate final output size.
            for ($i = 0; $i < $lev; $i++) {
                $buf = ob_get_contents();
                if ($buf !== false) $len += strlen($buf);
                ob_flush(); // flush to next level without ending (safe in dev)
            }
            return $len;
        }
        // Fallback: content length header if set by framework
        $lenHeader = headers_list();
        foreach ($lenHeader as $h) {
            if (stripos($h, 'Content-Length:') === 0) {
                return (int)trim(substr($h, 15));
            }
        }
        return 0;
    }
}
