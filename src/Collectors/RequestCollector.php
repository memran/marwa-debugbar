<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class RequestCollector implements Collector
{
    use HtmlKit;
    public static function key(): string { return 'request'; }
    public static function label(): string { return 'Request'; }
    public static function icon(): string { return '🌐'; }
    public static function order(): int { return 170; }

    public function collect(DebugState $state): array
    {
        $server = $_SERVER ?? [];
        return [
            'method'  => $server['REQUEST_METHOD'] ?? 'CLI',
            'uri'     => $server['REQUEST_URI'] ?? ($server['argv'][0] ?? ''),
            'ip'      => $server['REMOTE_ADDR'] ?? null,
            'ua'      => $server['HTTP_USER_AGENT'] ?? null,
            'headers' => $this->headers(),
            'get'     => $_GET ?? [],
            'post'    => $_POST ?? [],
        ];
    }

    public function renderHtml(array $d): string
    {
        $content = $this->renderRequestTable($d);
        return '<div style="max-height:45vh; overflow:auto">'.$content.'</div>';
    }

    private function headers(): array
    {
        $out = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $h = strtolower(str_replace('_', '-', substr($k, 5)));
                $out[$h] = $v;
            }
        }
        return $out;
    }



    private function renderRequestTable(array $p): string
    {
        $r = $p??[]; //$p['request'] ?? [];
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
            $trs .= '<tr><th style="width:120px;color:#9ca3af;text-align:left;padding:8px;border-bottom:1px solid var(--mw-border)">'.$this->e($k).'</th>
            <td style="padding:8px;color:#9ca3af;border-bottom:1px solid var(--mw-border)">'.$v.'</td></tr>';
        }
        return '<table class="mw-table" style="border:0"><tbody>'.$trs.'</tbody></table>';
    }
}
