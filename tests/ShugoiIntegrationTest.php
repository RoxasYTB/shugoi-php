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

class ShugoiIntegrationTest extends TestCase
{
    private function createFullStack(): Middleware
    {
        $config = new Config([
            'siteKey' => 'sg_sk_test_abc',
            'secret' => 'test_secret',
        ]);
        $mockHandler = new MockHandler([
            new Response(200, [], json_encode(['whitelistedMachines' => [], 'detectionFlags' => [], 'skipPaths' => []])),
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
        return new Middleware($config, $core, $api, $configCache, $guardCache, $htmlStore, $cspBuilder, $injector, $tokenSigner);
    }

    public function test_full_flow_browser_passes(): void
    {
        $middleware = $this->createFullStack();
        $request = new ServerRequest('GET', '/');
        $request = $request
            ->withHeader('User-Agent', 'Mozilla/5.0 Chrome/120')
            ->withHeader('Accept-Language', 'en-US')
            ->withHeader('Sec-Fetch-Dest', 'document')
            ->withHeader('Sec-Fetch-Mode', 'navigate');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn(new Psr7Response(200, ['Content-Type' => 'text/html'], '<html><body>Hello</body></html>'));
        $response = $middleware->process($request, $handler);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('default-src', $response->getHeaderLine('Content-Security-Policy'));
    }

    public function test_full_flow_curl_blocked(): void
    {
        $middleware = $this->createFullStack();
        $request = new ServerRequest('GET', '/');
        $request = $request->withHeader('User-Agent', 'curl/7.68');
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');
        $response = $middleware->process($request, $handler);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
