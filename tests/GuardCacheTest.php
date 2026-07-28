<?php
namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\GuardCache;
use Shugoi\ApiClient;
use Shugoi\Config;

class GuardCacheTest extends TestCase
{
    public function test_get_fetches_and_caches(): void
    {
        $config = new Config([
            'siteKey' => 'sg_sk_test_abc',
            'secret' => 'test_secret',
            'internalUrl' => 'http://127.0.0.1:3098/v1',
        ]);

        $api = $this->createMock(ApiClient::class);
        $api->method('getConfig')->willReturn($config);

        $api->expects($this->once())
            ->method('fetchGuardDetect')
            ->willReturn('console.log("detect");');

        $api->expects($this->once())
            ->method('fetchGuard')
            ->willReturn('console.log("guard");');

        $cache = new GuardCache($api);

        $result1 = $cache->get();
        $this->assertEquals('console.log("detect");', $result1['detect']);
        $this->assertEquals('console.log("guard");', $result1['guard']);

        $result2 = $cache->get();
        $this->assertSame($result1, $result2);
    }
}
