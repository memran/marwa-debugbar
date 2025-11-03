<?php
declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Collectors\HtmlKit;
use Marwa\DebugBar\Core\DebugState;
use Psr\Log\LoggerInterface;

/**
 * Core orchestrator (trimmed to essentials for this refactor).
 * Holds runtime signal arrays (marks, logs, queries, dumps).
 */
final class DebugBar
{
    use HtmlKit;
    private bool $enabled;
    public float $start;

    private array $marks = [];
    private array $logs = [];
    private array $queries = [];
    private array $dumps = [];
    private array $exceptions = [];
    private int $maxDumps = 100;

    private ?LoggerInterface $logger = null;
    private CollectorManager $collectors;

    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
        $this->start = microtime(true);
        $this->collectors = new CollectorManager();
        $this->mark('request_start');
    }

    public function enable(): void { $this->enabled = true; }
    public function disable(): void { $this->enabled = false; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setLogger(LoggerInterface $logger): void { $this->logger = $logger; }

    /** Public API used by your app/framework hooks */
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
            'time'    => round((microtime(true) - $this->start) * 1000, 2),
            'level'   => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    public function addQuery(string $sql, array $params = [], float $durationMs = 0.0, ?string $conn = null): void
    {
        if (!$this->enabled) return;
      
        $this->queries[] = ['sql' => $sql, 'params' => $params, 'duration_ms' => $durationMs, 'connection' => $conn];

    }
    public function addDump(mixed $value,?string $name=null,?string $file = null, ?int $line = null)
    {
        if (!$this->enabled) return;
        if (count($this->dumps) >= $this->maxDumps) array_shift($this->dumps);
        $safe = htmlspecialchars(print_r($value, true), ENT_QUOTES, 'UTF-8');
        $html="<pre>$safe</pre>";
        $this->dumps[] = compact('name','file','line','html') + [
            'time' => round((microtime(true) - $this->start) * 1000, 2)
        ];
       
    }

    /** Expose the manager to register/auto-discover collectors lazily. */
    public function collectors(): CollectorManager
    {
        return $this->collectors;
    }

     // Capture an exception (uncaught or manually reported)
    public function addException(\Throwable $e): void
    {
        if (!$this->enabled) return;

        $nowMs = round((microtime(true) - $this->start) * 1000, 2);
        $item = [
            'type'     => $e::class,
            'message'  => $e->getMessage(),
            'code'     => (int)$e->getCode(),
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'time_ms'  => $nowMs,
            'trace'    => $e->getTraceAsString(),
            'chain'    => [],
        ];

        // previous chain (without huge traces by default)
        $p = $e->getPrevious();
        while ($p instanceof \Throwable) {
            $item['chain'][] = [
                'type'    => $p::class,
                'message' => $p->getMessage(),
                'code'    => (int)$p->getCode(),
                'file'    => $p->getFile(),
                'line'    => $p->getLine(),
                // 'trace' => $p->getTraceAsString(), // add if you want full chain traces
            ];
            $p = $p->getPrevious();
        }

        $this->exceptions[] = $item;

        // Optional: also mirror as an ERROR log entry
        $this->logs[] = [
            'time'    => $nowMs,
            'level'   => 'ERROR',
            'message' => $e::class . ': ' . $e->getMessage(),
            'context' => ['file' => $e->getFile(), 'line' => $e->getLine(), 'code' => (int)$e->getCode()],
        ];
    }

    /** Optional helper: register global handlers (enable only in dev) */
    public function registerExceptionHandlers(bool $capturePhpErrorsAsExceptions = true): void
    {
        set_exception_handler(function (\Throwable $e) {
            $this->addException($e);
            // Let framework render its error page; we only capture.
        });

        if ($capturePhpErrorsAsExceptions) {
            set_error_handler(function (int $severity, string $message, ?string $file = null, ?int $line = null) {
                // Convert PHP errors to ErrorException so they’re captured too
                $this->addException(new \ErrorException($message, 0, $severity, (string)$file, (int)$line));
                return false; // allow normal error handling to continue
            });
        }
        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->addException(new \ErrorException($err['message'] ?? 'Fatal error', 0, $err['type'] ?? 0, $err['file'] ?? 'unknown', (int)($err['line'] ?? 0)));
            }
        });
    }
    /** Build immutable state snapshot for collectors. */
    public function state(): DebugState
    {
        return new DebugState(
            requestStart: $this->start,
            marks: $this->marks,
            logs: $this->logs,
            queries: $this->queries,
            dumps: $this->dumps,
            exceptions: $this->exceptions,
        );
    }


}
