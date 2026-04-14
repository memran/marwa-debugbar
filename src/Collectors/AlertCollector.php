<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\AlertConfig;
use Marwa\DebugBar\Core\DebugState;
use Marwa\DebugBar\Core\RuntimeMetrics;

final class AlertCollector implements Collector
{
    use HtmlKit;

    public static function key(): string
    {
        return 'alerts';
    }

    public static function label(): string
    {
        return 'Alerts';
    }

    public static function icon(): string
    {
        return '🚨';
    }

    public static function order(): int
    {
        return 105;
    }

    /**
     * @return array{
     *   enabled:bool,
     *   total:int,
     *   warnings:int,
     *   critical:int,
     *   thresholds:array{slow_query_ms:float,slow_request_ms:float,slow_span_ms:float,high_memory_mb:float,large_response_bytes:int},
     *   items:list<array{
     *     severity:string,
     *     type:string,
     *     message:string,
     *     metric:string,
     *     context:string,
     *     value:float|int,
     *     threshold:float|int
     *   }>
     * }
     */
    public function collect(DebugState $state): array
    {
        $config = AlertConfig::fromEnv();
        if (!$config->enabled) {
            return [
                'enabled' => false,
                'total' => 0,
                'warnings' => 0,
                'critical' => 0,
                'thresholds' => $this->thresholds($config),
                'items' => [],
            ];
        }

        $alerts = [];

        foreach ($state->queries as $query) {
            if ($query['duration_ms'] < $config->slowQueryMs) {
                continue;
            }

            $alerts[] = $this->makeAlert(
                type: 'slow_query',
                value: $query['duration_ms'],
                threshold: $config->slowQueryMs,
                metric: $this->num($query['duration_ms']) . ' ms',
                message: 'Query exceeded the slow query threshold.',
                context: trim(($query['connection'] ?? 'default') . ' | ' . $query['sql'], ' |')
            );
        }

        $requestDurationMs = RuntimeMetrics::requestDurationMs($state);
        if ($requestDurationMs >= $config->slowRequestMs) {
            $alerts[] = $this->makeAlert(
                type: 'slow_request',
                value: $requestDurationMs,
                threshold: $config->slowRequestMs,
                metric: $this->num($requestDurationMs) . ' ms',
                message: 'Request duration exceeded the slow request threshold.',
                context: 'Total request duration'
            );
        }

        foreach (RuntimeMetrics::timelineSpans($state) as $span) {
            if ($span['delta_ms'] < $config->slowSpanMs) {
                continue;
            }

            $alerts[] = $this->makeAlert(
                type: 'slow_span',
                value: $span['delta_ms'],
                threshold: $config->slowSpanMs,
                metric: $this->num($span['delta_ms']) . ' ms',
                message: 'Timeline span exceeded the slow span threshold.',
                context: $span['from_label'] . ' -> ' . $span['to_label']
            );
        }

        $memoryPeakMb = RuntimeMetrics::memoryPeakMb();
        if ($memoryPeakMb >= $config->highMemoryMb) {
            $alerts[] = $this->makeAlert(
                type: 'high_memory',
                value: $memoryPeakMb,
                threshold: $config->highMemoryMb,
                metric: $this->num($memoryPeakMb) . ' MB',
                message: 'Peak memory usage exceeded the configured threshold.',
                context: 'memory_get_peak_usage(true)'
            );
        }

        $responseBytes = RuntimeMetrics::responseBytes();
        if ($responseBytes >= $config->largeResponseBytes) {
            $alerts[] = $this->makeAlert(
                type: 'large_response',
                value: $responseBytes,
                threshold: $config->largeResponseBytes,
                metric: $this->humanBytes($responseBytes),
                message: 'Buffered response size exceeded the configured threshold.',
                context: 'Output buffer size'
            );
        }

        $warnings = count(array_filter($alerts, static fn(array $alert): bool => $alert['severity'] === 'warning'));
        $critical = count($alerts) - $warnings;

        return [
            'enabled' => true,
            'total' => count($alerts),
            'warnings' => $warnings,
            'critical' => $critical,
            'thresholds' => $this->thresholds($config),
            'items' => $alerts,
        ];
    }

    public function renderHtml(array $data): string
    {
        if (!(bool) ($data['enabled'] ?? true)) {
            return $this->card('Alerts', '<div class="mw-muted">Alerts are disabled via <code class="mw-mono">DEBUGBAR_ALERTS_ENABLED=0</code>.</div>');
        }

        $summary = '<div class="mw-grid-3">'
            . $this->stat('Total alerts', '<span class="mw-mono">' . $this->e((string) ($data['total'] ?? 0)) . '</span>')
            . $this->stat('Warnings', '<span class="mw-badgel mw-sev-warning">' . $this->e((string) ($data['warnings'] ?? 0)) . '</span>')
            . $this->stat('Critical', '<span class="mw-badgel mw-sev-critical">' . $this->e((string) ($data['critical'] ?? 0)) . '</span>')
            . '</div>';

        $items = $data['items'] ?? [];
        if ($items === []) {
            return $summary . $this->card('Alerts', '<div class="mw-muted">No performance alerts triggered.</div>' . $this->renderThresholds($data));
        }

        $rows = '';
        foreach ($items as $alert) {
            $badgeClass = $alert['severity'] === 'critical' ? 'mw-sev-critical' : 'mw-sev-warning';

            $rows .= $this->tr(
                [
                    '<span class="mw-badgel ' . $badgeClass . '">' . $this->e($alert['severity']) . '</span>',
                    '<span class="mw-mono">' . $this->e($alert['type']) . '</span>',
                    $this->e($alert['message']),
                    '<span class="mw-mono">' . $this->e($alert['metric']) . '</span>',
                    '<span class="mw-mono">' . $this->e($alert['context']) . '</span>',
                ],
                [null, null, null, 'right', null]
            );
        }

        return $summary
            . $this->table(['Severity', 'Type', 'Message', 'Metric', 'Context'], $rows, [null, null, null, 'right', null])
            . '<div style="margin-top:12px">' . $this->renderThresholds($data) . '</div>';
    }

    /**
     * @return array{slow_query_ms:float,slow_request_ms:float,slow_span_ms:float,high_memory_mb:float,large_response_bytes:int}
     */
    private function thresholds(AlertConfig $config): array
    {
        return [
            'slow_query_ms' => $config->slowQueryMs,
            'slow_request_ms' => $config->slowRequestMs,
            'slow_span_ms' => $config->slowSpanMs,
            'high_memory_mb' => $config->highMemoryMb,
            'large_response_bytes' => $config->largeResponseBytes,
        ];
    }

    /**
     * @return array{severity:string,type:string,message:string,metric:string,context:string,value:float|int,threshold:float|int}
     */
    private function makeAlert(
        string $type,
        float|int $value,
        float|int $threshold,
        string $metric,
        string $message,
        string $context
    ): array {
        return [
            'severity' => $this->severity($value, $threshold),
            'type' => $type,
            'message' => $message,
            'metric' => $metric,
            'context' => $context,
            'value' => $value,
            'threshold' => $threshold,
        ];
    }

    private function severity(float|int $value, float|int $threshold): string
    {
        if ($threshold > 0 && $value >= ($threshold * 2)) {
            return 'critical';
        }

        return 'warning';
    }

    /**
     * @param array{
     *   thresholds?:array{
     *     slow_query_ms?:float,
     *     slow_request_ms?:float,
     *     slow_span_ms?:float,
     *     high_memory_mb?:float,
     *     large_response_bytes?:int
     *   }
     * } $data
     */
    private function renderThresholds(array $data): string
    {
        $thresholds = $data['thresholds'] ?? [];

        return $this->card(
            'Thresholds',
            '<div class="mw-grid-3">'
            . $this->stat('Slow query', '<span class="mw-mono">' . $this->e($this->num((float) ($thresholds['slow_query_ms'] ?? 0.0))) . ' ms</span>')
            . $this->stat('Slow request', '<span class="mw-mono">' . $this->e($this->num((float) ($thresholds['slow_request_ms'] ?? 0.0))) . ' ms</span>')
            . $this->stat('Slow span', '<span class="mw-mono">' . $this->e($this->num((float) ($thresholds['slow_span_ms'] ?? 0.0))) . ' ms</span>')
            . $this->stat('High memory', '<span class="mw-mono">' . $this->e($this->num((float) ($thresholds['high_memory_mb'] ?? 0.0))) . ' MB</span>')
            . $this->stat('Large response', '<span class="mw-mono">' . $this->e($this->humanBytes((int) ($thresholds['large_response_bytes'] ?? 0))) . '</span>')
            . '</div>'
        );
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
