<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Collectors\HtmlKit;

final class Renderer
{
    use HtmlKit;

    public function __construct(private readonly DebugBar $debugBar)
    {
    }

    public function render(): string
    {
        if (!$this->debugBar->isEnabled()) {
            return '';
        }

        $rows = $this->debugBar->collectors()->renderAll($this->debugBar->state());
        if ($rows === []) {
            return '<div style="padding:10px;color:#b91c1c">DebugBar: No collectors registered.</div>';
        }

        $tabs = [];
        $kpis = '';
        $memNow = '-';
        $phpVersion = PHP_VERSION;

        foreach ($rows as $row) {
            if ($row['key'] === 'memory') {
                $memNow = $this->e((string)($row['data']['usage_mb'] ?? '-'));
            }

            if ($row['key'] === 'php') {
                $phpVersion = (string)($row['data']['version'] ?? PHP_VERSION);
            }

            if ($row['key'] === 'kpi') {
                $kpis = $row['html'];
                continue;
            }

            $tabs[$row['key']] = [
                'title' => trim($row['icon'] . ' ' . $row['label']),
                'html' => $row['html'],
            ];
        }

        if ($tabs === []) {
            return '<div style="padding:10px;color:#b91c1c">DebugBar: No enabled tabs available.</div>';
        }

        $elapsed = $this->e($this->num($this->debugBar->elapsedMilliseconds()));
        $phpVer = $this->e($phpVersion);
        $tabsNav = $this->tabsNav($tabs);
        $tabsPanels = $this->tabsPanels($tabs);
        $kpiSection = $kpis === '' ? '' : '<div class="mw-kpi-grid">' . $kpis . '</div>';

        return <<<HTML
<style>
:root {
  --mw-bg: #0b1220;
  --mw-bg-2: #0f172a;
  --mw-card: #111827;
  --mw-border: #1f2937;
  --mw-text: #e5e7eb;
  --mw-text-dim: #9ca3af;
  --mw-accent: #2563eb;
  --mw-pill: #18243b;
  --mw-badge: #223253;
  --mw-shadow: 0 10px 30px rgba(0,0,0,.45);
}

#mwdbg-root {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 2147483000;
  font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  color: var(--mw-text);
}

#mwdbg-root * {
  box-sizing: border-box;
}

.mw-cloak {
  display: none !important;
}

.mw-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  background: var(--mw-bg-2);
  border-top: 1px solid var(--mw-border);
  box-shadow: var(--mw-shadow);
}

.mw-header-left,
.mw-header-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.mw-badge,
.mw-chip,
.mw-badgel {
  display: inline-block;
  border: 1px solid var(--mw-border);
}

.mw-badge,
.mw-chip {
  color: var(--mw-text);
}

.mw-badge {
  font-size: 12px;
  background: var(--mw-pill);
  border-radius: 9999px;
  padding: 2px 10px;
}

.mw-chip {
  font-size: 11px;
  background: var(--mw-pill);
  border-radius: 6px;
  padding: 1px 8px;
}

.mw-btn {
  font-size: 12px;
  padding: 4px 10px;
  border-radius: 6px;
  background: var(--mw-card);
  color: var(--mw-text);
  border: 1px solid var(--mw-border);
  cursor: pointer;
}

.mw-panel-wrap {
  position: absolute;
  left: 0;
  right: 0;
  bottom: 42px;
  padding: 0 8px 8px;
  overflow: hidden;
  transition: max-height .2s ease, opacity .2s ease;
}

.mw-collapse.closed {
  max-height: 0;
  opacity: 0;
}

.mw-collapse.open {
  max-height: 85vh;
  opacity: 1;
}

.mw-panel {
  background: var(--mw-bg);
  border: 1px solid var(--mw-border);
  border-radius: 12px;
  box-shadow: var(--mw-shadow);
  overflow: hidden;
}

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

.mw-tab-btn {
  width: 100%;
  text-align: left;
  padding: 10px 12px;
  color: var(--mw-text);
  background: transparent;
  border: 0;
  border-bottom: 1px solid rgba(255,255,255,.05);
  cursor: pointer;
}

.mw-tab-btn.active {
  background: rgba(37,99,235,.2);
}

.mw-kpi-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
  margin-bottom: 12px;
}

.mw-kpi {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  background: var(--mw-card);
  border: 1px solid var(--mw-border);
  border-radius: 8px;
  padding: 8px 10px;
}

.mw-kpi .k {
  color: var(--mw-text-dim);
  font-size: 12px;
  text-transform: capitalize;
}

.mw-kpi .v {
  font-size: 12px;
  font-weight: 600;
}

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
  font-size: 14px;
  color: var(--mw-text);
  vertical-align: top;
}

.mw-table tr:last-child td {
  border-bottom: 0;
}

.mw-right {
  text-align: right;
}

.mw-pre {
  background: var(--mw-bg-2);
  border: 1px solid var(--mw-border);
  padding: 8px;
  border-radius: 8px;
  overflow: auto;
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
  white-space: pre-wrap;
  word-break: break-word;
}

.mw-badgel {
  font-size: 11px;
  padding: 2px 8px;
  background: var(--mw-badge);
  border-radius: 9999px;
}

.mw-sev-warning {
  background: rgba(245, 158, 11, .2);
  border-color: rgba(245, 158, 11, .45);
  color: #fde68a;
}

.mw-sev-critical {
  background: rgba(239, 68, 68, .2);
  border-color: rgba(239, 68, 68, .5);
  color: #fecaca;
}

.mw-muted {
  color: var(--mw-text-dim);
}
.mw-mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 12px;
}

.mw-grid-2 {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.mw-grid-3 {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

@media (max-width: 900px) {
  .mw-grid {
    grid-template-columns: 150px 1fr;
  }

  .mw-kpi-grid,
  .mw-grid-3 {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 700px) {
  .mw-grid,
  .mw-grid-2,
  .mw-kpi-grid,
  .mw-grid-3 {
    grid-template-columns: 1fr;
  }

  .mw-panel-wrap {
    bottom: 52px;
  }
}

#mwdbg-root.ready {
  display: initial !important;
}
</style>

<div id="mwdbg-root" class="mw-cloak">
  <div class="mw-header">
    <div class="mw-header-left">
      <span class="mw-badge">DebugBar</span>
      <span class="mw-chip">Elapsed: <b style="margin-left:6px">{$elapsed} ms</b></span>
      <span class="mw-chip">Mem: <b style="margin-left:6px">{$memNow} MB</b></span>
      <span class="mw-chip">PHP: <b style="margin-left:6px">{$phpVer}</b></span>
    </div>
    <div class="mw-header-right">
      <button id="mw-btn-toggle" class="mw-btn" type="button" aria-expanded="false">Open</button>
    </div>
  </div>

  <div id="mw-collapse" class="mw-panel-wrap mw-collapse closed" aria-hidden="true">
    <div class="mw-panel">
      <div class="mw-grid">
        <div id="mw-tabs" class="mw-sidebar" role="tablist" aria-label="DebugBar tabs">
          {$tabsNav}
        </div>
        <div class="mw-content">
          {$kpiSection}
          <div id="mw-panels">
            {$tabsPanels}
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
  var root = document.getElementById('mwdbg-root');
  var toggle = document.getElementById('mw-btn-toggle');
  var collapse = document.getElementById('mw-collapse');
  var tabs = document.getElementById('mw-tabs');
  var panels = document.getElementById('mw-panels');

  if (!root || !toggle || !collapse || !tabs || !panels) {
    return;
  }

  var storageKey = 'mwDebugBar';
  var state = { open: false, active: tabs.querySelector('[role="tab"]') ? tabs.querySelector('[role="tab"]').getAttribute('data-key') : null };

  try {
    var saved = JSON.parse(localStorage.getItem(storageKey) || '{}');
    if (typeof saved.open === 'boolean') {
      state.open = saved.open;
    }
    if (typeof saved.active === 'string' && saved.active) {
      state.active = saved.active;
    }
  } catch (error) {}

  function persist() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(state));
    } catch (error) {}
  }

  function setOpen(open) {
    state.open = open;
    collapse.classList.toggle('open', open);
    collapse.classList.toggle('closed', !open);
    collapse.setAttribute('aria-hidden', String(!open));
    toggle.textContent = open ? 'Close' : 'Open';
    toggle.setAttribute('aria-expanded', String(open));
    persist();
  }

  function switchTab(key) {
    state.active = key;

    tabs.querySelectorAll('[role="tab"]').forEach(function (tab) {
      var selected = tab.getAttribute('data-key') === key;
      tab.classList.toggle('active', selected);
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
    });

    panels.querySelectorAll('[role="tabpanel"]').forEach(function (panel) {
      var visible = panel.getAttribute('data-key') === key;
      panel.style.display = visible ? '' : 'none';
      panel.setAttribute('aria-hidden', String(!visible));
    });

    persist();
  }

  toggle.addEventListener('click', function () {
    setOpen(!state.open);
  });

  tabs.addEventListener('click', function (event) {
    var button = event.target.closest('[role="tab"]');
    if (!button) {
      return;
    }

    switchTab(button.getAttribute('data-key'));
  });

  tabs.addEventListener('keydown', function (event) {
    var tabItems = Array.from(tabs.querySelectorAll('[role="tab"]'));
    if (tabItems.length === 0) {
      return;
    }

    var currentIndex = tabItems.findIndex(function (item) {
      return item.getAttribute('data-key') === state.active;
    });
    var nextIndex = currentIndex;

    if (event.key === 'ArrowDown' || event.key === 'ArrowRight') {
      nextIndex = (currentIndex + 1) % tabItems.length;
    } else if (event.key === 'ArrowUp' || event.key === 'ArrowLeft') {
      nextIndex = (currentIndex - 1 + tabItems.length) % tabItems.length;
    } else if (event.key === 'Home') {
      nextIndex = 0;
    } else if (event.key === 'End') {
      nextIndex = tabItems.length - 1;
    } else {
      return;
    }

    event.preventDefault();
    var nextKey = tabItems[nextIndex].getAttribute('data-key');
    switchTab(nextKey);
    tabItems[nextIndex].focus();
  });

  if (!state.active) {
    return;
  }

  setOpen(state.open);
  switchTab(state.active);
  root.classList.add('ready');
})();
</script>
HTML;
    }
}
