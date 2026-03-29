<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;
use Marwa\DebugBar\Core\DebugState;

final class SessionCollector implements Collector
{
    use HtmlKit;

    private const SENSITIVE_KEYS = ['password', 'passwd', 'token', 'secret', 'csrf', 'key'];

    public static function key(): string
    {
        return 'session';
    }

    public static function label(): string
    {
        return 'Session';
    }

    public static function icon(): string
    {
        return '🔑';
    }

    public static function order(): int
    {
        return 300;
    }

    public function collect(DebugState $state): array
    {
        unset($state);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return ['active' => false, 'meta' => [], 'data' => []];
        }

        $cookie = session_get_cookie_params();
        $sessionId = session_id();

        return [
            'active' => true,
            'meta' => [
                'id' => $this->maskValue($sessionId === false ? '' : $sessionId),
                'name' => session_name(),
                'cookie' => [
                    'lifetime' => $cookie['lifetime'],
                    'path' => $cookie['path'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => $cookie['samesite'],
                ],
            ],
            'data' => $this->redactArray($_SESSION),
        ];
    }

    public function renderHtml(array $data): string
    {
        if (!($data['active'] ?? false)) {
            return '<div style="color:#9ca3af">Session is not active.</div>';
        }

        $meta = '<div class="mw-grid-3" style="margin-bottom:10px">'
            . $this->stat('Session ID', '<span class="mw-mono">' . $this->e((string) ($data['meta']['id'] ?? '')) . '</span>')
            . $this->stat('Session Name', '<span class="mw-mono">' . $this->e((string) ($data['meta']['name'] ?? '')) . '</span>')
            . $this->stat('Attrs Count', $this->e((string) count((array) ($data['data'] ?? []))))
            . '</div>';

        $body = '<div class="mw-grid-2">'
            . $this->card('Attributes', $this->preJson($data['data'] ?? []))
            . $this->card('Cookie Meta', $this->preJson($data['meta']['cookie'] ?? []))
            . '</div>';

        return '<div style="max-height:45vh;overflow:auto">' . $meta . $body . '</div>';
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

    private function maskValue(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (strlen($value) <= 6) {
            return str_repeat('*', strlen($value));
        }

        return str_repeat('*', strlen($value) - 4) . substr($value, -4);
    }
}
