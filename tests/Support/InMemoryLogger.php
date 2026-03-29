<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests\Support;

use Psr\Log\AbstractLogger;

final class InMemoryLogger extends AbstractLogger
{
    /** @var list<array{level:string,message:string,context:array<string,mixed>}> */
    public array $records = [];

    /**
     * @param array<string,mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}
