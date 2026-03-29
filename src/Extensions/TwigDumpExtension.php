<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Extensions;

use Marwa\DebugBar\DebugBar;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TwigDumpExtension extends AbstractExtension
{
    public function __construct(private readonly DebugBar $debugBar)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('db_dump', [$this, 'dump'])];
    }

    public function dump(mixed $value, ?string $name = null): string
    {
        if ($this->debugBar->isEnabled()) {
            $this->debugBar->addDump($value, $name);
        }

        return '';
    }
}
