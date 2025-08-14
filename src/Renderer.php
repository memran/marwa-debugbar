<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

final class Renderer
{
  public function __construct(private DebugBar $debugBar) {}

  public function render(): string
  {
    $payload = $this->debugBar->payload();
    if (!$payload) return '';

    // Optional snapshot
    if (($h = $this->debugBar->history()) && $h->isEnabled()) {
      $id = $h->persist($payload);
      $payload['_history_meta']    = $h->recentMeta();
      $payload['_history_current'] = $id;
    }

    // Header pills
    $elapsed = $this->e((string)($payload['_meta']['elapsed_ms'] ?? '-'));
    $memNow  = $this->e((string)($payload['memory']['usage_mb'] ?? '-'));
    $php     = $this->e((string)($payload['php']['version'] ?? PHP_VERSION));

    // KPIs
    $m = $payload['request_metrics'] ?? [];
    $kpis = $this->kpiRow([
      ['Route',         (string)($m['route'] ?? '-')],
      ['Status',        (string)($m['status'] ?? '-')],
      ['Duration',      isset($m['duration_ms']) ? $m['duration_ms'] . ' ms' : '-'],
      ['SQL',           ($m['queries'] ?? 0) . ' in ' . ($m['queries_time_ms'] ?? 0) . ' ms'],
      ['PeakMem',       ($payload['memory']['peak_usage_mb'] ?? 0) . ' MB'],
      ['Resp',          isset($m['response_bytes']) ? (string)(int)round($m['response_bytes'] / 1024) . ' KB' : '-'],
      ['Logs',          (string)($m['logs'] ?? 0)],
      ['Dumps',         (string)($m['dumps'] ?? 0)],
    ]);

    // Tabs (server-rendered)
    $tabs = [
      'timeline' => ['title' => '⏱ Timeline', 'html' => $this->renderTimeline($payload)],
      'logs'     => ['title' => '📝 Logs',     'html' => $this->renderLogs($payload)],
      'queries'  => ['title' => '🗄 Queries',  'html' => $this->renderQueries($payload)],
      'memory'   => ['title' => '💾 Memory',   'html' => $this->renderMemory($payload)],
      'php'      => ['title' => '🐘 PHP',      'html' => $this->renderPhp($payload)],
      'request'  => ['title' => '🌐 Request',  'html' => $this->renderRequest($payload)],
      'history'  => ['title' => '🕘 History',  'html' => $this->renderHistory($payload)],
    ];
    $tabsList  = $this->tabsList($tabs);
    $tabsViews = $this->tabsViews($tabs);

    return <<<HTML
<style>[x-cloak]{display:none!important}</style>

<!-- DebugBar fixed to bottom; panel expands upward -->
<div class="fixed bottom-0 left-0 right-0 z-[2147483000]" x-data="mwDebugBar()" x-init="restore()" x-cloak>

  <!-- Header bar sitting at the bottom edge -->
  <div class="flex items-center justify-between bg-gray-900 text-white border-t border-gray-800 px-3 py-2 shadow-2xl">
    <div class="flex items-center gap-2">
      <span class="text-xs font-semibold bg-gray-800 border border-gray-700 rounded-full px-2.5 py-1">DebugBar</span>
      <span class="text-[11px] bg-gray-800 border border-gray-700 rounded px-2 py-0.5">Elapsed: <b class="ml-1">{$elapsed} ms</b></span>
      <span class="text-[11px] bg-gray-800 border border-gray-700 rounded px-2 py-0.5">Mem: <b class="ml-1">{$memNow} MB</b></span>
      <span class="text-[11px] bg-gray-800 border border-gray-700 rounded px-2 py-0.5">PHP: <b class="ml-1">{$php}</b></span>
    </div>
    <button @click="toggle(); persist()"
            class="text-xs px-2 py-1 rounded border border-gray-600 bg-gray-800 text-white hover:bg-gray-700">
      <span x-show="open">Close</span>
      <span x-cloak x-show="!open">Open</span>
    </button>
  </div>

  <!-- Upward-opening panel: absolutely positioned ABOVE the header -->
  <div class="absolute left-0 right-0 bottom-[40px] sm:bottom-[40px]" 
       x-show="open" 
       x-collapse.duration.250ms 
       x-transition.opacity 
       x-cloak>
    <div class="mx-2 sm:mx-4 mb-3 bg-gray-900 text-gray-100 border border-gray-800 rounded-xl shadow-2xl overflow-hidden">

      <!-- Body with max height and internal scroll; grows UPWARDS because it's anchored to bottom -->
      <div class="grid grid-cols-[180px_1fr] max-h-[60vh]">

        <!-- Sidebar tabs -->
        <div class="bg-gray-800 border-r border-gray-700 overflow-y-auto">
          {$tabsList}
        </div>

        <!-- Content area -->
        <div class="bg-gray-900 p-3 overflow-y-auto">
          <!-- KPI grid -->
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 mb-3">
            {$kpis}
          </div>

          <!-- Active tab views -->
          {$tabsViews}
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Load collapse BEFORE Alpine to avoid registration race -->
<script defer src="https://unpkg.com/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
  function mwDebugBar(){
    return {
      open: false,
      active: 'timeline',
      toggle(){ this.open = !this.open; },
      set(tab){ this.active = tab; this.persist(); },
      is(tab){ return this.active === tab; },
      persist(){
        try{ localStorage.setItem('mwDebugBar', JSON.stringify({open:this.open, active:this.active})); }catch(e){}
      },
      restore(){
        try{
          const s = JSON.parse(localStorage.getItem('mwDebugBar') || '{}');
          if (typeof s.open === 'boolean') this.open = s.open;
          if (typeof s.active === 'string' && s.active) this.active = s.active;
        }catch(e){}
      }
    }
  }
</script>
HTML;
  }

  /* ========================= Sections ========================= */

  private function renderTimeline(array $p): string
  {
    $spans = $p['timeline_spans'] ?? null;
    $marks = $p['timeline'] ?? null;

    if (is_array($spans) && $spans) {
      $total = 0.0;
      foreach ($spans as $s) {
        $end = (float)($s['start_ms'] ?? 0) + (float)($s['duration_ms'] ?? 0);
        if ($end > $total) $total = $end;
      }
      $summary = $this->timelineSummary(count($spans), $total);

      $rows = '';
      foreach ($spans as $s) {
        $rows .= $this->tr([
          $this->e((string)($s['label'] ?? '')),
          $this->num((float)($s['start_ms'] ?? 0)) . ' ms',
          $this->num((float)($s['duration_ms'] ?? 0)) . ' ms',
          $this->e((string)($s['depth'] ?? 0)),
        ], [null, 'right', 'right', 'right']);
      }

      return $summary . $this->table(['Label', 'Start', 'Duration', 'Depth'], $rows, ['left', 'right', 'right', 'right']);
    }

    if (is_array($marks) && $marks) {
      $summary = $this->timelineSummary(count($marks), null);
      $rows = '';
      foreach ($marks as $m) {
        $rows .= $this->tr([
          $this->e((string)($m['label'] ?? '')),
          $this->num((float)($m['t'] ?? 0), 5) . ' s',
        ], [null, 'right']);
      }
      return $summary . $this->table(['Label', 'Seconds'], $rows, ['left', 'right']);
    }

    return '<div class="text-gray-400">No timeline data.</div>';
  }

  private function timelineSummary(int $count, ?float $totalMs): string
  {
    $total = $totalMs !== null ? $this->num($totalMs) . ' ms' : '-';
    return <<<HTML
<div class="flex flex-wrap items-center gap-2 mb-2">
  <span class="text-[11px] bg-gray-800 border border-gray-700 rounded px-2 py-0.5">Spans: <b class="ml-1">{$this->e((string)$count)}</b></span>
  <span class="text-[11px] bg-gray-800 border border-gray-700 rounded px-2 py-0.5">Total: <b class="ml-1">{$total}</b></span>
</div>
HTML;
  }

  private function renderLogs(array $p): string
  {
    $logs = $p['logs'] ?? [];
    if (!$logs) return '<div class="text-gray-400">No logs.</div>';

    $rows = '';
    foreach ($logs as $l) {
      $rows .= $this->tr([
        $this->num((float)($l['time'] ?? 0)) . ' ms',
        '<span class="text-[11px] px-2 py-0.5 rounded bg-gray-800 border border-gray-700">' . $this->e((string)($l['level'] ?? '')) . '</span>',
        $this->e((string)($l['message'] ?? '')),
        '<code class="text-[12px]">' . $this->e(json_encode($l['context'] ?? [], JSON_UNESCAPED_SLASHES)) . '</code>',
      ], ['right', null, null, null]);
    }

    return $this->table(['Time', 'Level', 'Message', 'Context'], $rows, ['right', null, null, null]);
  }

  private function renderQueries(array $p): string
  {
    $qs = $p['queries'] ?? [];
    if (!$qs) return '<div class="text-gray-400">No queries.</div>';

    $rows = '';
    foreach ($qs as $q) {
      $rows .= $this->tr([
        '<code class="text-[12px]">' . $this->e((string)($q['sql'] ?? '')) . '</code>',
        '<code class="text-[12px]">' . $this->e(json_encode($q['params'] ?? [], JSON_UNESCAPED_SLASHES)) . '</code>',
        $this->num((float)($q['duration_ms'] ?? 0)) . ' ms',
        $this->e((string)($q['connection'] ?? '')),
      ], [null, null, 'right', null]);
    }

    return $this->table(['SQL', 'Params', 'Duration', 'Conn'], $rows, [null, null, 'right', null]);
  }

  private function renderMemory(array $p): string
  {
    $m = $p['memory'] ?? [];
    $cards = [
      $this->stat('Usage', $this->num((float)($m['usage_mb'] ?? 0)) . ' MB'),
      $this->stat('Peak',  $this->num((float)($m['peak_usage_mb'] ?? 0)) . ' MB'),
      $this->stat('Limit', '<code class="text-[12px]">' . $this->e((string)($m['limit'] ?? '')) . '</code>'),
    ];
    return '<div class="grid grid-cols-1 md:grid-cols-3 gap-2">' . implode('', $cards) . '</div>';
  }

  private function renderPhp(array $p): string
  {
    $php = $p['php'] ?? [];
    $exts = '';
    foreach ((array)($php['extensions'] ?? []) as $ex) {
      $exts .= '<span class="text-[11px] px-2 py-0.5 mr-1 mb-1 rounded bg-gray-800 border border-gray-700 inline-block">' . $this->e((string)$ex) . '</span>';
    }
    return <<<HTML
<div class="grid grid-cols-1 md:grid-cols-3 gap-2 mb-3">
  {$this->stat('Version',$this->e((string)($php['version'] ?? '')))}
  {$this->stat('Opcache', !empty($php['opcache_enabled']) ? 'Yes' : 'No')}
</div>
<div>
  <div class="text-xs text-gray-400 mb-1">Extensions</div>
  <div>{$exts}</div>
</div>
HTML;
  }

  private function renderRequest(array $p): string
  {
    $r = $p['request'] ?? [];
    $rows = [
      ['Method', $this->e((string)($r['method'] ?? ''))],
      ['URI',    '<code class="text-[12px]">' . $this->e((string)($r['uri'] ?? '')) . '</code>'],
      ['IP',     $this->e((string)($r['ip'] ?? ''))],
      ['User Agent', '<code class="text-[12px]">' . $this->e((string)($r['ua'] ?? '')) . '</code>'],
      ['Headers', $this->preJson($r['headers'] ?? [])],
      ['GET',     $this->preJson($r['get'] ?? [])],
      ['POST',    $this->preJson($r['post'] ?? [])],
      ['Cookies', $this->preJson($r['cookies'] ?? [])],
      ['Files',   $this->preJson($r['files'] ?? [])],
    ];

    $trs = '';
    foreach ($rows as [$k, $v]) {
      $trs .= '<tr class="border-b border-gray-800"><th class="py-1.5 pr-3 text-left text-gray-300 w-28 align-top">' . $this->e($k) . '</th><td class="py-1.5">' . $v . '</td></tr>';
    }
    return '<table class="min-w-full text-left"><tbody class="text-sm">' . $trs . '</tbody></table>';
  }

  private function renderHistory(array $p): string
  {
    $meta = $p['_history_meta'] ?? [];
    if (!$meta) return '<div class="text-gray-400">No history stored.</div>';

    $rows = '';
    foreach ($meta as $i => $m) {
      $rows .= $this->tr([
        (string)($i + 1),
        '<code class="text-[12px]">' . $this->e((string)($m['id'] ?? '')) . '</code>',
        $this->e((string)($m['ts'] ?? '')),
        isset($m['elapsed_ms']) ? $this->num((float)$m['elapsed_ms']) . ' ms' : '',
        (string)($m['size'] ?? ''),
      ], ['right', null, null, 'right', 'right']);
    }
    return $this->table(['#', 'ID', 'Timestamp', 'Elapsed', 'Size (B)'], $rows, ['right', null, null, 'right', 'right']);
  }

  /* ========================= UI helpers ========================= */

  private function tabsList(array $tabs): string
  {
    $out = '';
    foreach ($tabs as $key => $def) {
      $label = $this->e($def['title']);
      $k = $this->jsKey($key);
      $out .= <<<HTML
<button @click="set('{$k}')"
        :class="is('{$k}') ? 'bg-gray-700 text-white' : 'text-gray-200 hover:bg-gray-700/60'"
        class="w-full text-left px-3 py-2 border-b border-gray-700/70">
  {$label}
</button>
HTML;
    }
    return $out;
  }

  private function tabsViews(array $tabs): string
  {
    $out = '';
    foreach ($tabs as $key => $def) {
      $k = $this->jsKey($key);
      $out .= <<<HTML
<div x-show="is('{$k}')" x-transition.opacity.duration.120ms x-cloak>
  {$def['html']}
</div>
HTML;
    }
    return $out;
  }

  private function table(array $headers, string $rowsHtml, array $align): string
  {
    $ths = '';
    foreach ($headers as $i => $h) {
      $alignCls = ($align[$i] ?? null) === 'right' ? ' text-right' : ' text-left';
      $ths .= '<th class="py-1.5 px-2 text-xs font-medium uppercase text-gray-400' . $alignCls . '">' . $this->e($h) . '</th>';
    }
    return <<<HTML
<table class="min-w-full text-sm border border-gray-800 rounded overflow-hidden">
  <thead class="bg-gray-800/70 border-b border-gray-800">
    <tr>{$ths}</tr>
  </thead>
  <tbody class="divide-y divide-gray-800">{$rowsHtml}</tbody>
</table>
HTML;
  }

  private function tr(array $cells, array $align): string
  {
    $tds = '';
    foreach ($cells as $i => $c) {
      $a = ($align[$i] ?? null) === 'right' ? 'text-right' : 'text-left';
      $tds .= '<td class="py-1.5 px-2 ' . $a . '">' . $c . '</td>';
    }
    return '<tr>' . $tds . '</tr>';
  }

  private function stat(string $label, string $value): string
  {
    return '<div class="bg-gray-800/70 border border-gray-700 rounded px-3 py-2"><div class="text-xs text-gray-400">' . $this->e($label) . '</div><div class="font-semibold">' . $value . '</div></div>';
  }

  private function kpiRow(array $pairs): string
  {
    $out = '';
    foreach ($pairs as [$label, $value]) {
      $out .= '<div class="flex items-center justify-between bg-gray-800/70 border border-gray-700 rounded px-3 py-2">'
        . '<span class="text-xs text-gray-300">' . $this->e($label) . '</span>'
        . '<span class="text-xs font-semibold">' . $this->e((string)$value) . '</span>'
        . '</div>';
    }
    return $out;
  }

  private function preJson(array $a): string
  {
    $j = $this->e(json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return '<pre class="bg-gray-950 p-2 rounded border border-gray-800 overflow-auto text-[12px]">' . $j . '</pre>';
  }

  private function num(float $n, int $dec = 2): string
  {
    return number_format($n, $dec, '.', '');
  }

  private function e(?string $s): string
  {
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
  }

  private function jsKey(string $s): string
  {
    return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $s) ?: 'tab';
  }
}
