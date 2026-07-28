<?php
namespace Shugoi\Tests\Laravel;

use Orchestra\Testbench\TestCase;
use Shugoi\Laravel\ShugoiServiceProvider;
use Shugoi\Config;
use Shugoi\Core;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [ShugoiServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('shugoi.siteKey', 'sg_sk_test_laravel');
        $app['config']->set('shugoi.secret', 'test_secret');
    }

    public function test_config_is_bound()
    {
        $config = $this->app->make(Config::class);
        $this->assertInstanceOf(Config::class, $config);
        $this->assertEquals('sg_sk_test_laravel', $config->siteKey);
    }

    public function test_core_is_bound()
    {
        $core = $this->app->make(Core::class);
        $this->assertInstanceOf(Core::class, $core);
    }
}
