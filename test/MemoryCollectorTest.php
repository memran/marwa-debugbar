<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Marwa\DebugBar\Collectors\MemoryCollector;

final class MemoryCollectorTest extends TestCase
{
    public function testCollectHasKeys(): void
    {
        $c = new MemoryCollector();
        $d = $c->collect();
        $this->assertArrayHasKey('usage_mb', $d);
        $this->assertArrayHasKey('peak_usage_mb', $d);
        $this->assertArrayHasKey('limit', $d);
    }
}
