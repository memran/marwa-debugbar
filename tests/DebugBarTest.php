<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Tests\Support\InMemoryLogger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DebugBarTest extends TestCase
{
    public function testDisabledBarDoesNotCollectStateButStillLogsToConfiguredLogger(): void
    {
        $bar = new DebugBar(false);
        $logger = new InMemoryLogger();
        $bar->setLogger($logger);

        $bar->mark('boot');
        $bar->log('info', 'message', ['key' => 'value']);
        $bar->addQuery('SELECT 1');
        $bar->addDump('<script>alert(1)</script>');
        $bar->addException(new RuntimeException('boom'));

        $state = $bar->state();

        self::assertCount(0, $state->marks);
        self::assertCount(0, $state->logs);
        self::assertCount(0, $state->queries);
        self::assertCount(0, $state->dumps);
        self::assertCount(0, $state->exceptions);
        self::assertCount(1, $logger->records);
    }

    public function testEnabledBarCapsDumpsAndEscapesValues(): void
    {
        $bar = new DebugBar(true);
        $bar->setMaxDumps(2);

        $bar->addDump('first');
        $bar->addDump('<script>alert(1)</script>', 'payload');
        $bar->addDump('third');

        $state = $bar->state();

        self::assertCount(2, $state->dumps);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $state->dumps[0]['html']);
        self::assertSame('third', strip_tags($state->dumps[1]['html']));
    }

    public function testExceptionsAreRecordedAndMirroredToLogs(): void
    {
        $bar = new DebugBar(true);

        $bar->addException(new RuntimeException('failure'));
        $state = $bar->state();

        self::assertCount(1, $state->exceptions);
        self::assertCount(1, $state->logs);
        self::assertSame('ERROR', $state->logs[0]['level']);
        self::assertStringContainsString('RuntimeException: failure', $state->logs[0]['message']);
    }
}
