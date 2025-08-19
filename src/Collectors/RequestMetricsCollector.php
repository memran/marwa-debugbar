<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class RequestMetricsCollector implements Collector
{
    private ?string $route = null;
    private ?int $status = null;
    private ?int $responseBytes = null;
    private float $phpStart;
    private ?float $phpEnd = null;

    private int $dumpCount = 0;
    private int $logCount = 0;
    private array $logLevels = [];
    private int $queryCount = 0;
    private float $queryTimeMs = 0.0;

    public function __construct(float $phpStart)
    {
        $this->phpStart = $phpStart;
    }
    public function name(): string
    {
        return 'request_metrics';
    }
    public function finish(): void
    {
        $this->phpEnd = microtime(true);
    }
    public function setRoute(?string $route): void
    {
        $this->route = $route;
    }
    public function setStatus(?int $status): void
    {
        $this->status = $status;
    }
    public function setResponseBytes(?int $bytes): void
    {
        $this->responseBytes = $bytes;
    }
    public function incDumpCount(): void
    {
        $this->dumpCount++;
    }
    public function incLog(string $level): void
    {
        $this->logCount++;
        $lvl = strtoupper($level);
        $this->logLevels[$lvl] = ($this->logLevels[$lvl] ?? 0) + 1;
    }
    public function incQuery(float $durationMs): void
    {
        $this->queryCount++;
        $this->queryTimeMs += $durationMs;
    }
    public function collect(): array
    {
        $durationMs = $this->phpEnd ? round(($this->phpEnd - $this->phpStart) * 1000, 2) : null;
        return [
            'route'          => $this->route,
            'status'         => $this->status,
            'duration_ms'    => $durationMs,
            'response_bytes' => $this->responseBytes,
            'memory_peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
            'dumps'          => $this->dumpCount,
            'logs'           => $this->logCount,
            'log_levels'     => $this->logLevels,
            'queries'        => $this->queryCount,
            'queries_time_ms' => round($this->queryTimeMs, 2),
        ];
    }

     public function renderHTML():string
    {
        return '';
    }
}
