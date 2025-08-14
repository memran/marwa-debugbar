<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Plugins;

final class HeuristicsPlugin extends AbstractPlugin
{
    public function name(): string
    {
        return 'heuristics';
    }

    public function extendPayload(array $payload): array
    {
        $m = $payload['request_metrics'] ?? [];
        $issues = [];

        $slowReqMs      = 500.0;
        $manyQueries    = 30;
        $highQueryTime  = 200.0;
        $highMemoryMb   = 128.0;
        $largeRespBytes = 1_000_000;

        if (($m['duration_ms'] ?? 0) > $slowReqMs)
            $issues[] = ['severity' => 'high', 'code' => 'SLOW_REQUEST', 'msg' => "Slow request: {$m['duration_ms']} ms > {$slowReqMs} ms"];
        if (($m['queries'] ?? 0) > $manyQueries)
            $issues[] = ['severity' => 'med', 'code' => 'MANY_QUERIES', 'msg' => "Too many queries: {$m['queries']} > {$manyQueries}"];
        if (($m['queries_time_ms'] ?? 0) > $highQueryTime)
            $issues[] = ['severity' => 'med', 'code' => 'QUERY_TIME_HIGH', 'msg' => "Total query time high: {$m['queries_time_ms']} ms > {$highQueryTime} ms"];
        if (($m['memory_peak_mb'] ?? 0) > $highMemoryMb)
            $issues[] = ['severity' => 'med', 'code' => 'MEMORY_PEAK_HIGH', 'msg' => "High peak memory: {$m['memory_peak_mb']} MB > {$highMemoryMb} MB"];
        if (($m['response_bytes'] ?? 0) > $largeRespBytes) {
            $kb = (int) round(($m['response_bytes'] ?? 0) / 1024);
            $issues[] = ['severity' => 'low', 'code' => 'LARGE_RESPONSE', 'msg' => "Large response: {$kb} KB > " . (int)($largeRespBytes / 1024) . " KB"];
        }
        if (($payload['php']['opcache_enabled'] ?? false) === false)
            $issues[] = ['severity' => 'low', 'code' => 'OPCACHE_DISABLED', 'msg' => 'OPcache disabled; enable in production.'];

        return ['heuristics' => [
            'issues' => $issues,
            'score' => $this->score($issues),
            'route' => $m['route'] ?? null,
            'status' => $m['status'] ?? null,
            'duration_ms' => $m['duration_ms'] ?? null,
        ]];
    }

    public function tabs(): array
    {
        $renderer = <<<'JS'
function renderHeuristics(d) {
  const h = d.heuristics||{issues:[]};
  if (!h.issues.length) return `<div>✅ No major issues detected for this request.</div>`;
  const color = s => s==='high'?'badge-error':(s==='med'?'badge-info':'badge-info');
  const rows = h.issues.map((it,i)=>`
    <tr>
      <td>${i+1}</td>
      <td><span class="badge ${color(it.severity)}">${it.severity.toUpperCase()}</span></td>
      <td class="mw-mono">${escapeHtml(it.code)}</td>
      <td>${escapeHtml(it.msg)}</td>
    </tr>`).join('');
  return `<div style="margin-bottom:8px">Route: <code class="mw-mono">${escapeHtml(h.route||'')}</code> ·
    Status: <b>${h.status??''}</b> · Duration: <b>${h.duration_ms??''} ms</b> · Score: <b>${h.score??''}</b></div>
    <table class="mw"><thead><tr><th>#</th><th>Severity</th><th>Code</th><th>Message</th></tr></thead><tbody>${rows}</tbody></table>`;
}
JS;
        return [['key' => 'heuristics', 'title' => 'Profiler', 'icon' => '🔥', 'order' => 105, 'renderer' => $renderer]];
    }

    private function score(array $issues): int
    {
        $score = 100;
        foreach ($issues as $i) $score -= match ($i['severity']) {
            'high' => 25,
            'med' => 15,
            default => 5
        };
        return max(0, $score);
    }
}
