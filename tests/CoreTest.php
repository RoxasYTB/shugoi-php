<?php

namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\Core;
use Shugoi\Config;
use Shugoi\ApiClient;
use Shugoi\ConfigCache;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Client;

class CoreTest extends TestCase
{
    private function makeCore(array $configOverrides = [], ?MockHandler $mock = null): Core
    {
        $config = new Config(array_merge([
            'siteKey' => 'sg_sk_test_abc',
            'secret' => 'test_secret',
        ], $configOverrides));
        if (!$mock) {
            $mock = new MockHandler([
                new Response(200, [], json_encode([
                    'whitelistedMachines' => [],
                    'detectionFlags' => [],
                    'skipPaths' => [],
                ])),
            ]);
        }
        $http = new Client(['handler' => HandlerStack::create($mock)]);
        $api = new ApiClient($config, $http);
        $configCache = new ConfigCache($api);
        return new Core($config, $api, $configCache);
    }

    public function test_allowlisted_path_returns_null(): void
    {
        $core = $this->makeCore();
        $result = $core->evaluate([
            'path' => '/api/test',
            'ua' => 'curl/7.68',
            'ip' => '1.2.3.4',
        ]);
        $this->assertNull($result);
    }

    public function test_headless_ua_returns_block(): void
    {
        $core = $this->makeCore();
        $result = $core->evaluate([
            'path' => '/some-page',
            'ua' => 'curl/7.68',
            'ip' => '1.2.3.4',
        ]);
        $this->assertNotNull($result);
        $this->assertTrue($result['block']);
        $this->assertEquals(403, $result['status']);
    }

    public function test_whitelisted_bot_not_blocked(): void
    {
        $core = $this->makeCore();
        $result = $core->evaluate([
            'path' => '/',
            'ua' => 'Mozilla/5.0 Googlebot/2.1',
            'ip' => '1.2.3.4',
        ]);
        $this->assertNull($result);
    }

    public function test_normal_browser_with_missing_headers_returns_block(): void
    {
        $core = $this->makeCore();
        $result = $core->evaluate([
            'path' => '/',
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120',
            'ip' => '1.2.3.4',
        ]);
        $this->assertNotNull($result);
        $this->assertTrue($result['block']);
    }

    public function test_normal_browser_with_headers_passes(): void
    {
        $core = $this->makeCore();
        $result = $core->evaluate([
            'path' => '/',
            'ua' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120',
            'ip' => '1.2.3.4',
            'acceptLanguage' => 'en-US,en;q=0.9',
            'secFetchDest' => 'document',
            'secFetchMode' => 'navigate',
        ]);
        $this->assertNull($result);
    }

    public function test_isAllowlisted(): void
    {
        $core = $this->makeCore();
        $this->assertTrue($core->isAllowlisted('/api/test'));
        $this->assertTrue($core->isAllowlisted('/legal'));
        $this->assertFalse($core->isAllowlisted('/'));
    }

    public function test_isWhitelistedBot(): void
    {
        $core = $this->makeCore();
        $this->assertTrue($core->isWhitelistedBot('Googlebot/2.1'));
        $this->assertTrue($core->isWhitelistedBot('Bingbot'));
        $this->assertFalse($core->isWhitelistedBot('Mozilla/5.0 Chrome/120'));
    }
}
