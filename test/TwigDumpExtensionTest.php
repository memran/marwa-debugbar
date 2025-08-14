<?php

declare(strict_types=1);

namespace Tests;

use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Twig\DebugBarDumpExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class TwigDumpExtensionTest extends TestCase
{
    public function testTwigDbDumpCaptures(): void
    {
        $bar = new DebugBar(true);
        $twig = new Environment(new ArrayLoader(['t' => "{{ db_dump({'a':1}, 'A') }}"]));
        $twig->addExtension(new DebugBarDumpExtension($bar));
        $twig->render('t');
        $payload = $bar->payload();
        $this->assertArrayHasKey('dumps', $payload);
        $this->assertNotEmpty($payload['dumps']);
    }
}
