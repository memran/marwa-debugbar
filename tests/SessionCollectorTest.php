<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\Collectors\SessionCollector;
use Marwa\DebugBar\Core\DebugState;
use PHPUnit\Framework\TestCase;

final class SessionCollectorTest extends TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testCollectorRedactsSensitiveSessionValues(): void
    {
        session_id('marwa-debugbar-test');
        session_start();
        $_SESSION = [
            'user_id' => 10,
            'api_token' => 'secret-token',
            'nested' => ['csrf_token' => 'csrf-secret'],
        ];

        $collector = new SessionCollector();
        $data = $collector->collect(new DebugState(microtime(true), [], [], [], [], []));

        self::assertTrue($data['active']);
        self::assertSame('[redacted]', $data['data']['api_token']);
        self::assertSame('[redacted]', $data['data']['nested']['csrf_token']);
        self::assertStringEndsWith('test', $data['meta']['id']);

        session_destroy();
    }
}
