<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Collectors\HtmlKit;

final class Renderer
{
    use HtmlKit;
    public function __construct(private DebugBar $debugBar) {}

    public function render(): string
    {
        if (!$this->debugBar->isEnabled()) return '';

        $metaData=['_meta' => [
                'generated_at' => date('c'),
                'elapsed_ms'   => round((microtime(true) - $this->debugBar->start) * 1000, 2),
                'php_sapi'     => PHP_SAPI
            ]];
        $state = $this->debugBar->state();
        $rows  = $this->debugBar->collectors()->renderAll($state);

        if (!$rows || !is_array($rows)) {
            return '<div style="padding:10px;color:red">DebugBar: No payload data</div>';
        }
        
        // Header pills
        $elapsed = $this->e((string)($metaData['_meta']['elapsed_ms'] ?? '-'));
        $phpVer  = $this->e((string)($rows['php']['version'] ?? PHP_VERSION));
        
        $tabs = [];
        foreach($rows as $data) {
          if($data['key']==='memory')
          {
              $memNow  = $this->e((string)($data['data']['usage_mb'] ?? '-'));
          }
          if($data['key'] === 'kpi') {
                $kpis = $data['html']??'';
            }else {
                $tabs[$data['key']] = ['title' => $data['icon'] . ' ' . $data['label'], 'html' => $data['html']];
            }
        
        }
        
        $tabsNav   = $this->tabsNav($tabs);
        $tabsPanel = $this->tabsPanels($tabs);

        return <<<HTML
<style>
/* ======== Theme Variables ======== */
:root {
  --mw-theme: 'dark';
  
  /* Dark Theme (Default) */
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
  --mw-btn-bg: var(--mw-card);
  --mw-btn-hover: #1a2338;
}

[data-theme="light"] {
  --mw-bg: #f8fafc;
  --mw-bg-2: #f1f5f9;
  --mw-card: #ffffff;
  --mw-border: #e2e8f0;
  --mw-text: #1e293b;
  --mw-text-dim: #64748b;
  --mw-accent: #3b82f6;
  --mw-pill: #e2e8f0;
  --mw-badge: #f1f5f9;
  --mw-shadow: 0 10px 30px rgba(0,0,0,.1);
  --mw-btn-bg: #ffffff;
  --mw-btn-hover: #f1f5f9;
}

/* ======== Base Styles ======== */
#mwdbg-root { 
  position: fixed; 
  left: 0; 
  right: 0; 
  bottom: 0; 
  z-index: 2147483000; 
  font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji","Segoe UI Emoji"; 
  color: var(--mw-text); 
}
#mwdbg-root * { box-sizing: border-box; }

.mw-cloak { display: none !important; }

/* Header bar */
.mw-header { 
  display: flex; 
  align-items: center; 
  justify-content: space-between; 
  padding: 8px 12px; 
  background: var(--mw-bg-2); 
  border-top: 1px solid var(--mw-border); 
  box-shadow: var(--mw-shadow); 
}

.mw-badge { 
  font-size: 12px; 
  background: var(--mw-pill); 
  border: 1px solid var(--mw-border); 
  border-radius: 9999px; 
  padding: 2px 10px; 
  color: var(--mw-text); 
}

.mw-chip { 
  font-size: 11px; 
  background: var(--mw-pill); 
  border: 1px solid var(--mw-border); 
  border-radius: 6px; 
  padding: 1px 8px; 
  color: var(--mw-text); 
}

.mw-btn { 
  font-size: 12px; 
  padding: 4px 10px; 
  border-radius: 6px; 
  background: var(--mw-btn-bg); 
  color: var(--mw-text); 
  border: 1px solid var(--mw-border); 
  cursor: pointer; 
  transition: background-color 0.2s ease;
}

.mw-btn:hover { 
  background: var(--mw-btn-hover); 
}

.mw-theme-toggle {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-left: 8px;
}

.mw-theme-icon {
  width: 16px;
  height: 16px;
  fill: currentColor;
}

/* Panel up (absolute, opens upward) */
.mw-panel-wrap { 
  position: absolute; 
  left: 0; 
  right: 0; 
  bottom: 40px; 
  padding: 0 8px 8px; 
}

.mw-panel { 
  background: var(--mw-bg); 
  border: 1px solid var(--mw-border); 
  border-radius: 12px; 
  box-shadow: var(--mw-shadow); 
  overflow: hidden; 
}

/* Panel collapse animation */
.mw-collapse { 
  overflow: clip; 
  transition: max-height .25s ease, opacity .2s ease; 
  opacity: 1; 
}

.mw-collapse.closed { 
  max-height: 0 !important; 
  opacity: 0; 
}

.mw-collapse.open { 
  max-height: 80vh; 
}

/* Grid */
.mw-grid { 
  display: grid; 
  grid-template-columns: 180px 1fr; 
  max-height: 60vh; 
}

.mw-sidebar { 
  background: var(--mw-bg-2); 
  border-right: 1px solid var(--mw-border); 
  overflow-y: auto; 
}

.mw-content { 
  background: var(--mw-bg); 
  padding: 12px; 
  overflow-y: auto; 
}

/* Sidebar tabs */
.mw-tab-btn { 
  width: 100%; 
  text-align: left; 
  padding: 10px 12px; 
  color: var(--mw-text); 
  background: transparent; 
  border: 0; 
  border-bottom: 1px solid rgba(255,255,255,.05); 
  cursor: pointer; 
  transition: background-color 0.2s ease;
}

.mw-tab-btn:hover { 
  background: rgba(255,255,255,.06); 
}

.mw-tab-btn.active { 
  background: var(--mw-accent); 
}

/* KPI grid */
.mw-kpi-grid { 
  display: grid; 
  grid-template-columns: repeat(6, minmax(0,1fr)); 
  gap: 8px; 
  margin-bottom: 10px; 
}

@media (max-width: 1024px) { 
  .mw-kpi-grid { 
    grid-template-columns: repeat(3, minmax(0,1fr)); 
  } 
}

@media (max-width: 640px)  { 
  .mw-kpi-grid { 
    grid-template-columns: repeat(2, minmax(0,1fr)); 
  } 
}

.mw-kpi { 
  display:flex; 
  align-items:center; 
  justify-content:space-between; 
  background: var(--mw-card); 
  border:1px solid var(--mw-border); 
  border-radius: 8px; 
  padding: 8px 10px; 
}

.mw-kpi .k { 
  color: var(--mw-text-dim); 
  font-size: 12px; 
}

.mw-kpi .v { 
  color: var(--mw-text); 
  font-weight: 600; 
  font-size: 12px; 
}

/* Cards & sections */
.mw-card { 
  background: var(--mw-card); 
  border: 1px solid var(--mw-border); 
  border-radius: 10px; 
}

.mw-card-h { 
  padding: 8px 10px; 
  border-bottom: 1px solid var(--mw-border); 
  font-weight: 600; 
  font-size: 14px; 
}

.mw-card-b { 
  padding: 10px; 
}

/* Table */
.mw-table { 
  width: 100%; 
  border: 1px solid var(--mw-border); 
  border-radius: 8px; 
  border-collapse: collapse; 
  overflow: hidden; 
}

.mw-table thead th { 
  background: var(--mw-bg-2); 
  color: var(--mw-text-dim); 
  text-transform: uppercase; 
  font-size: 12px; 
  padding: 8px; 
  border-bottom: 1px solid var(--mw-border); 
  text-align: left; 
}

.mw-table td { 
  padding: 8px; 
  border-bottom: 1px solid var(--mw-border);
  font-size:14px;
  color: var(--mw-text);
}

.mw-table tr:last-child td { 
  border-bottom: 0; 
}

.mw-right { 
  text-align: right; 
}

.mw-pre { 
  background: var(--mw-bg-2); 
  border:1px solid var(--mw-border); 
  padding:8px; 
  border-radius:8px; 
  overflow:auto; 
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; 
  font-size: 12px; 
}

/* Utilities */
.mw-badgel { 
  display:inline-block; 
  font-size: 11px; 
  padding: 2px 8px; 
  background: var(--mw-badge); 
  border:1px solid var(--mw-border); 
  border-radius: 9999px; 
}

.mw-mono { 
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace; 
  font-size: 12px; 
}

.mw-grid-2 { 
  display:grid; 
  grid-template-columns: 1fr 1fr; 
  gap: 12px; 
}

.mw-grid-3 { 
  display:grid; 
  grid-template-columns: 1fr 1fr 1fr; 
  gap: 12px; 
}

@media (max-width: 900px) { 
  .mw-grid-3 { 
    grid-template-columns: 1fr; 
  } 
  .mw-grid { 
    grid-template-columns: 150px 1fr; 
  } 
}

@media (max-width: 700px) { 
  .mw-grid-2 { 
    grid-template-columns: 1fr; 
  } 
}

/* Cloak removal on init */
#mwdbg-root.ready { 
  display: initial !important; 
}

/* ======== Exception Collector Styles ======== */
.db-exception-empty {
  padding: 20px;
  text-align: center;
  color: var(--mw-text-dim);
  font-style: italic;
}

.db-exception-header-bar {
  display: flex;
  gap: 8px;
  align-items: center;
  margin-bottom: 16px;
  padding: 8px 0;
  border-bottom: 1px solid var(--mw-border);
}

.db-exception-pill {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  background: var(--mw-pill);
  border: 1px solid var(--mw-border);
  border-radius: 16px;
  font-size: 12px;
}

.db-exception-pill-label {
  color: var(--mw-text-dim);
}

.db-exception-pill-value {
  color: var(--mw-text);
  font-weight: 600;
}

.db-exception-card {
  background: var(--mw-card);
  border: 1px solid var(--mw-border);
  border-radius: 8px;
  margin-bottom: 16px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.db-exception-header {
  padding: 12px 16px;
  background: linear-gradient(90deg, rgba(239,68,68,0.15) 0%, transparent 100%);
  border-bottom: 1px solid var(--mw-border);
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.db-exception-badge {
  background: #dc2626;
  color: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.db-exception-title {
  color: var(--mw-text);
  font-weight: 600;
  font-size: 14px;
  flex: 1;
  min-width: 0;
  word-break: break-word;
}

.db-exception-meta {
  color: var(--mw-text-dim);
  font-size: 12px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

.db-exception-body {
  padding: 16px;
}

.db-exception-details {
  margin-bottom: 12px;
}

.db-exception-summary {
  cursor: pointer;
  padding: 8px 12px;
  background: var(--mw-bg-2);
  border: 1px solid var(--mw-border);
  border-radius: 6px;
  font-weight: 600;
  font-size: 13px;
  color: var(--mw-text);
  list-style: none;
  transition: background-color 0.2s ease;
}

.db-exception-summary:hover {
  background: var(--mw-accent);
}

.db-exception-summary::-webkit-details-marker {
  display: none;
}

.db-exception-summary::before {
  content: '▼';
  display: inline-block;
  margin-right: 8px;
  font-size: 10px;
  transition: transform 0.2s ease;
}

.db-exception-details[open] .db-exception-summary::before {
  transform: rotate(180deg);
}

.db-exception-trace {
  background: var(--mw-bg-2);
  border: 1px solid var(--mw-border);
  border-radius: 6px;
  padding: 12px;
  margin-top: 8px;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  line-height: 1.4;
  color: var(--mw-text);
  white-space: pre-wrap;
  word-break: break-all;
  max-height: 300px;
  overflow-y: auto;
}

.db-exception-chain {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--mw-border);
}

.db-exception-chain b {
  color: var(--mw-text);
  font-size: 13px;
  display: block;
  margin-bottom: 8px;
}

.db-exception-chain-list {
  margin: 0;
  padding-left: 20px;
  list-style: none;
}

.db-exception-chain-item {
  margin-bottom: 8px;
  padding: 8px;
  background: var(--mw-bg-2);
  border: 1px solid var(--mw-border);
  border-radius: 6px;
  font-size: 12px;
  line-height: 1.4;
}

.db-exception-chain-type {
  color: #dc2626;
  font-weight: 600;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

.db-exception-chain-message {
  color: var(--mw-text);
}

.db-exception-chain-location {
  color: var(--mw-text-dim);
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
}

/* Animation for new exceptions */
.db-exception-card {
  animation: db-exception-appear 0.3s ease-out;
}

@keyframes db-exception-appear {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Responsive design */
@media (max-width: 768px) {
  .db-exception-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 6px;
  }
  
  .db-exception-title {
    font-size: 13px;
  }
  
  .db-exception-meta {
    font-size: 11px;
  }
  
  .db-exception-trace {
    font-size: 11px;
    padding: 8px;
  }
  
  .db-exception-chain-item {
    font-size: 11px;
  }
}
</style>

<div id="mwdbg-root" class="mw-cloak" data-theme="dark">
  <!-- Header -->
  <div class="mw-header">
    <div style="display:flex;align-items:center;gap:8px">
      <span class="mw-badge">DebugBar</span>
      <span class="mw-chip">Elapsed: <b style="margin-left:6px">{$elapsed} ms</b></span>
      <span class="mw-chip">Mem: <b style="margin-left:6px">{$memNow} MB</b></span>
      <span class="mw-chip">PHP: <b style="margin-left:6px">{$phpVer}</b></span>
      <div class="mw-theme-toggle">
        <button id="mw-theme-toggle" class="mw-btn" type="button" title="Toggle theme">
          <svg class="mw-theme-icon" viewBox="0 0 24 24" width="16" height="16">
            <path d="M12 18c-3.31 0-6-2.69-6-6s2.69-6 6-6 6 2.69 6 6-2.69 6-6 6zm0-10c-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4-1.79-4-4-4zm0-6c-.55 0-1-.45-1-1s.45-1 1-1 1 .45 1 1-.45 1-1 1zm0 12c-.55 0-1 .45-1 1s.45 1 1 1 1-.45 1-1-.45-1-1-1z"/>
          </svg>
        </button>
      </div>
    </div>
    <button id="mw-btn-toggle" class="mw-btn" type="button" aria-expanded="false">Open</button>
  </div>

<<<<<<< HEAD
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
        <div class="bg-gray-900 p-3 h-fit overflow-y-auto">
          <!-- KPI grid -->
          <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2 mb-3">
            {$kpis}
=======
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
>>>>>>> 728ac7ee24e650259c673e8d13d163a6c2b0b007
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
  const themeToggle = document.getElementById('mw-theme-toggle');
  const collapse  = document.getElementById('mw-collapse');
  const tabsWrap  = document.getElementById('mw-tabs');
  const panels    = document.getElementById('mw-panels');

  if(!root || !btnToggle || !themeToggle || !collapse || !tabsWrap || !panels) return;

  // State (restore)
  const KEY = 'mwDebugBar';
  let state = { 
    open: false, 
    active: 'timeline', 
    theme: 'dark' 
  };

  try {
    const saved = JSON.parse(localStorage.getItem(KEY) || '{}');
    if (typeof saved.open === 'boolean') state.open = saved.open;
    if (typeof saved.active === 'string' && saved.active) state.active = saved.active;
    if (typeof saved.theme === 'string' && saved.theme) state.theme = saved.theme;
  } catch(e) {}

  // Theme functions
  function setTheme(theme) {
    state.theme = theme;
    root.setAttribute('data-theme', theme);
    persist();
  }

  function toggleTheme() {
    const newTheme = state.theme === 'dark' ? 'light' : 'dark';
    setTheme(newTheme);
  }

  // Init UI
  function persist(){ 
    try{ 
      localStorage.setItem(KEY, JSON.stringify(state)); 
    } catch(e){} 
  }
  
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

  // Event listeners
  btnToggle.addEventListener('click', ()=> setOpen(!state.open));
  themeToggle.addEventListener('click', toggleTheme);

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

  // Initialize
  setOpen(state.open);
  switchTab(state.active);
  setTheme(state.theme);
  root.classList.add('ready');
})();
</script>
HTML;
    }
<<<<<<< HEAD

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
    return '<table class="min-w-full text-left"><tbody class="text-sm h-fit">' . $trs . '</tbody></table>';
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
=======
}
>>>>>>> 728ac7ee24e650259c673e8d13d163a6c2b0b007
