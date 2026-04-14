<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\Collectors\AlertCollector;
use Marwa\DebugBar\Core\DebugState;
use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;
use PHPUnit\Framework\TestCase;

final class AlertCollectorTest extends TestCase
{
    /** @var array<string,string|false> */
    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->alertEnvNames() as $name) {
            $this->originalEnv[$name] = getenv($name);
            putenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            if ($value === false) {
                putenv($name);
                continue;
            }

            putenv($name . '=' . $value);
        }

        parent::tearDown();
    }

    public function testNoAlertsWhenMetricsStayBelowThresholds(): void
    {
        $collector = new AlertCollector();
        $state = new DebugState(
            requestStart: 10.0,
            marks: [
                ['t' => 10.0, 'label' => 'request_start'],
                ['t' => 10.05, 'label' => 'boot'],
                ['t' => 10.10, 'label' => 'done'],
            ],
            logs: [],
            queries: [
                ['sql' => 'SELECT 1', 'params' => [], 'duration_ms' => 15.0, 'connection' => 'mysql'],
            ],
            dumps: [],
            exceptions: []
        );

        $data = $collector->collect($state);

        self::assertTrue($data['enabled']);
        self::assertSame(0, $data['total']);
        self::assertSame([], $data['items']);
    }

    public function testSlowQueryAlertWhenQueryDurationExceedsThreshold(): void
    {
        putenv('DEBUGBAR_SLOW_QUERY_MS=100');
        $collector = new AlertCollector();

        $data = $collector->collect(new DebugState(
            requestStart: 10.0,
            marks: [['t' => 10.2, 'label' => 'request_end']],
            logs: [],
            queries: [
                ['sql' => 'SELECT * FROM users', 'params' => [], 'duration_ms' => 140.0, 'connection' => 'mysql'],
            ],
            dumps: [],
            exceptions: []
        ));

        self::assertSame(1, $data['total']);
        self::assertSame('slow_query', $data['items'][0]['type']);
        self::assertSame('warning', $data['items'][0]['severity']);
        self::assertStringContainsString('SELECT * FROM users', $data['items'][0]['context']);
    }

    public function testSlowRequestAlertWhenDurationExceedsThreshold(): void
    {
        putenv('DEBUGBAR_SLOW_REQUEST_MS=1000');
        $collector = new AlertCollector();

        $data = $collector->collect(new DebugState(
            requestStart: 20.0,
            marks: [['t' => 21.25, 'label' => 'response']],
            logs: [],
            queries: [],
            dumps: [],
            exceptions: []
        ));

        self::assertSame('slow_request', $data['items'][0]['type']);
        self::assertSame('1,250.00 ms', number_format((float) $data['items'][0]['value'], 2) . ' ms');
    }

    public function testSlowSpanAlertWhenMarkDeltaExceedsThreshold(): void
    {
        putenv('DEBUGBAR_SLOW_SPAN_MS=250');
        $collector = new AlertCollector();

        $data = $collector->collect(new DebugState(
            requestStart: 5.0,
            marks: [
                ['t' => 5.0, 'label' => 'request_start'],
                ['t' => 5.05, 'label' => 'bootstrap'],
                ['t' => 5.45, 'label' => 'controller'],
            ],
            logs: [],
            queries: [],
            dumps: [],
            exceptions: []
        ));

        self::assertSame(1, $data['total']);
        self::assertSame('slow_span', $data['items'][0]['type']);
        self::assertSame('bootstrap -> controller', $data['items'][0]['context']);
    }

    public function testSeverityBecomesCriticalAtDoubleThreshold(): void
    {
        putenv('DEBUGBAR_SLOW_QUERY_MS=100');
        $collector = new AlertCollector();

        $data = $collector->collect(new DebugState(
            requestStart: 10.0,
            marks: [['t' => 10.3, 'label' => 'done']],
            logs: [],
            queries: [
                ['sql' => 'SELECT * FROM posts', 'params' => [], 'duration_ms' => 200.0, 'connection' => 'pgsql'],
            ],
            dumps: [],
            exceptions: []
        ));

        self::assertSame('critical', $data['items'][0]['severity']);
        self::assertSame(0, $data['warnings']);
        self::assertSame(1, $data['critical']);
    }

    public function testCollectorRendersSummaryContent(): void
    {
        putenv('DEBUGBAR_SLOW_QUERY_MS=100');
        $collector = new AlertCollector();

        $html = $collector->renderHtml($collector->collect(new DebugState(
            requestStart: 1.0,
            marks: [['t' => 1.5, 'label' => 'done']],
            logs: [],
            queries: [
                ['sql' => 'SELECT * FROM widgets', 'params' => [], 'duration_ms' => 150.0, 'connection' => 'mysql'],
            ],
            dumps: [],
            exceptions: []
        )));

        self::assertStringContainsString('Total alerts', $html);
        self::assertStringContainsString('Severity', $html);
        self::assertStringContainsString('slow_query', $html);
    }

    public function testAlertsCanBeDisabledViaEnv(): void
    {
        putenv('DEBUGBAR_ALERTS_ENABLED=0');
        $collector = new AlertCollector();

        $data = $collector->collect(new DebugState(
            requestStart: 10.0,
            marks: [['t' => 12.5, 'label' => 'done']],
            logs: [],
            queries: [
                ['sql' => 'SELECT * FROM heavy_table', 'params' => [], 'duration_ms' => 400.0, 'connection' => 'mysql'],
            ],
            dumps: [],
            exceptions: []
        ));

        self::assertFalse($data['enabled']);
        self::assertSame(0, $data['total']);
        self::assertStringContainsString('Alerts are disabled', $collector->renderHtml($data));
    }

    public function testRendererShowsAlertsTab(): void
    {
        putenv('DEBUGBAR_SLOW_QUERY_MS=100');

        $bar = new DebugBar(true);
        $bar->collectors()->register(AlertCollector::class);
        $bar->addQuery('SELECT * FROM widgets', [], 150.0, 'mysql');
        $bar->mark('done');

        $html = (new Renderer($bar))->render();

        self::assertStringContainsString('data-key="alerts"', $html);
        self::assertStringContainsString('Alerts', $html);
        self::assertStringContainsString('slow_query', $html);
    }

    /**
     * @return list<string>
     */
    private function alertEnvNames(): array
    {
        return [
            'DEBUGBAR_ALERTS_ENABLED',
            'DEBUGBAR_SLOW_QUERY_MS',
            'DEBUGBAR_SLOW_REQUEST_MS',
            'DEBUGBAR_SLOW_SPAN_MS',
            'DEBUGBAR_HIGH_MEMORY_MB',
            'DEBUGBAR_LARGE_RESPONSE_BYTES',
        ];
    }
}
