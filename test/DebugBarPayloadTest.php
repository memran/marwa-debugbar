<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Collectors\PhpCollector;

final class DebugBarPayloadTest extends TestCase
{
    public function testDisabledYieldsEmpty(): void
    {
        $db = new DebugBar(false);
        $this->assertSame([], $db->payload());
    }

    public function testEnabledHasMetaAndPhp(): void
    {
        $db = new DebugBar(true);
        $db->addCollector(new PhpCollector());
        $payload = $db->payload();
        $this->assertArrayHasKey('_meta', $payload);
        $this->assertArrayHasKey('php', $payload);
        $this->assertIsArray($payload['php']);
    }
}
