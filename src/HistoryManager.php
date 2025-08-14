<?php

declare(strict_types=1);

namespace Marwa\DebugBar;

use Marwa\DebugBar\Config\HistoryConfig;
use Marwa\DebugBar\Contracts\Storage;

final class HistoryManager
{
    public function __construct(
        private readonly Storage $storage,
        private readonly HistoryConfig $config
    ) {}

    public function isEnabled(): bool
    {
        return $this->config->enabled;
    }

    public function persist(array $payload): ?string
    {
        if (!$this->isEnabled()) return null;
        $id = $this->storage->saveSnapshot($payload);
        $this->storage->enforceRetention($this->config->maxSnapshots);
        return $id;
    }

    public function recentMeta(): array
    {
        if (!$this->isEnabled()) return [];
        return $this->storage->listSnapshots($this->config->uiListLimit);
    }

    public function load(string $id): ?array
    {
        if (!$this->isEnabled()) return null;
        return $this->storage->loadSnapshot($id);
    }
}
