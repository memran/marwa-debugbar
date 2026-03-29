<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Core\DebugState;
use Psr\Log\LoggerInterface;
use Throwable;

final class DebugBar
{
    private bool $enabled;
    private float $startTime;

    /** @var list<array{t:float,label:string}> */
    private array $marks = [];

    /** @var list<array{time:float,level:string,message:string,context:array<string,mixed>}> */
    private array $logs = [];

    /** @var list<array{sql:string,params:array<int|string,mixed>,duration_ms:float,connection:?string}> */
    private array $queries = [];

    /** @var list<array{name:?string,file:?string,line:?int,html:string,time:float}> */
    private array $dumps = [];

    /** @var list<array{type:string,message:string,code:int,file:string,line:int,time_ms:float,trace:string,chain:list<array{type:string,message:string,code:int,file:string,line:int}>}> */
    private array $exceptions = [];

    private int $maxDumps = 100;
    private ?LoggerInterface $logger = null;
    private CollectorManager $collectors;

    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
        $this->startTime = microtime(true);
        $this->collectors = new CollectorManager();
        $this->mark('request_start');
    }

    public function enable(): void
    {
        $this->enabled = true;
        if ($this->marks === []) {
            $this->mark('request_start');
        }
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function setMaxDumps(int $maxDumps): void
    {
        $this->maxDumps = max(1, $maxDumps);
    }

    public function getStartTime(): float
    {
        return $this->startTime;
    }

    public function elapsedMilliseconds(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    public function collectors(): CollectorManager
    {
        return $this->collectors;
    }

    public function mark(string $label): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->marks[] = [
            't' => microtime(true),
            'label' => trim($label) !== '' ? $label : 'mark',
        ];
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger !== null) {
            $this->logger->log($level, $message, $context);
        }

        if (!$this->enabled) {
            return;
        }

        $this->logs[] = [
            'time' => $this->elapsedMilliseconds(),
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @param array<int|string,mixed> $params
     */
    public function addQuery(string $sql, array $params = [], float $durationMs = 0.0, ?string $connection = null): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration_ms' => max(0.0, round($durationMs, 2)),
            'connection' => $connection,
        ];
    }

    public function addDump(mixed $value, ?string $name = null, ?string $file = null, ?int $line = null): void
    {
        if (!$this->enabled) {
            return;
        }

        if (count($this->dumps) >= $this->maxDumps) {
            array_shift($this->dumps);
        }

        $html = '<pre class="mw-pre">' . htmlspecialchars($this->stringifyDump($value), ENT_QUOTES, 'UTF-8') . '</pre>';
        $this->dumps[] = [
            'name' => $name,
            'file' => $file,
            'line' => $line,
            'html' => $html,
            'time' => $this->elapsedMilliseconds(),
        ];
    }

    public function addException(Throwable $exception): void
    {
        if (!$this->enabled) {
            return;
        }

        $nowMs = $this->elapsedMilliseconds();
        $item = [
            'type' => $exception::class,
            'message' => $exception->getMessage(),
            'code' => (int) $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'time_ms' => $nowMs,
            'trace' => $exception->getTraceAsString(),
            'chain' => [],
        ];

        $previous = $exception->getPrevious();
        while ($previous instanceof Throwable) {
            $item['chain'][] = [
                'type' => $previous::class,
                'message' => $previous->getMessage(),
                'code' => (int) $previous->getCode(),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ];
            $previous = $previous->getPrevious();
        }

        $this->exceptions[] = $item;
        $this->log('error', $exception::class . ': ' . $exception->getMessage(), [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => (int) $exception->getCode(),
        ]);
    }

    public function registerExceptionHandlers(bool $capturePhpErrorsAsExceptions = true): void
    {
        set_exception_handler(function (Throwable $exception): void {
            $this->addException($exception);
        });

        if ($capturePhpErrorsAsExceptions) {
            set_error_handler(function (int $severity, string $message, ?string $file = null, ?int $line = null): bool {
                if (!(error_reporting() & $severity)) {
                    return false;
                }

                $this->addException(new \ErrorException($message, 0, $severity, (string) $file, (int) $line));
                return false;
            });
        }

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error === null) {
                return;
            }

            if (!in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                return;
            }

            $this->addException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        });
    }

    public function state(): DebugState
    {
        return new DebugState(
            requestStart: $this->startTime,
            marks: $this->marks,
            logs: $this->logs,
            queries: $this->queries,
            dumps: $this->dumps,
            exceptions: $this->exceptions,
        );
    }

    private function stringifyDump(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return rtrim(print_r($value, true));
    }
}
