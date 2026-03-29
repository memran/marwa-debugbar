<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\CollectorManager;
use Marwa\DebugBar\Contracts\CollectorException;
use Marwa\DebugBar\Core\DebugState;
use Marwa\DebugBar\Tests\Fixtures\BrokenCollector;
use Marwa\DebugBar\Tests\Fixtures\LateCollector;
use Marwa\DebugBar\Tests\Fixtures\TestCollector;
use Marwa\DebugBar\Tests\Fixtures\TestCollectorDuplicateKey;
use PHPUnit\Framework\TestCase;

final class CollectorManagerTest extends TestCase
{
    public function testDuplicateKeysAreRejected(): void
    {
        $manager = new CollectorManager();
        $manager->register(TestCollector::class);

        $this->expectException(CollectorException::class);
        $manager->register(TestCollectorDuplicateKey::class);
    }

    public function testRenderAllSortsCollectorsAndContainsCollectorFailures(): void
    {
        $manager = new CollectorManager();
        $manager->register(LateCollector::class);
        $manager->register(BrokenCollector::class);
        $manager->register(TestCollector::class);

        $rows = $manager->renderAll(new DebugState(microtime(true), [], [], [], [], []));

        self::assertSame(['test', 'broken', 'late'], array_column($rows, 'key'));
        self::assertStringContainsString('RuntimeException', $rows[1]['html']);
    }
}
