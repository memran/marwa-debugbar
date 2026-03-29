<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\Collectors\KpiCollector;
use Marwa\DebugBar\Collectors\MemoryCollector;
use Marwa\DebugBar\Collectors\PhpCollector;
use Marwa\DebugBar\Collectors\TimelineCollector;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testDisabledDebugBarRendersNothing(): void
    {
        $renderer = new Renderer(new DebugBar(false));

        self::assertSame('', $renderer->render());
    }

    public function testRendererBuildsTabsForEnabledCollectors(): void
    {
        $bar = new DebugBar(true);
        $bar->collectors()->register(KpiCollector::class);
        $bar->collectors()->register(TimelineCollector::class);
        $bar->collectors()->register(MemoryCollector::class);
        $bar->collectors()->register(PhpCollector::class);
        $bar->mark('boot');

        $html = (new Renderer($bar))->render();

        self::assertStringContainsString('mwdbg-root', $html);
        self::assertStringContainsString('data-key="timeline"', $html);
        self::assertStringContainsString('Elapsed:', $html);
    }
}
