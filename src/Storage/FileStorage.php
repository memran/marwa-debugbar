<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Storage;

use Marwa\DebugBar\Contracts\Storage;

final class FileStorage implements Storage
{
    private string $dir;
    private string $snapDir;
    private string $indexFile;

    public function __construct(string $directory)
    {
        $this->dir = rtrim($directory, DIRECTORY_SEPARATOR);
        $this->snapDir = $this->dir . DIRECTORY_SEPARATOR . 'snapshots';
        $this->indexFile = $this->dir . DIRECTORY_SEPARATOR . 'index.jsonl';
        if (!is_dir($this->snapDir) && !@mkdir($this->snapDir, 0775, true) && !is_dir($this->snapDir)) {
            throw new \RuntimeException("Cannot create snapshot directory: {$this->snapDir}");
        }
        if (!file_exists($this->indexFile)) {
            @file_put_contents($this->indexFile, '');
        }
    }

    public function saveSnapshot(array $payload): string
    {
        $ts = $payload['_meta']['generated_at'] ?? gmdate('c');
        $id = str_replace([':', '.', '+'], ['-', '-', '-'], $ts) . '_' . substr(bin2hex(random_bytes(4)), 0, 8);

        $path = $this->snapPath($id);
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) throw new \RuntimeException('Failed to encode snapshot payload.');

        $tmp = $path . '.tmp';
        if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $path)) {
            @unlink($tmp);
            throw new \RuntimeException("Failed to write snapshot: {$path}");
        }

        $size = filesize($path) ?: null;
        $elapsed = $payload['_meta']['elapsed_ms'] ?? null;
        $meta = json_encode(['id' => $id, 'ts' => $ts, 'elapsed_ms' => $elapsed, 'size' => $size]) . PHP_EOL;
        @file_put_contents($this->indexFile, $meta, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public function loadSnapshot(string $id): ?array
    {
        $path = $this->snapPath($id);
        if (!is_file($path)) return null;
        $json = file_get_contents($path);
        if ($json === false) return null;
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    public function listSnapshots(int $limit = 20): array
    {
        if (!is_file($this->indexFile)) return [];
        $lines = @file($this->indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_reverse($lines);
        $out = [];
        foreach ($lines as $ln) {
            $meta = json_decode($ln, true);
            if (!is_array($meta)) continue;
            $out[] = $meta;
            if (count($out) >= $limit) break;
        }
        return $out;
    }

    public function enforceRetention(int $max): void
    {
        if ($max <= 0 || !is_file($this->indexFile)) return;
        $lines = @file($this->indexFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $count = count($lines);
        if ($count <= $max) return;

        $toDelete = array_slice($lines, 0, $count - $max);
        foreach ($toDelete as $ln) {
            $meta = json_decode($ln, true);
            if (!is_array($meta) || empty($meta['id'])) continue;
            @unlink($this->snapPath($meta['id']));
        }
        $keep = array_slice($lines, $count - $max);
        @file_put_contents($this->indexFile, implode(PHP_EOL, $keep) . PHP_EOL);
    }

    private function snapPath(string $id): string
    {
        return $this->snapDir . DIRECTORY_SEPARATOR . $id . '.json';
    }
}
