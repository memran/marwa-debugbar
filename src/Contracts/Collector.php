<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Contracts;

interface Collector
{
    public function name(): string;
    public function collect(): array;
}
