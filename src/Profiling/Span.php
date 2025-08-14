<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Profiling;

final class Span
{
    public int $id;
    public string $label;
    public float $start;     // abs time: microtime(true)
    public ?float $end = null;
    public ?float $durationMs = null;
    public int $depth;       // nesting depth computed from stack
    public array $meta;

    public function __construct(int $id, string $label, float $start, int $depth, array $meta = [])
    {
        $this->id = $id;
        $this->label = $label;
        $this->start = $start;
        $this->depth = $depth;
        $this->meta = $meta;
    }

    public function close(float $end): void
    {
        $this->end = $end;
        $this->durationMs = round(($this->end - $this->start) * 1000, 2);
    }
}
