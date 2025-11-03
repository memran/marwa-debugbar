<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class SessionCollector implements Collector
{
    use HtmlKit;
    public static function key(): string   { return 'session'; }
    public static function label(): string { return 'Session'; }
    public static function icon(): string  { return '🔑'; }
    public static function order(): int    { return 300; }

    public function collect(DebugState $state): array
    {
        if (session_status() !== \PHP_SESSION_ACTIVE) {
            return ['active' => false, 'meta' => [], 'data' => []];
        }
        return [
            'active' => true,
            'meta'   => [
                'id'          => \session_id(),
                'name'        => \session_name(),
                'save_path'   => \session_save_path(),
                'cookie'      => \session_get_cookie_params(),
            ],
            'data'   => $_SESSION ?? [],
        ];
    }

    public function renderHtml(array $data): string
    {
        $content= $this->renderSession($data);
        return '<div style="max-height:45vh; overflow:auto">'.$content.'</div>';
    }

     private function renderSession(array $s): string
    {
       
        if (!$s['active']) return '<div style="color:#9ca3af">Session is not Active.</div>';

        $meta = '<div class="mw-grid-3" style="margin-bottom:10px;">'
              . $this->stat('Session ID',  '<span class="mw-mono">'.$this->e((string)($s['meta']['id'] ?? '')).'</span>')
              . $this->stat('Session Name','<span class="mw-mono">'.$this->e((string)($s['meta']['name'] ?? '')).'</span>')
              . $this->stat('Attrs Count', $this->e((string)count((array)($s['data'] ?? []))))
              . '</div>';

        $grid = '<div class="mw-grid-2" style="max-height:45vh;">'
              . $this->card('Attributes', $this->preJson($s['data'] ?? []))
              . $this->card('Flashes',    $this->preJson($s['data']['flashes'] ?? []))
              . $this->card('Meta',       $this->preJson($s['meta'] ?? []))
              . '</div>';

        return $meta . $grid;
    }
}
