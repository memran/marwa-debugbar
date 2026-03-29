<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class RequestCollector implements Collector
{
    use HtmlKit;

    private const SENSITIVE_KEYS = ['password', 'passwd', 'token', 'secret', 'authorization', 'cookie', 'csrf', 'key', 'session'];

    public static function key(): string
    {
        return 'request';
    }

    public static function label(): string
    {
        return 'Request';
    }

    public static function icon(): string
    {
        return '🌐';
    }

    public static function order(): int
    {
        return 170;
    }

    public function collect(DebugState $state): array
    {
        unset($state);

        $server = $_SERVER;

        return [
            'method' => $server['REQUEST_METHOD'] ?? 'CLI',
            'uri' => $server['REQUEST_URI'] ?? ($server['argv'][0] ?? ''),
            'ip' => $server['REMOTE_ADDR'] ?? null,
            'ua' => $server['HTTP_USER_AGENT'] ?? null,
            'headers' => $this->headers($server),
            'get' => $this->redactArray($_GET),
            'post' => $this->redactArray($_POST),
            'cookies' => $this->redactArray($_COOKIE),
            'files' => $this->normalizeFiles($_FILES),
            'server' => $this->serverDetails($server),
        ];
    }

    public function renderHtml(array $data): string
    {
        $rows = [
            ['Method', $this->e((string) ($data['method'] ?? ''))],
            ['URI', '<span class="mw-mono">' . $this->e((string) ($data['uri'] ?? '')) . '</span>'],
            ['IP', $this->e((string) ($data['ip'] ?? ''))],
            ['User Agent', '<span class="mw-mono">' . $this->e((string) ($data['ua'] ?? '')) . '</span>'],
            ['Headers', $this->preJson($data['headers'] ?? [])],
            ['GET', $this->preJson($data['get'] ?? [])],
            ['POST', $this->preJson($data['post'] ?? [])],
            ['Cookies', $this->preJson($data['cookies'] ?? [])],
            ['Files', $this->preJson($data['files'] ?? [])],
            ['Server', $this->preJson($data['server'] ?? [])],
        ];

        $body = '';
        foreach ($rows as [$label, $value]) {
            $body .= '<tr><th style="width:120px;color:#9ca3af;text-align:left;padding:8px;border-bottom:1px solid var(--mw-border)">' . $this->e($label) . '</th><td style="padding:8px;border-bottom:1px solid var(--mw-border)">' . $value . '</td></tr>';
        }

        return '<div style="max-height:45vh;overflow:auto"><table class="mw-table" style="border:0"><tbody>' . $body . '</tbody></table></div>';
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,mixed>
     */
    private function headers(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $header = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$header] = $this->isSensitiveKey($header) ? '[redacted]' : $value;
        }

        return $headers;
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,mixed>
     */
    private function serverDetails(array $server): array
    {
        $details = [];
        foreach (
            [
            'REQUEST_METHOD',
            'REQUEST_URI',
            'QUERY_STRING',
            'CONTENT_TYPE',
            'CONTENT_LENGTH',
            'SERVER_NAME',
            'SERVER_PORT',
            'HTTPS',
            'REQUEST_TIME_FLOAT',
            ] as $key
        ) {
            if (array_key_exists($key, $server)) {
                $details[$key] = $server[$key];
            }
        }

        return $details;
    }

    /**
     * @param array<string,mixed> $files
     * @return array<string,mixed>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $file) {
            if (!is_array($file)) {
                $normalized[$field] = '[invalid file payload]';
                continue;
            }

            $normalized[$field] = [
                'name' => $file['name'] ?? null,
                'type' => $file['type'] ?? null,
                'size' => $file['size'] ?? null,
                'error' => $file['error'] ?? null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    private function redactArray(array $values): array
    {
        $sanitized = [];
        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey((string) $key)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->redactArray($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::SENSITIVE_KEYS as $needle) {
            if (str_contains($normalized, $needle)) {
                return true;
            }
        }

        return false;
    }
}
