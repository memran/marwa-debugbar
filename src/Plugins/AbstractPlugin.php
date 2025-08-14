<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Plugins;

use Marwa\DebugBar\Contracts\Plugin;

abstract class AbstractPlugin implements Plugin
{
    private bool $enabled = true;
    public function boot(): void {}

    public function name(): string
    {
        return '';
    }
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    public function extendPayload(array $payload): array
    {
        return [];
    }
    public function tabs(): array
    {
        return [];
    }
}
