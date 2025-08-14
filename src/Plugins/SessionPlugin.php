<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Plugins;

final class SessionPlugin extends AbstractPlugin
{
    public function name(): string
    {
        return 'session';
    }

    public function extendPayload(array $payload): array
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            return ['session' => ['status' => 'Session not started', 'data' => [], 'meta' => []]];
        }
        return ['session' => [
            'status' => 'Active',
            'id' => \session_id(),
            'name' => \session_name(),
            'save_path' => \session_save_path(),
            'cookie_params' => \session_get_cookie_params(),
            'data' => $_SESSION ?? [],
        ]];
    }

    public function tabs(): array
    {
        $renderer = <<<'JS'
function renderSession(d) {
  const s = d.session || {};
  if (!s.data) return `<div>No session data</div>`;
  return `<table class="mw"><tbody>
    <tr><th>Status</th><td>${escapeHtml(s.status||'')}</td></tr>
    <tr><th>ID</th><td>${escapeHtml(s.id||'')}</td></tr>
    <tr><th>Name</th><td>${escapeHtml(s.name||'')}</td></tr>
    <tr><th>Save Path</th><td>${escapeHtml(s.save_path||'')}</td></tr>
    <tr><th>Cookie Params</th><td class="mw-mono">${escapeHtml(JSON.stringify(s.cookie_params||{},null,2))}</td></tr>
    <tr><th>Data</th><td class="mw-mono">${escapeHtml(JSON.stringify(s.data||{},null,2))}</td></tr>
  </tbody></table>`;
}
JS;
        return [['key' => 'session', 'title' => 'Session', 'icon' => '🔑', 'order' => 300, 'renderer' => $renderer]];
    }
}
