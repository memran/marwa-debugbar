<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

/**
 * TimelineCollector
 *
 * Renders a developer-friendly timeline of your DebugBar marks:
 * - Absolute timestamp (HH:MM:SS.mmm)
 * - Relative time from request start (ms)
 * - Delta from previous mark (ms)
 * - % of total elapsed
 * - Mini bar proportional to total request time
 *
 * Usage:
 *   $bar->mark('bootstrap');
 *   $bar->mark('controller');
 *   $bar->mark('view');
 */
final class TimelineCollector implements Collector
{
    use HtmlKit;

    public static function key(): string   { return 'timeline'; }
    public static function label(): string { return 'Timeline'; }
    public static function icon(): string  { return '⏱️'; }
    public static function order(): int    { return 110; }

    /** @inheritDoc */
    public function collect(DebugState $state): array
    {
        $marks = $state->marks; // [ ['t'=>float, 'label'=>string], ... ]
        if (empty($marks)) {
            return [
                'total_ms' => 0.0,
                'rows' => [],
            ];
        }

        // Sort by t ascending to ensure consistent order
        usort($marks, static fn(array $a, array $b) => ($a['t'] <=> $b['t']));

        $start = $state->requestStart;
        $rows  = [];
        $prevT = null;

        foreach ($marks as $i => $m) {
            $tAbs = (float)($m['t'] ?? microtime(true));
            $label = (string)($m['label'] ?? ('Mark #'.($i+1)));

            $relMs   = round(($tAbs - $start) * 1000, 2);
            $deltaMs = $prevT === null ? 0.0 : round(($tAbs - $prevT) * 1000, 2);
            $rows[] = [
                'i'       => $i + 1,
                'label'   => $label,
                'abs'     => $this->formatAbs($tAbs),
                'rel_ms'  => $relMs,
                'delta_ms'=> $deltaMs,
                't'       => $tAbs,
            ];
            $prevT = $tAbs;
        }

        $totalMs = (float)end($rows)['rel_ms'];
        // Compute percentage of total for the last column bar
        foreach ($rows as &$r) {
            $r['pct'] = $totalMs > 0 ? round(($r['rel_ms'] / $totalMs) * 100, 2) : 0.0;
        }
        unset($r);

        return [
            'total_ms' => $totalMs,
            'rows'     => $rows,
        ];
    }

    /** @inheritDoc */
    public function renderHtml(array $data): string
    {
        
        $rows = $data['rows'] ?? [];
        $total = (float)($data['total_ms'] ?? 0.0);

       
        //$esc = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');

        if (empty($rows)) {
            return '<div>No timeline marks recorded. Call <code class="mw-mono">$bar->mark("label")</code> during your request.</div>';
        }

        $thead = <<<HTML
<thead>
  <tr>
    <th style="width:48px">#</th>
    <th>Label</th>
    <th style="width:140px">Time (abs)</th>
    <th style="width:120px">T+ (ms)</th>
    <th style="width:120px">Δ prev (ms)</th>
    <th style="width:30%">Progress</th>
  </tr>
</thead>
HTML;

        $trs = [];
        
        foreach ($rows as $r) {
            $barWidth = max(0.5, min(100.0, (float)$r['pct']));
            $bar = '<div style="position:relative;height:16px;background:#0F172A;border:1px solid #1F2937;border-radius:6px;overflow:hidden">'
                 . '<div style="position:absolute;left:0;top:0;bottom:0;width:'.$this->e($barWidth).'%'
                 . ';background:#2563EB;border-right:1px solid #1E40AF"></div>'
                 . '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;'
                 . 'font-size:11px;color:#EAF2FF;mix-blend:normal">'.$this->e($r['pct']).'%</div>'
                 . '</div>';

            $trs[] = '<tr>'
                . '<td>'.$this->e($r['i']).'</td>'
                . '<td >'.$this->e($r['label']).'</td>'
                . '<td >'.$this->e($r['abs']).'</td>'
                . '<td>'.$this->e($this->num((float)$r['rel_ms'], 2)).'</td>'
                . '<td>'.$this->e($this->num((float)$r['delta_ms'], 2)).'</td>'
                . '<td>'.$bar.'</td>'
                . '</tr>';
        }

        //$totalPill = '<div class="pill"><span class=mw-chip>Total: <b>'.$esc(number_format($total, 2)).' ms</b></span></div>';
        $totalPill = $this->timelineSummary(count($trs),$total);
        return str_replace('{ROWS}', implode('', $trs), <<<HTML
{$totalPill}
<table class="mw-table">
  {$thead}
  <tbody>
    {ROWS}
  </tbody>
</table>
HTML
);
    }

    private function formatAbs(float $t): string
    {
        $dt = \DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', $t));
        if ($dt === false) {
            // Fallback: seconds resolution
            return date('H:i:s', (int)$t);
        }
        return $dt->format('H:i:s.v');
    }

    private function timelineSummary(int $count, ?float $totalMs): string
    {
        $total = $totalMs !== null ? $this->num($totalMs).' ms' : '-';
        return <<<HTML
<div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px">
  <span class="mw-chip">Spans: <b style="margin-left:6px">{$this->e((string)$count)}</b></span>
  <span class="mw-chip">Total: <b style="margin-left:6px">{$total}</b></span>
</div>
HTML;
    }
}
