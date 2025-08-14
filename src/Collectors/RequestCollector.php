<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Collectors;

use Marwa\DebugBar\Contracts\Collector;

final class RequestCollector implements Collector
{
    public function name(): string
    {
        return 'request';
    }
    public function collect(): array
    {
        $server = $_SERVER ?? [];
        return [
            'method'   => $server['REQUEST_METHOD'] ?? 'CLI',
            'uri'      => ($server['REQUEST_URI'] ?? '') ?: ($server['argv'][0] ?? ''),
            'ip'       => $server['REMOTE_ADDR'] ?? null,
            'ua'       => $server['HTTP_USER_AGENT'] ?? null,
            'headers'  => $this->headers(),
            'get'      => $_GET ?? [],
            'post'     => $_POST ?? [],
            'cookies'  => $_COOKIE ?? [],
            'files'    => array_map(fn($f) => ['name' => $f['name'] ?? null, 'size' => $f['size'] ?? null], $_FILES ?? []),
        ];
    }
    private function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $h = strtolower(str_replace('_', '-', substr($k, 5)));
                $headers[$h] = $v;
            }
        }
        return $headers;
    }
}
