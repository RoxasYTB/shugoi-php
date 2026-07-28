<?php

namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\Middleware;
use Shugoi\Config;
use Shugoi\Core;
use Shugoi\ApiClient;
use Shugoi\ConfigCache;
use Shugoi\GuardCache;
use Shugoi\GuardInjector;
use Shugoi\CspBuilder;
use Shugoi\HtmlStore;
use Shugoi\TokenSigner;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Response as Psr7Response;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

class MiddlewareTest extends TestCase
{
    private function createMiddleware(array $configOverrides = []): Middleware
    {
        $config = new Config(array_merge([
            'siteKey' => 'sg_sk_test_abc',
            'secret' => 'test_secret',
        ], $configOverrides));

        $mockHandler = new MockHandler([
            new Response(200, [], json_encode([
                'whitelistedMachines' => [],
                'detectionFlags' => [],
                'skipPaths' => [],
            ])),
        ]);
        $http = new Client(['handler' => HandlerStack::create($mockHandler)]);
        $api = new ApiClient($config, $http);
        $configCache = new ConfigCache($api);
        $guardCache = new GuardCache($api);
        $htmlStore = new HtmlStore();
        $tokenSigner = new TokenSigner($config);
        $cspBuilder = new CspBuilder($config);
        $core = new Core($config, $api, $configCache);
        $injector = new GuardInjector($config, $tokenSigner, $htmlStore, $guardCache, $configCache);

        return new Middleware(
            config: $config,
            core: $core,
            api: $api,
            configCache: $configCache,
            guardCache: $guardCache,
            htmlStore: $htmlStore,
            cspBuilder: $cspBuilder,
            injector: $injector,
            tokenSigner: $tokenSigner,
        );
    }

    public function test_render_endpoint_returns_json(): void
    {
        $middleware = $this->createMiddleware();
        $request = new ServerRequest('GET', '/__shugoi/render?token=test123');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $response = $middleware->process($request, $handler);
        $body = json_decode((string)$response->getBody(), true);
        $this->assertArrayHasKey('error', $body);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_allowlisted_path_passes_through(): void
    {
        $middleware = $this->createMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())->method('handle')->willReturn(new Psr7Response(200, [], '<html>OK</html>'));
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_headless_ua_gets_blocked(): void
    {
        $middleware = $this->createMiddleware();
        $request = new ServerRequest('GET', '/');
        $request = $request->withHeader('User-Agent', 'curl/7.68');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $response = $middleware->process($request, $handler);
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertStringContainsString('Shugoi', (string)$response->getBody());
    }

    public function test_csp_header_is_set(): void
    {
        $middleware = $this->createMiddleware();
        $request = new ServerRequest('GET', '/api/test');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Psr7Response(200, [], ''));
        $response = $middleware->process($request, $handler);
        $this->assertStringContainsString("default-src 'self'", $response->getHeaderLine('Content-Security-Policy'));
    }
}
