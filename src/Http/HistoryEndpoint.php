<?php
declare(strict_types=1);

namespace Marwa\DebugBar\Http;

use Marwa\DebugBar\DebugBar;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;

/**
 * Simple JSON endpoint to read snapshots from configured history storage.
 * SECURITY: only enable in dev. Consider IP allowlist or signed cookie for shared envs.
 */
final class HistoryEndpoint implements RequestHandlerInterface
{
    public function __construct(private readonly DebugBar $debugBar) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $hist = $this->debugBar->history();
        if (!$hist || !$hist->isEnabled()) {
            return new Response(404, ['Content-Type'=>'application/json'], json_encode(['error'=>'history_disabled']));
        }

        $query = $request->getQueryParams();
        $id = isset($query['id']) ? (string)$query['id'] : '';
        if ($id === '') {
            return new Response(400, ['Content-Type'=>'application/json'], json_encode(['error'=>'missing_id']));
        }

        $snap = $hist->load($id);
        if (!$snap) {
            return new Response(404, ['Content-Type'=>'application/json'], json_encode(['error'=>'not_found']));
        }

        return new Response(200, ['Content-Type'=>'application/json'], json_encode($snap, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
}
