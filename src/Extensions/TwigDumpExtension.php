<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Extensions;

use Marwa\DebugBar\DebugBar;
use Symfony\Component\VarDumper\VarDumper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TwigDumpExtension extends AbstractExtension
{
    public function __construct(private readonly DebugBar $debugBar) {}

    public function getFunctions(): array
    {
        return [new TwigFunction('db_dump', [$this, 'dump'], ['is_safe' => ['html']])];
    }

    public function dump(mixed $value, ?string $name = null): string
    {
        if (!$this->debugBar->isEnabled()) return '';
        if (class_exists(VarDumper::class)) {
            VarDumper::dump($value, $name);
            return '';
        }
        $safe = htmlspecialchars(print_r($value, true), ENT_QUOTES, 'UTF-8');
        $this->debugBar->addDump('<pre>' . $safe . '</pre>', $name, null, null);
        return '';
    }
}
