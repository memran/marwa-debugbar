<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

final class Renderer
{
    public function __construct(private DebugBar $debugBar) {}

    public function render(): string
    {
        $payload = $this->debugBar->payload();
        if (empty($payload)) return '';

        // Persist snapshot if history enabled
        $history = $this->debugBar->history();
        if ($history?->isEnabled()) {
            $id = $history->persist($payload);
            $payload['_history_meta'] = $history->recentMeta();
            $payload['_history_current'] = $id;
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $pluginTabs = $this->debugBar->plugins()->tabs();
        $pluginTabsJson = json_encode($pluginTabs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $css = <<<CSS
#mw-debugbar{position:fixed;left:20px;right:20px;bottom:20px;z-index:2147483000;font-family:ui-sans-serif,system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif}
#mw-debugbar .bar{display:flex;align-items:center;gap:12px;background:#111827;color:#F9FAFB;border-radius:12px;padding:8px 14px;box-shadow:0 4px 12px rgba(0,0,0,.35);font-size:13px}
#mw-debugbar .pill{background:#1F2937;border-radius:999px;padding:4px 10px;font-size:12px;border:1px solid #374151}
#mw-debugbar .tab{cursor:pointer;user-select:none;padding:4px 10px;border-radius:6px;background:#1F2937;transition:background .2s}
#mw-debugbar .tab:hover{background:#374151}
#mw-debugbar .spacer{flex:1}
#mw-debugbar .kbd{font-size:11px;background:#1F2937;border:1px solid #374151;border-radius:6px;padding:2px 6px}
#mw-panel{position:fixed;left:20px;right:20px;bottom:64px;height:50vh;background:#0B1220;color:#F3F4F6;border:1px solid #1F2937;border-radius:12px;display:none;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.45);transform:translateY(10px);opacity:0;transition:opacity .25s,transform .25s}
#mw-panel.show{display:block;transform:translateY(0);opacity:1}
#mw-panel .inner{display:flex;height:100%}
#mw-panel .sidebar{width:220px;border-right:1px solid #1F2937;background:#111827;padding:10px;overflow-y:auto}
#mw-panel .sidebar .item{display:flex;align-items:center;gap:8px;padding:8px;border-radius:8px;font-size:13px;cursor:pointer;transition:background .2s}
#mw-panel .sidebar .item:hover{background:#1F2937}
#mw-panel .sidebar .item.active{background:#2563EB;color:#fff}
#mw-panel .content{flex:1;padding:12px;overflow-y:auto;background:#0B1220}
#mw-panel .search{width:100%;padding:8px;margin:8px 0;font-size:12px;background:#1F2937;border:1px solid #374151;border-radius:6px;color:#F3F4F6}
#mw-kpis{display:flex;gap:8px;flex-wrap:wrap;margin:6px 0}
table.mw{width:100%;border-collapse:collapse;font-size:12px}
table.mw th,table.mw td{border-bottom:1px solid #1F2937;padding:8px 10px;vertical-align:top}
table.mw th{text-align:left;background:#111827}
.mw-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:12px;white-space:pre-wrap;word-break:break-word}
.badge{display:inline-block;padding:2px 6px;border-radius:6px;font-size:11px;font-weight:500}
.badge-success{background:#065F46;color:#D1FAE5}
.badge-error{background:#7F1D1D;color:#FECACA}
.badge-info{background:#1E3A8A;color:#DBEAFE}
CSS;

        $js = <<<JS
(function(){
  const data = {$json};
  const pluginTabs = {$pluginTabsJson} || [];

  const elPanel = document.getElementById('mw-panel');
  const elContent = document.getElementById('mw-content');
  const elSidebar = document.getElementById('mw-sidebar');
  const elSearch  = document.getElementById('mw-search');

  // Filter state
  const filter = { route:'', status:'', minMs:'', maxMs:'', levels:new Set() };
  let activeKey = 'timeline';

  const tabs = {
    timeline: renderTimeline,
    dumps: renderDumps,
    logs: renderLogs,
    queries: renderQueries,
    memory: renderMemory,
    php: renderPhp,
    request: renderRequest,
    history: renderHistory
  };

  // Register plugin renderer functions
  pluginTabs.forEach(t=>{
    try { const fn = new Function('return ('+t.renderer+')')(); if (typeof fn==='function') tabs[t.key]=fn; }
    catch(e){ console.warn('Plugin renderer failed', t, e); }
  });

  function togglePanel(){
    const vis = elPanel.classList.contains('show');
    if (vis){ elPanel.classList.remove('show'); setTimeout(()=>elPanel.style.display='none',250); }
    else { elPanel.style.display='block'; setTimeout(()=>elPanel.classList.add('show'),10); }
  }

  function escapeHtml(s){ return String(s).replace(/[&<>"']/g, m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':"&quot;","'":'&#039;'}[m])); }
  function setContent(html){ elContent.innerHTML = html; }
  function rerenderActive(){ setContent(tabs[activeKey](data)); }
  function withinDuration(ms){ const min=filter.minMs?parseFloat(filter.minMs):null; const max=filter.maxMs?parseFloat(filter.maxMs):null; if(min!==null&&ms<min)return false; if(max!==null&&ms>max)return false; return true; }
  function matchesRouteAndStatus(){ const m=data.request_metrics||{}; const rOk=!filter.route||((m.route||'').toLowerCase().includes(filter.route)); const sOk=!filter.status||String(m.status||'')===filter.status; return rOk && sOk; }

  // KPI pills
  function mountKpis(){
    const m = data.request_metrics || {};
    const el = document.getElementById('mw-kpis');
    const pill = (label,val)=>`<div class="pill">\${label}: <b>\${val ?? '-'}</b></div>`;
    el.innerHTML = [
      pill('Route', escapeHtml(m.route||'-')),
      pill('Status', m.status ?? '-'),
      pill('Duration', (m.duration_ms!=null?`${m.duration_ms} ms`:'-')),
      pill('SQL', `${m.queries ?? 0} in ${m.queries_time_ms ?? 0} ms`),
      pill('PeakMem', `${m.memory_peak_mb ?? 0} MB`),
      pill('Resp', (m.response_bytes!=null?`${(m.response_bytes/1024).toFixed(0)} KB`:'-')),
      pill('Logs', m.logs ?? 0),
      pill('Dumps', m.dumps ?? 0)
    ].join('');
  }

  // Filters UI
  function mountFilters(){
    const metrics = data.request_metrics || {};
    const statuses = [ '', '200','201','204','301','302','400','401','403','404','422','500' ];
    const levels = ['DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY'];
    const html = `
      <div id="mw-filters" style="display:grid;grid-template-columns: 1fr 120px 120px 1fr; gap:8px; margin:6px 0 6px">
        <input id="f-route" class="search" placeholder="Route contains…" value="${escapeHtml(metrics.route||'')}" />
        <select id="f-status" class="search">${statuses.map(s=>`<option value="${s}">${s===''?'Any status':s}</option>`).join('')}</select>
        <input id="f-min" class="search" type="number" min="0" step="1" placeholder="Min ms" />
        <input id="f-max" class="search" type="number" min="0" step="1" placeholder="Max ms" />
      </div>
      <div id="f-levels" style="display:flex; gap:6px; flex-wrap:wrap; margin-bottom:4px">
        ${levels.map(l=>`<span class="tab" data-level="${l}" title="Toggle">${l}</span>`).join('')}
      </div>`;
    const searchBox = document.getElementById('mw-search');
    searchBox.insertAdjacentHTML('beforebegin', html);
    document.getElementById('f-route').addEventListener('input', e=>{ filter.route=e.target.value.toLowerCase(); rerenderActive(); });
    document.getElementById('f-status').addEventListener('change', e=>{ filter.status=e.target.value; rerenderActive(); });
    document.getElementById('f-min').addEventListener('input', e=>{ filter.minMs=e.target.value; rerenderActive(); });
    document.getElementById('f-max').addEventListener('input', e=>{ filter.maxMs=e.target.value; rerenderActive(); });
    document.querySelectorAll('#f-levels .tab').forEach(el=>{
      el.addEventListener('click', ()=>{
        const lvl=el.getAttribute('data-level');
        if(filter.levels.has(lvl)){ filter.levels.delete(lvl); el.classList.remove('active'); }
        else { filter.levels.add(lvl); el.classList.add('active'); }
        rerenderActive();
      });
    });
  }

  // Core renderers
  function renderTimeline(d){
    const rows = (d.timeline||[]).map(m=>`<tr><td class="mw-mono">\${escapeHtml(m.label)}</td><td>\${m.t.toFixed(5)}</td></tr>`).join('');
    return `<table class="mw"><thead><tr><th>Label</th><th>Seconds</th></tr></thead><tbody>\${rows}</tbody></table>`;
  }
  function renderDumps(d){
    const items = (d.dumps||[]).filter(dp=> matchesRouteAndStatus() && withinDuration(parseFloat(dp.time||0)));
    if (!items.length) return `<div>No dumps captured (after filters).</div>`;
    const blocks = items.map((dp,i)=>{
      const meta=[]; if(dp.time!==undefined)meta.push(\`\${dp.time} ms\`); if(dp.file)meta.push(\`\${dp.file}\${dp.line?':'+dp.line:''}\`);
      const title = dp.name ? dp.name : \`Dump #\${i+1}\`;
      const safe = String(dp.html||'').replace(/<\\s*\\/?\\s*script\\b[^>]*>/gi, '');
      return \`<div style="margin-bottom:10px;border:1px solid #1F2937;border-radius:10px;overflow:hidden">
        <div style="padding:6px 10px;background:#0F172A;color:#E5E7EB;font-size:12px">\${escapeHtml(title)} \${meta.length?'<span style="opacity:.7">('+escapeHtml(meta.join(' · '))+')</span>':''}</div>
        <div style="background:#111827;padding:8px;overflow:auto">\${safe}</div></div>\`;
    }).join('');
    return \`<div>\${blocks}</div>\`;
  }
  function renderLogs(d){
    const color = lvl => lvl==='ERROR'?'badge-error':(lvl==='WARNING'?'badge-info':'badge-info');
    const arr = (d.logs||[]).filter(l=> matchesRouteAndStatus() && (!filter.levels.size || filter.levels.has(String(l.level||'').toUpperCase())) && withinDuration(parseFloat(l.time||0)));
    const rows = arr.map(l=>\`<tr><td>\${l.time} ms</td><td><span class="badge \${color(l.level)}">\${escapeHtml(l.level)}</span></td><td class="mw-mono">\${escapeHtml(l.message)}</td><td class="mw-mono">\${escapeHtml(JSON.stringify(l.context||{}))}</td></tr>\`).join('');
    return \`<table class="mw"><thead><tr><th>Time</th><th>Level</th><th>Message</th><th>Context</th></tr></thead><tbody>\${rows}</tbody></table>\`;
  }
  function renderQueries(d){
    const arr = (d.queries||[]).filter(q=> matchesRouteAndStatus() && withinDuration(parseFloat(q.duration_ms||0)));
    const rows = arr.map(q=>\`<tr><td class="mw-mono">\${escapeHtml(q.sql)}</td><td class="mw-mono">\${escapeHtml(JSON.stringify(q.params||{}))}</td><td>\${q.duration_ms||0} ms</td><td>\${escapeHtml(q.connection||'')}</td></tr>\`).join('');
    return \`<table class="mw"><thead><tr><th>SQL</th><th>Params</th><th>Duration</th><th>Conn</th></tr></thead><tbody>\${rows}</tbody></table>\`;
  }
  function renderMemory(d){
    const m=d.memory||{}; return \`<table class="mw"><tbody>
      <tr><th>Usage</th><td>\${m.usage_mb} MB</td></tr>
      <tr><th>Peak</th><td>\${m.peak_usage_mb} MB</td></tr>
      <tr><th>Limit</th><td>\${escapeHtml(m.limit||'')}</td></tr>
    </tbody></table>\`;
  }
  function renderPhp(d){
    const p=d.php||{}; const exts=(p.extensions||[]).map(e=>\`<code class="mw-mono">\${escapeHtml(e)}</code>\`).join(', ');
    return \`<div>Version: <b>\${escapeHtml(p.version||'')}</b><br/>Opcache: <b>\${p.opcache_enabled?'Yes':'No'}</b><br/><div style="margin-top:6px">Extensions: \${exts}</div></div>\`;
  }
  function renderRequest(d){
    const r=d.request||{}; return \`<table class="mw"><tbody>
      <tr><th>Method</th><td>\${escapeHtml(r.method||'')}</td></tr>
      <tr><th>URI</th><td class="mw-mono">\${escapeHtml(r.uri||'')}</td></tr>
      <tr><th>IP</th><td>\${escapeHtml(r.ip||'')}</td></tr>
      <tr><th>User Agent</th><td class="mw-mono">\${escapeHtml(r.ua||'')}</td></tr>
      <tr><th>Headers</th><td class="mw-mono">\${escapeHtml(JSON.stringify(r.headers||{},null,2))}</td></tr>
      <tr><th>GET</th><td class="mw-mono">\${escapeHtml(JSON.stringify(r.get||{},null,2))}</td></tr>
      <tr><th>POST</th><td class="mw-mono">\${escapeHtml(JSON.stringify(r.post||{},null,2))}</td></tr>
      <tr><th>Cookies</th><td class="mw-mono">\${escapeHtml(JSON.stringify(r.cookies||{},null,2))}</td></tr>
      <tr><th>Files</th><td class="mw-mono">\${escapeHtml(JSON.stringify(r.files||{},null,2))}</td></tr>
    </tbody></table>\`;
  }
  function renderHistory(d){
    const meta=d._history_meta||[]; if(!meta.length) return '<div>No history stored.</div>';
    const rows = meta.map((m,i)=>{ const cur=(d._history_current&&m.id===d._history_current); return \`<tr data-id="\${m.id}" class="hist-row" style="cursor:pointer">
      <td>\${i+1}</td><td class="mw-mono">\${escapeHtml(m.id)}</td><td class="mw-mono">\${escapeHtml(m.ts||'')}</td>
      <td>\${m.elapsed_ms!=null? m.elapsed_ms.toFixed(2)+' ms' : ''}</td><td>\${m.size??''}</td><td>\${cur?'<span class="badge badge-info">Current</span>':''}</td></tr>\`; }).join('');
    const html = \`<div style="margin-bottom:8px">Recent snapshots (metadata only). Implement endpoint to load full payloads.</div>
      <table class="mw"><thead><tr><th>#</th><th>ID</th><th>Timestamp</th><th>Elapsed</th><th>Size(B)</th><th></th></tr></thead><tbody>\${rows}</tbody></table><div id="hist-detail" style="margin-top:10px"></div>\`;
    setTimeout(()=>{ document.querySelectorAll('.hist-row').forEach(tr=>{
      tr.addEventListener('click',()=>{ const id=tr.getAttribute('data-id'); document.getElementById('hist-detail').innerHTML =
        '<div class="mw-mono">Snapshot ID: '+escapeHtml(id)+'. Add JSON endpoint to load full details.</div>'; });
    }); },0);
    return html;
  }

  function mountSidebar(){
    const base = [
      {key:'heuristics', title:'Profiler', icon:'🔥', order:105},
      {key:'timeline', title:'Timeline', icon:'⏱️', order:110},
      {key:'dumps', title:'Dumps', icon:'🧪', order:120},
      {key:'logs', title:'Logs', icon:'📝', order:130},
      {key:'queries', title:'Queries', icon:'🗄️', order:140},
      {key:'memory', title:'Memory', icon:'💾', order:150},
      {key:'php', title:'PHP', icon:'🐘', order:160},
      {key:'request', title:'Request', icon:'🌐', order:170},
      {key:'history', title:'History', icon:'🕘', order:490}
    ];
    const all = base.concat(pluginTabs.map(t=>({key:t.key,title:t.title,icon:(t.icon||''),order:(t.order||500)}))).sort((a,b)=>a.order-b.order);

    elSidebar.innerHTML = all.map((x,i)=>`<div class="item${i===0?' active':''}" data-key="${x.key}">${x.icon?x.icon+' ':''}${x.title}</div>`).join('');
    elSidebar.querySelectorAll('.item').forEach((it,idx)=>{
      it.addEventListener('click', ()=>{
        elSidebar.querySelectorAll('.item').forEach(x=>x.classList.remove('active'));
        it.classList.add('active');
        activeKey = it.getAttribute('data-key');
        setContent(tabs[activeKey](data));
      });
    });
    activeKey = all[0].key;
    setContent(tabs[activeKey](data));
  }

  // Bindings
  document.getElementById('mw-toggle').addEventListener('click', togglePanel);
  document.getElementById('mw-close').addEventListener('click', togglePanel);
  document.addEventListener('keydown', e=>{ if(e.key==='`' || e.key==='~') togglePanel(); });

  // KPIs + Filters
  mountKpis();
  mountFilters();

  // Search box reuses timeline render
  elSearch.addEventListener('input', ()=> setContent(tabs[activeKey](data)));

  // Sidebar
  mountSidebar();
})();
JS;

        $html = <<<HTML
<style>{$css}</style>
<div id="mw-debugbar" role="complementary" aria-label="Debug bar">
  <div class="bar">
    <div class="pill">DebugBar</div>
    <div class="pill">Elapsed: {$payload['_meta']['elapsed_ms']} ms</div>
    <div class="pill">Mem: {$payload['memory']['usage_mb'] ?? '-'} MB</div>
    <div class="pill">PHP: {$payload['php']['version'] ?? PHP_VERSION}</div>
    <div class="spacer"></div>
    <div class="tab" id="mw-toggle" title="Toggle panel (press ~)">Open</div>
    <div class="tab" id="mw-close" title="Hide panel">Hide</div>
    <div class="kbd">~</div>
  </div>
</div>

<div id="mw-panel" aria-label="Debug details panel">
  <div class="inner">
    <div class="sidebar" id="mw-sidebar"></div>
    <div class="content">
      <div id="mw-kpis"></div>
      <input id="mw-search" class="search" type="search" placeholder="Filter current tab…" />
      <div id="mw-content"></div>
    </div>
  </div>
</div>
<script>{$js}</script>
HTML;

        return $html;
    }
}
