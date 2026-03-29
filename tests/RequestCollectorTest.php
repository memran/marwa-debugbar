<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Tests;

use Marwa\DebugBar\Collectors\RequestCollector;
use Marwa\DebugBar\Core\DebugState;
use PHPUnit\Framework\TestCase;

final class RequestCollectorTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $serverBackup;

    /** @var array<string,mixed> */
    private array $getBackup;

    /** @var array<string,mixed> */
    private array $postBackup;

    /** @var array<string,mixed> */
    private array $cookieBackup;

    /** @var array<string,mixed> */
    private array $filesBackup;

    protected function setUp(): void
    {
        $this->serverBackup = $_SERVER;
        $this->getBackup = $_GET;
        $this->postBackup = $_POST;
        $this->cookieBackup = $_COOKIE;
        $this->filesBackup = $_FILES;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        $_GET = $this->getBackup;
        $_POST = $this->postBackup;
        $_COOKIE = $this->cookieBackup;
        $_FILES = $this->filesBackup;
    }

    public function testCollectorRedactsSensitiveRequestData(): void
    {
        $_SERVER = [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/login',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_AUTHORIZATION' => 'Bearer secret',
            'HTTP_X_REQUEST_ID' => 'abc-123',
            'CONTENT_TYPE' => 'application/json',
        ];
        $_GET = ['page' => '1'];
        $_POST = ['email' => 'user@example.com', 'password' => 'secret'];
        $_COOKIE = ['laravel_session' => 'cookie-value'];
        $_FILES = [
            'avatar' => [
                'name' => 'avatar.png',
                'type' => 'image/png',
                'tmp_name' => '/tmp/php123',
                'error' => 0,
                'size' => 5120,
            ],
        ];

        $collector = new RequestCollector();
        $data = $collector->collect(new DebugState(microtime(true), [], [], [], [], []));

        self::assertSame('[redacted]', $data['headers']['authorization']);
        self::assertSame('[redacted]', $data['post']['password']);
        self::assertSame('[redacted]', $data['cookies']['laravel_session']);
        self::assertArrayNotHasKey('tmp_name', $data['files']['avatar']);
        self::assertSame('abc-123', $data['headers']['x-request-id']);
    }
}
