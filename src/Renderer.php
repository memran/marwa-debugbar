<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

final class Renderer
{
    public function __construct(private DebugBar $debugBar) {}

    public function render(): string
    {

       
        $p = $this->debugBar->payload();
        if (!$p || !is_array($p)) {
            return '<div style="padding:10px;color:red">DebugBar: No payload data</div>';
        }

       
        // Optional history snapshot
        if (($h = $this->debugBar->history()) && $h->isEnabled()) {
            $id = $h->persist($p);
            $p['_history_meta']    = $h->recentMeta();
            $p['_history_current'] = $id;
        }

        // Header pills
        $elapsed = $this->e((string)($p['_meta']['elapsed_ms'] ?? '-'));
        $memNow  = $this->e((string)($p['memory']['usage_mb'] ?? '-'));
        $phpVer  = $this->e((string)($p['php']['version'] ?? PHP_VERSION));

        // KPI row
        $m = $p['request_metrics'] ?? [];
        $kpis = $this->kpiRow([
            ['Route',         (string)($m['route'] ?? '-')],
            ['Status',        (string)($m['status'] ?? '-')],
            ['Duration',      isset($m['duration_ms']) ? $m['duration_ms'].' ms' : '-'],
            ['SQL',           ($m['queries'] ?? 0).' in '.($m['queries_time_ms'] ?? 0).' ms'],
            ['PeakMem',       ($p['memory']['peak_usage_mb'] ?? 0).' MB'],
            ['Resp',          isset($m['response_bytes']) ? (string)(int)round($m['response_bytes']/1024).' KB' : '-'],
            ['Logs',          (string)($m['logs'] ?? 0)],
            ['Dumps',         (string)($m['dumps'] ?? 0)],
        ]);

        // Tabs
        $tabs = [
            'timeline' => ['title' => '⏱ Timeline',   'html' => $this->renderTimeline($p)],
            'dumper'   => ['title' => '🧪 Dumper',  'html' => $this->renderVarDumper($p), 'scroll' => true],
            'logs'     => ['title' => '📝 Logs',       'html' => $this->renderLogs($p)],
            'queries'  => ['title' => '🗄 Queries',    'html' => $this->renderQueries($p)],
            'memory'   => ['title' => '💾 Memory',     'html' => $this->renderMemory($p)],
            'php'      => ['title' => '🐘 PHP',        'html' => $this->renderPhp($p)],
            'request'  => ['title' => '🌐 Request',    'html' => $this->renderRequest($p),   'scroll' => true],
            'session'  => ['title' => '🔐 Session',    'html' => $this->renderSession($p),   'scroll' => true],
            'env'      => ['title' => '⚙️ Env & Server','html' => $this->renderEnvServer($p),'scroll' => true],
            'history'  => ['title' => '🕘 History',    'html' => $this->renderHistory($p),   'scroll' => true],
        ];

        $tabsNav   = $this->tabsNav($tabs);
        $tabsPanel = $this->tabsPanels($tabs);
        

        return <<<HTML
<style>
/* ======== Minimal Dark Theme (Vanilla CSS) ======== */
:root {
  --mw-bg: #0b1220;
  --mw-bg-2: #0f172a;
  --mw-card: #111827;
  --mw-border: #1f2937;
  --mw-text: #e5e7eb;
  --mw-text-dim: #9ca3af;
  --mw-accent: #2a3a5e;
  --mw-pill: #18243b;
  --mw-badge: #223253;
  --mw-shadow: 0 10px 30px rgba(0,0,0,.45);
}

#mwdbg-root { position: fixed; left: 0; right: 0; bottom: 0; z-index: 2147483000; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji"; color: var(--mw-text); }
#mwdbg-root * { box-sizing: border-box; }
/*.mw-cloak { display: none !important; }*/
.mw-cloak {
  display: none !important;
}
/* Header bar */
.mw-header { display: flex; align-items: center; justify-content: space-between; padding: 8px 12px; background: var(--mw-bg-2); border-top: 1px solid var(--mw-border); box-shadow: var(--mw-shadow); }
.mw-badge { font-size: 12px; background: var(--mw-pill); border: 1px solid var(--mw-border); border-radius: 9999px; padding: 2px 10px; color: var(--mw-text); }
.mw-chip { font-size: 11px; background: var(--mw-pill); border: 1px solid var(--mw-border); border-radius: 6px; padding: 1px 8px; color: var(--mw-text); }
.mw-btn { font-size: 12px; padding: 4px 10px; border-radius: 6px; background: var(--mw-card); color: var(--mw-text); border: 1px solid var(--mw-border); cursor: pointer; }
.mw-btn:hover { background: #1a2338; }

/* Panel up (absolute, opens upward) */
.mw-panel-wrap { position: absolute; left: 0; right: 0; bottom: 40px; padding: 0 8px 8px; }
.mw-panel { background: var(--mw-bg); border: 1px solid var(--mw-border); border-radius: 12px; box-shadow: var(--mw-shadow); overflow: hidden; }

/* Panel collapse animation */
.mw-collapse { overflow: clip; transition: max-height .25s ease, opacity .2s ease; opacity: 1; }
.mw-collapse.closed { max-height: 0 !important; opacity: 0; }
.mw-collapse.open { max-height: 80vh; }

/* Grid */
.mw-grid { display: grid; grid-template-columns: 180px 1fr; max-height: 60vh; }
.mw-sidebar { background: var(--mw-bg-2); border-right: 1px solid var(--mw-border); overflow-y: auto; }
.mw-content { background: var(--mw-bg); padding: 12px; overflow-y: auto; }

/* Sidebar tabs */
.mw-tab-btn { width: 100%; text-align: left; padding: 10px 12px; color: var(--mw-text); background: transparent; border: 0; border-bottom: 1px solid rgba(255,255,255,.05); cursor: pointer; }
.mw-tab-btn:hover { background: rgba(255,255,255,.06); }
.mw-tab-btn.active { background: #273149; }

/* KPI grid */
.mw-kpi-grid { display: grid; grid-template-columns: repeat(6, minmax(0,1fr)); gap: 8px; margin-bottom: 10px; }
@media (max-width: 1024px) { .mw-kpi-grid { grid-template-columns: repeat(3, minmax(0,1fr)); } }
@media (max-width: 640px)  { .mw-kpi-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
.mw-kpi { display:flex; align-items:center; justify-content:space-between; background: var(--mw-card); border:1px solid var(--mw-border); border-radius: 8px; padding: 8px 10px; }
.mw-kpi .k { color: var(--mw-text-dim); font-size: 12px; }
.mw-kpi .v { color: var(--mw-text); font-weight: 600; font-size: 12px; }

/* Cards & sections */
.mw-card { background: var(--mw-card); border: 1px solid var(--mw-border); border-radius: 10px; }
.mw-card-h { padding: 8px 10px; border-bottom: 1px solid var(--mw-border); font-weight: 600; font-size: 14px; }
.mw-card-b { padding: 10px; }

/* Table */
.mw-table { width: 100%; border: 1px solid var(--mw-border); border-radius: 8px; border-collapse: collapse; overflow: hidden; }
.mw-table thead th { background: #101a30; color: var(--mw-text-dim); text-transform: uppercase; font-size: 12px; padding: 8px; border-bottom: 1px solid var(--mw-border); text-align: left; }
.mw-table td { padding: 8px; border-bottom: 1px solid var(--mw-border); }
.mw-table tr:last-child td { border-bottom: 0; }
.mw-right { text-align: right; }
.mw-pre { background: #0a0f1d; border:1px solid var(--mw-border); padding:8px; border-radius:8px; overflow:auto; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size: 12px; }

/* Utilities */
.mw-badgel { display:inline-block; font-size: 11px; padding: 2px 8px; background: var(--mw-badge); border:1px solid var(--mw-border); border-radius: 9999px; }
.mw-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; font-size: 12px; }
.mw-grid-2 { display:grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.mw-grid-3 { display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
@media (max-width: 900px) { .mw-grid-3 { grid-template-columns: 1fr; } .mw-grid { grid-template-columns: 150px 1fr; } }
@media (max-width: 700px) { .mw-grid-2 { grid-template-columns: 1fr; } }

/* Cloak removal on init */
#mwdbg-root.ready { display: initial !important; }
</style>

<div id="mwdbg-root" class="mw-cloak">
  <!-- Header -->
  <div class="mw-header">
    <div style="display:flex;align-items:center;gap:8px">
      <span class="mw-badge">DebugBar</span>
      <span class="mw-chip">Elapsed: <b style="margin-left:6px">{$elapsed} ms</b></span>
      <span class="mw-chip">Mem: <b style="margin-left:6px">{$memNow} MB</b></span>
      <span class="mw-chip">PHP: <b style="margin-left:6px">{$phpVer}</b></span>
    </div>
    <button id="mw-btn-toggle" class="mw-btn" type="button" aria-expanded="false">Open</button>
  </div>

  <!-- Panel above header -->
  <div class="mw-panel-wrap">
    <div id="mw-collapse" class="mw-panel mw-collapse closed" aria-hidden="true">
      <div class="mw-grid">
        <!-- Sidebar -->
        <nav id="mw-tabs" class="mw-sidebar" role="tablist" aria-label="DebugBar tabs">
          {$tabsNav}
        </nav>
        <!-- Content -->
        <section class="mw-content">
          <!-- KPIs -->
          <div class="mw-kpi-grid">{$kpis}</div>
          <!-- Panels -->
          <div id="mw-panels">
            {$tabsPanel}
          </div>
        </section>
      </div>
    </div>
  </div>
</div>

<script>
/* ======== Vanilla JS controller ======== */
(function(){
  const root      = document.getElementById('mwdbg-root');
  const btnToggle = document.getElementById('mw-btn-toggle');
  const collapse  = document.getElementById('mw-collapse');
  const tabsWrap  = document.getElementById('mw-tabs');
  const panels    = document.getElementById('mw-panels');

  if(!root || !btnToggle || !collapse || !tabsWrap || !panels) return;

  // State (restore)
  const KEY = 'mwDebugBar';
  let state = { open: false, active: 'timeline' };
  //btnToggle.addEventListener("click",()=> console.log('button Clicked'));

  try {
    const saved = JSON.parse(localStorage.getItem(KEY) || '{}');
    if (typeof saved.open === 'boolean') state.open = saved.open;
    if (typeof saved.active === 'string' && saved.active) state.active = saved.active;
  } catch(e) {}

  // Init UI
  function persist(){ try{ localStorage.setItem(KEY, JSON.stringify(state)); }catch(e){} }
  function setOpen(open){
    state.open = open;
    collapse.classList.toggle('open', open);
    collapse.classList.toggle('closed', !open);
    collapse.setAttribute('aria-hidden', String(!open));
    btnToggle.textContent = open ? 'Close' : 'Open';
    btnToggle.setAttribute('aria-expanded', String(open));
    persist();
  }
  function switchTab(key){
    state.active = key;
    // sidebar
    tabsWrap.querySelectorAll('[role="tab"]').forEach(el=>{
      const k = el.getAttribute('data-key');
      const sel = k===key;
      el.classList.toggle('active', sel);
      el.setAttribute('aria-selected', String(sel));
      el.tabIndex = sel ? 0 : -1;
    });
    // panels
    panels.querySelectorAll('[role="tabpanel"]').forEach(el=>{
      const k = el.getAttribute('data-key');
      const show = k===key;
      el.style.display = show ? '' : 'none';
      el.setAttribute('aria-hidden', String(!show));
    });
    persist();
  }

  // Build dynamic height (optional: we keep CSS max-height; JS not required)
  btnToggle.addEventListener('click', ()=> setOpen(!state.open));
 

  // Sidebar tab click + keyboard
  tabsWrap.addEventListener('click', (e)=>{
    const btn = e.target.closest('[role="tab"]');
    if(!btn) return;
    switchTab(btn.getAttribute('data-key'));
  });
  tabsWrap.addEventListener('keydown', (e)=>{
    const tabs = Array.from(tabsWrap.querySelectorAll('[role="tab"]'));
    if(!tabs.length) return;
    const idx = tabs.findIndex(t => t.getAttribute('data-key') === state.active);
    let next = idx;
    if(e.key === 'ArrowDown' || e.key === 'ArrowRight') next = (idx+1) % tabs.length;
    else if(e.key === 'ArrowUp' || e.key === 'ArrowLeft') next = (idx-1+tabs.length) % tabs.length;
    else if(e.key === 'Home') next = 0;
    else if(e.key === 'End') next = tabs.length-1;
    else return;
    e.preventDefault();
    const key = tabs[next].getAttribute('data-key');
    switchTab(key);
    tabs[next].focus();
  });

  // Ready
  setOpen(state.open);
  switchTab(state.active);
  root.classList.add('ready');
})();
</script>
HTML;


    }

    /* ========================= Sections ========================= */

    private function renderVarDumper(array $p): string
    {
        $dumps = $p['dumps'] ?? [];
        if (empty($dumps)) {
            return '<div class="mw-text-dim">No dumps collected</div>';
        }

        $html = '';
        foreach ($dumps as $i => $dump) {
            $title = htmlspecialchars($dump['name'] ?? 'Dump #'.($i + 1));
            $time = isset($dump['time']) ? round($dump['time'] * 1000, 2).' ms' : '';
            
            $html .= <<<HTML
    <div class="mw-card" style="margin-bottom: 15px;">
        <div class="mw-card-h">{$title} <small style="color: #999;">{$time}</small></div>
        <div class="mw-card-b">{$dump['html']}</div>
    </div>
    HTML;
        }

        return $html;
    }

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
                    $this->num((float)($s['start_ms'] ?? 0)).' ms',
                    $this->num((float)($s['duration_ms'] ?? 0)).' ms',
                    $this->e((string)($s['depth'] ?? 0)),
                ], [null,'right','right','right']);
            }

            return $summary . $this->table(['Label','Start','Duration','Depth'], $rows, ['left','right','right','right']);
        }

        if (is_array($marks) && $marks) {
            $summary = $this->timelineSummary(count($marks), null);
            $rows = '';
            foreach ($marks as $m) {
                $rows .= $this->tr([
                    $this->e((string)($m['label'] ?? '')),
                    $this->num((float)($m['t'] ?? 0), 5).' s',
                ], [null,'right']);
            }
            return $summary . $this->table(['Label','Seconds'], $rows, ['left','right']);
        }

        return '<div style="color:#9ca3af">No timeline data.</div>';
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

    private function renderLogs(array $p): string
    {
        $logs = $p['logs'] ?? [];
        if (!$logs) return '<div style="color:#9ca3af">No logs.</div>';

        $rows = '';
        foreach ($logs as $l) {
            $rows .= $this->tr([
                $this->num((float)($l['time'] ?? 0)).' ms',
                '<span class="mw-badgel">'.$this->e((string)($l['level'] ?? '')).'</span>',
                $this->e((string)($l['message'] ?? '')),
                '<span class="mw-mono">'. $this->e(json_encode($l['context'] ?? [], JSON_UNESCAPED_SLASHES)) .'</span>',
            ], ['right',null,null,null]);
        }

        return $this->table(['Time','Level','Message','Context'], $rows, ['right',null,null,null]);
    }

    private function renderQueries(array $p): string
    {
        $qs = $p['queries'] ?? [];
        if (!$qs) return '<div style="color:#9ca3af">No queries.</div>';

        $rows = '';
        foreach ($qs as $q) {
            $rows .= $this->tr([
                '<span class="mw-mono">'.$this->e((string)($q['sql'] ?? '')).'</span>',
                '<span class="mw-mono">'. $this->e(json_encode($q['params'] ?? [], JSON_UNESCAPED_SLASHES)) .'</span>',
                $this->num((float)($q['duration_ms'] ?? 0)).' ms',
                $this->e((string)($q['connection'] ?? '')),
            ], [null,null,'right',null]);
        }

        return $this->table(['SQL','Params','Duration','Conn'], $rows, [null,null,'right',null]);
    }

    private function renderMemory(array $p): string
    {
        $m = $p['memory'] ?? [];
        $cards = [
            $this->stat('Usage', $this->num((float)($m['usage_mb'] ?? 0)).' MB'),
            $this->stat('Peak',  $this->num((float)($m['peak_usage_mb'] ?? 0)).' MB'),
            $this->stat('Limit', '<span class="mw-mono">'.$this->e((string)($m['limit'] ?? '')).'</span>'),
        ];
        return '<div class="mw-grid-3">'.implode('', $cards).'</div>';
    }

    private function renderPhp(array $p): string
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

    private function renderRequest(array $p): string
    {
        $content = $this->renderRequestTable($p);
        return '<div style="max-height:45vh; overflow:auto">'.$content.'</div>';
    }

    private function renderRequestTable(array $p): string
    {
        $r = $p['request'] ?? [];
        $rows = [
            ['Method', $this->e((string)($r['method'] ?? ''))],
            ['URI',    '<span class="mw-mono">'.$this->e((string)($r['uri'] ?? '')).'</span>'],
            ['IP',     $this->e((string)($r['ip'] ?? ''))],
            ['User Agent', '<span class="mw-mono">'.$this->e((string)($r['ua'] ?? '')).'</span>'],
            ['Headers', $this->preJson($r['headers'] ?? [])],
            ['GET',     $this->preJson($r['get'] ?? [])],
            ['POST',    $this->preJson($r['post'] ?? [])],
            ['Cookies', $this->preJson($r['cookies'] ?? [])],
            ['Files',   $this->preJson($r['files'] ?? [])],
            ['Server',  $this->preJson($r['server'] ?? [])],
        ];

        $trs = '';
        foreach ($rows as [$k,$v]) {
            $trs .= '<tr><th style="width:120px;color:#9ca3af;text-align:left;padding:8px;border-bottom:1px solid var(--mw-border)">'.$this->e($k).'</th><td style="padding:8px;border-bottom:1px solid var(--mw-border)">'.$v.'</td></tr>';
        }
        return '<table class="mw-table" style="border:0"><tbody>'.$trs.'</tbody></table>';
    }

    private function renderSession(array $p): string
    {
        $s = $p['session'] ?? [];
        if (!$s) return '<div style="color:#9ca3af">No session data.</div>';

        $meta = '<div class="mw-grid-3" style="margin-bottom:10px">'
              . $this->stat('Session ID',  '<span class="mw-mono">'.$this->e((string)($s['id'] ?? '')).'</span>')
              . $this->stat('Session Name','<span class="mw-mono">'.$this->e((string)($s['name'] ?? '')).'</span>')
              . $this->stat('Attrs Count', $this->e((string)count((array)($s['attributes'] ?? []))))
              . '</div>';

        $grid = '<div class="mw-grid-2" style="max-height:45vh; overflow:auto">'
              . $this->card('Attributes', $this->preJson($s['attributes'] ?? []))
              . $this->card('Flashes',    $this->preJson($s['flashes'] ?? []))
              . $this->card('Meta',       $this->preJson($s['meta'] ?? []))
              . '</div>';

        return $meta . $grid;
    }

    private function renderEnvServer(array $p): string
    {
        $env    = (array)($p['php']['env'] ?? $p['env'] ?? []);
        $server = (array)($p['request']['server'] ?? []);
        if (!$env && !$server) return '<div style="color:#9ca3af">No environment/server data.</div>';

        return '<div class="mw-grid-2" style="max-height:45vh; overflow:auto">'
             . $this->card('Environment Variables', $this->preJson($env))
             . $this->card('Server Parameters',     $this->preJson($server))
             . '</div>';
    }

    private function renderHistory(array $p): string
    {
        $meta = $p['_history_meta'] ?? [];
        if (!$meta) return '<div style="color:#9ca3af">No history stored.</div>';

        $rows = '';
        foreach ($meta as $i => $m) {
            $rows .= $this->tr([
                (string)($i+1),
                '<span class="mw-mono">'.$this->e((string)($m['id'] ?? '')).'</span>',
                $this->e((string)($m['ts'] ?? '')),
                isset($m['elapsed_ms']) ? $this->num((float)$m['elapsed_ms']).' ms' : '',
                (string)($m['size'] ?? ''),
            ], ['right',null,null,'right','right']);
        }
        $table = $this->table(['#','ID','Timestamp','Elapsed','Size (B)'], $rows, ['right',null,null,'right','right']);
        return '<div style="max-height:45vh; overflow:auto">'.$table.'</div>';
    }

    /* ========================= UI helpers ========================= */

    private function tabsNav(array $tabs): string
    {
        $out = '';
        $i = 0;
        foreach ($tabs as $key => $def) {
            $k = $this->jsKey($key);
            $title = $this->e($def['title']);
            $selected = $i===0 ? 'true' : 'false';
            $tabIndex = $i===0 ? '0' : '-1';
            $out .= <<<HTML
<button role="tab" aria-selected="{$selected}" tabindex="{$tabIndex}" data-key="{$k}" class="mw-tab-btn">{$title}</button>
HTML;
            $i++;
        }
        return $out;
    }

    private function tabsPanels(array $tabs): string
    {
        $out = '';
        $i = 0;
        foreach ($tabs as $key => $def) {
            $k = $this->jsKey($key);
            $display = $i===0 ? '' : 'style="display:none"';
            // For very long tabs we already wrapped internally where needed
            $out .= <<<HTML
<div role="tabpanel" aria-hidden="{$this->e($i===0 ? 'false' : 'true')}" data-key="{$k}" {$display}>
  {$def['html']}
</div>
HTML;
            $i++;
        }
        return $out;
    }

    private function card(string $title, string $bodyHtml): string
    {
        return '<section class="mw-card">'
             .   '<div class="mw-card-h">'.$this->e($title).'</div>'
             .   '<div class="mw-card-b">'.$bodyHtml.'</div>'
             . '</section>';
    }

    private function table(array $headers, string $rowsHtml, array $align): string
    {
        $ths = '';
        foreach ($headers as $i => $h) {
            $ths .= '<th'.(($align[$i]??null)==='right'?' class="mw-right"':'').'>'.$this->e($h).'</th>';
        }
        return '<table class="mw-table"><thead><tr>'.$ths.'</tr></thead><tbody>'.$rowsHtml.'</tbody></table>';
    }

    private function tr(array $cells, array $align): string
    {
        $tds = '';
        foreach ($cells as $i => $c) {
            $tds .= '<td'.(($align[$i]??null)==='right'?' class="mw-right"':'').'>'.$c.'</td>';
        }
        return '<tr>'.$tds.'</tr>';
    }

    private function stat(string $label, string $value): string
    {
        return '<div class="mw-card"><div class="mw-card-h">'.$this->e($label).'</div><div class="mw-card-b">'.$value.'</div></div>';
    }

    private function kpiRow(array $pairs): string
    {
        $out = '';
        foreach ($pairs as [$label, $value]) {
            $out .= '<div class="mw-kpi"><span class="k">'.$this->e($label).'</span><span class="v">'.$this->e((string)$value).'</span></div>';
        }
        return $out;
    }

    private function preJson(array $a): string
    {
        $j = $this->e(json_encode($a, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return '<pre class="mw-pre">'.$j.'</pre>';
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
