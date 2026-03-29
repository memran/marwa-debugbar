<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class KpiCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'kpi';
    }

    public static function label(): string
    {
        return 'KPIs';
    }

    public static function icon(): string
    {
        return '📊';
    }

    public static function order(): int
    {
        return 100;
    }

    public function collect(DebugState $state): array
    {
        $endTimestamp = $this->lastMarkTimestamp($state) ?? microtime(true);
        $sqlTimeMs = 0.0;

        foreach ($state->queries as $query) {
            $sqlTimeMs += $query['duration_ms'];
        }

        $server = $_SERVER;
        $route = $server['REQUEST_URI'] ?? ($server['argv'][0] ?? 'CLI');

        return [
            'duration_ms' => round(($endTimestamp - $state->requestStart) * 1000, 2),
            'sql_count' => count($state->queries),
            'sql_time_ms' => round($sqlTimeMs, 2),
            'logs_count' => count($state->logs),
            'dumps_count' => count($state->dumps),
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'route' => $route,
            'status' => http_response_code() ?: 200,
            'response_bytes' => $this->detectResponseSize(),
        ];
    }

    public function renderHtml(array $data): string
    {
        $values = [
            'duration' => $this->num((float) ($data['duration_ms'] ?? 0.0)) . ' ms',
            'queries' => (string) ($data['sql_count'] ?? 0),
            'query time' => $this->num((float) ($data['sql_time_ms'] ?? 0.0)) . ' ms',
            'logs' => (string) ($data['logs_count'] ?? 0),
            'dumps' => (string) ($data['dumps_count'] ?? 0),
            'peak memory' => $this->num((float) ($data['memory_peak_mb'] ?? 0.0)) . ' MB',
            'status' => (string) ($data['status'] ?? 200),
            'response' => $this->humanBytes((int) ($data['response_bytes'] ?? 0)),
        ];

        $html = '';
        foreach ($values as $label => $value) {
            $html .= '<div class="mw-kpi"><span class="k">' . $this->e($label) . '</span><span class="v">' . $this->e($value) . '</span></div>';
        }

        return $html;
    }

    private function lastMarkTimestamp(DebugState $state): ?float
    {
        if ($state->marks === []) {
            return null;
        }

        $marks = $state->marks;
        $last = end($marks);

        return $last['t'];
    }

    private function detectResponseSize(): int
    {
        if (ob_get_level() === 0) {
            return 0;
        }

        $length = 0;
        foreach (ob_get_status(true) as $buffer) {
            $length += (int) ($buffer['buffer_used'] ?? 0);
        }

        return $length;
    }

    private function humanBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
    }
}
