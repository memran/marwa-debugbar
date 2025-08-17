<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Config\HistoryConfig;
use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Contracts\Storage;
use Marwa\DebugBar\Profiling\Span;
use Psr\Log\LoggerInterface;

final class DebugBar
{
    private bool $enabled;
    /** @var array<string,Collector> */
    private array $collectors = [];
    private array $marks = [];
    private array $logs = [];
    private array $queries = [];
    private array $dumps = [];
    private int $maxDumps = 100;
    private ?LoggerInterface $logger = null;
    private float $start;

    private PluginManager $plugins;
    private ?HistoryManager $history = null;

    /** Spans (visual timeline) */
    private array $spans = [];           // completed spans + open placeholders
    private array $openSpanStack = [];   // stack of open span IDs
    private int $spanSeq = 0;

    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
        $this->start = microtime(true);
        $this->plugins = new PluginManager();
        $this->mark('request_start');
    }

    public function enable(): void { $this->enabled = true; }
    public function disable(): void { $this->enabled = false; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setLogger(LoggerInterface $logger): void { $this->logger = $logger; }

    public function addCollector(Collector $collector): self
    {
        $this->collectors[$collector->name()] = $collector;
        return $this;
    }

    public function mark(string $label): void
    {
        if (!$this->enabled) return;
        $this->marks[] = ['t' => microtime(true), 'label' => $label];
    }

    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) $this->logger->log($level, $message, $context);
        if (!$this->enabled) return;
        $this->logs[] = [
            'time' => round((microtime(true) - $this->start) * 1000, 2),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    public function addQuery(string $sql, array $params = [], float $durationMs = 0.0, ?string $conn = null): void
    {
        if (!$this->enabled) return;
        $this->queries[] = ['sql' => $sql, 'params' => $params, 'duration_ms' => $durationMs, 'connection' => $conn];
    }

    public function addDump(string $html, ?string $name = null, ?string $file = null, ?int $line = null): void
    {
        if (!$this->enabled) return;
        if (count($this->dumps) >= $this->maxDumps) array_shift($this->dumps);
        $this->dumps[] = [
            'name' => $name, 'file' => $file, 'line' => $line, 'html' => $html,
            'time' => round((microtime(true) - $this->start) * 1000, 2),
        ];
    }

    public function setVarDumperMaxDumps(int $max): void { $this->maxDumps = $max; }

    public function plugins(): PluginManager { return $this->plugins; }

    public function setHistory(Storage $storage, ?HistoryConfig $config = null): void
    {
        $cfg = $config ?? new HistoryConfig(enabled: true);
        $this->history = new HistoryManager($storage, $cfg);
    }
    public function history(): ?HistoryManager { return $this->history; }

    /* =========================
     * Spans API (visual timeline)
     * ========================= */
    /** Begin a nested span. Returns span id. */
    public function spanBegin(string $label, array $meta = []): int
    {
        if (!$this->enabled) return -1;
        $id = ++$this->spanSeq;
        $depth = count($this->openSpanStack);
        $span = new Span($id, $label, microtime(true), $depth, $meta);
        $this->openSpanStack[] = $id;
        $this->spans["open:$id"] = $span;
        return $id;
    }

    /** End a previously started span. */
    public function spanEnd(int $id): void
    {
        if (!$this->enabled) return;
        $key = "open:$id";
        if (!isset($this->spans[$key])) return;
        /** @var Span $span */
        $span = $this->spans[$key];
        $span->close(microtime(true));
        $idx = array_search($id, $this->openSpanStack, true);
        if ($idx !== false) array_splice($this->openSpanStack, $idx, 1);
        unset($this->spans[$key]);
        $this->spans[] = $span;
    }

    /** Measure a callable while recording a span. */
    public function measure(callable $fn, string $label, array $meta = []): mixed
    {
        $sid = $this->spanBegin($label, $meta);
        try {
            return $fn();
        } finally {
            if ($sid > 0) $this->spanEnd($sid);
        }
    }

    public function payload(): array
    {
        if (!$this->enabled) return [];

        // Safety: close orphan spans at payload time
        if (!empty($this->openSpanStack)) {
            foreach ($this->openSpanStack as $sid) {
                $key = "open:$sid";
                if (isset($this->spans[$key]) && $this->spans[$key] instanceof Span) {
                    $this->spans[$key]->close(microtime(true));
                    $this->spans[] = $this->spans[$key];
                    unset($this->spans[$key]);
                }
            }
            $this->openSpanStack = [];
        }

        $data = [
            '_meta' => [
                'generated_at' => date('c'),
                'elapsed_ms'   => round((microtime(true) - $this->start) * 1000, 2),
                'php_sapi'     => PHP_SAPI
            ],
            'timeline' => $this->marks,
            'logs'     => $this->logs,
            'queries'  => $this->queries,
            'dumps'    => $this->dumps,
        ];

        // Collectors
        foreach ($this->collectors as $name => $collector) {
            $data[$name] = $collector->collect();
        }

        // Convert spans to array for JSON
        $startAbs = $this->start;
        $spanArr = [];
        foreach ($this->spans as $s) {
            if (!$s instanceof Span) continue;
            $spanArr[] = [
                'label'       => $s->label,
                'start_ms'    => round(($s->start - $startAbs) * 1000, 2),
                'duration_ms' => $s->durationMs ?? 0.0,
                'depth'       => $s->depth,
                'meta'        => $s->meta,
            ];
        }
        $data['timeline_spans'] = $spanArr;

        // Plugins
        $data = $this->plugins->extendPayload($data);

        // History metadata
        if ($this->history?->isEnabled()) {
            $data['_history_meta'] = $this->history->recentMeta();
        }

        return $data;
    }
}
