<?php

declare(strict_types=1);

namespace Marwa\DebugBar\Http;

use Marwa\DebugBar\DebugBar;
use Marwa\DebugBar\Renderer;
use Marwa\DebugBar\Collectors\RequestMetricsCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

final class DebugBarMiddleware implements MiddlewareInterface
{
    public function __construct(private DebugBar $debugBar, private ?RequestMetricsCollector $metrics = null) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        if (!$this->debugBar->isEnabled()) return $response;

        // basic metrics (route can be set by your router elsewhere)
        if ($this->metrics) {
            $this->metrics->setStatus((int)$response->getStatusCode());
            $size = $response->getBody()->getSize();
            $this->metrics->setResponseBytes($size !== null ? (int)$size : null);
            $this->metrics->finish();
        }

        $ctype = $response->getHeaderLine('Content-Type');
        if (stripos($ctype, 'text/html') === false) return $response;

        $body = (string)$response->getBody();
        $html = (new Renderer($this->debugBar))->render();
        if ($html === '') return $response;

        $pos = strripos($body, '</body>');
        $newBody = $pos !== false ? substr($body, 0, $pos) . $html . substr($body, $pos) : $body . $html;

        $stream = \GuzzleHttp\Psr7\Utils::streamFor($newBody);
        return $response->withBody($stream);
    }
}
