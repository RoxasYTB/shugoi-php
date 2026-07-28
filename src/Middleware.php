<?php

namespace Shugoi;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface as PsrMiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Response;

class Middleware implements PsrMiddlewareInterface
{
    public ?string $publicRenderUrl = null;

    public function __construct(
        private readonly Config $config,
        private readonly Core $core,
        private readonly ApiClient $api,
        private readonly ConfigCache $configCache,
        private readonly GuardCache $guardCache,
        private readonly HtmlStore $htmlStore,
        private readonly CspBuilder $cspBuilder,
        private readonly GuardInjector $injector,
        private readonly TokenSigner $tokenSigner,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (str_ends_with($path, '/__shugoi/render')) {
            return $this->handleRender($request);
        }

        $csp = $this->cspBuilder->build();

        $ua = $request->getHeaderLine('User-Agent');
        $ip = $request->getHeaderLine('X-Forwarded-For') ?: $request->getServerParams()['REMOTE_ADDR'] ?? '';
        $acceptLanguage = $request->getHeaderLine('Accept-Language') ?: null;
        $secFetchDest = $request->getHeaderLine('Sec-Fetch-Dest') ?: null;
        $secFetchMode = $request->getHeaderLine('Sec-Fetch-Mode') ?: null;
        $host = $request->getHeaderLine('Host') ?: null;

        $block = $this->core->evaluate([
            'path' => $path,
            'ua' => $ua,
            'ip' => $ip,
            'host' => $host,
            'acceptLanguage' => $acceptLanguage,
            'secFetchDest' => $secFetchDest,
            'secFetchMode' => $secFetchMode,
        ]);

        if ($block !== null) {
            return new Response(
                $block['status'],
                [
                    'Content-Type' => $block['contentType'],
                    'Content-Security-Policy' => $csp,
                ],
                $block['body']
            );
        }

        $response = $handler->handle($request);

        $body = (string)$response->getBody();
        if ($this->config->autoInject && $this->config->splitRender) {
            $contentType = $response->getHeaderLine('Content-Type');
            if (str_contains($contentType, 'text/html') || $contentType === '') {
                if (!empty($body) && str_contains($body, '<html')) {
                    try {
                        $renderUrl = $this->publicRenderUrl ?: $host ?? 'localhost' . '/__shugoi/render';
                        $body = $this->injector->inject($body, $path, $ua, $ip, $host ?? '', $acceptLanguage, $renderUrl);
                    } catch (\Throwable $e) {
                        if ($this->config->debug) {
                            error_log("Shugoi inject failed: " . $e->getMessage());
                        }
                    }
                }
            }
        }

        $response = $response->withHeader('Content-Security-Policy', $csp);
        $response->getBody()->rewind();
        $response->getBody()->write($body);

        return $response;
    }

    private function handleRender(ServerRequestInterface $request): ResponseInterface
    {
        $params = $request->getQueryParams();
        $token = $params['token'] ?? '';

        // If content replacement check is disabled, serve any fresh token's HTML
        $cfg = $this->configCache->get();
        $ecr = $cfg['enableContentReplacementCheck'] ?? $cfg['detectionFlags']['enableContentReplacementCheck'] ?? true;
        if (!$ecr) {
            // Try disk store for any available token
            $fresh = $this->htmlStore->hasFreshToken($this->config->siteKey, true);
            if ($fresh !== null) {
                return new Response(200, ['Content-Type' => 'application/json'], json_encode(['html' => $fresh['html']]));
            }
            // Also check the shared disk directory directly
            $diskPath = '/tmp/shugoi-render-shared';
            if (is_dir($diskPath)) {
                $files = glob($diskPath . '/*');
                if (!empty($files)) {
                    $html = file_get_contents($files[0]);
                    if ($html !== false && $html !== '') {
                        return new Response(200, ['Content-Type' => 'application/json'], json_encode(['html' => $html]));
                    }
                }
            }
            return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'not_found']));
        }

        if (empty($token)) {
            return new Response(400, ['Content-Type' => 'application/json'], json_encode(['error' => 'missing_token']));
        }

        $entry = $this->htmlStore->retrieve($token);
        if ($entry !== null) {
            return new Response(200, ['Content-Type' => 'application/json'], json_encode(['html' => $entry['html']]));
        }

        return new Response(404, ['Content-Type' => 'application/json'], json_encode(['error' => 'not_found']));
    }
}
