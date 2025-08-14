<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Contracts;

interface Storage
{
    public function saveSnapshot(array $payload): string;
    public function loadSnapshot(string $id): ?array;
    public function listSnapshots(int $limit = 20): array;
    public function enforceRetention(int $max): void;
}
