<?php
namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\HtmlStore;

class HtmlStoreTest extends TestCase
{
    public function test_store_and_retrieve(): void
    {
        $store = new HtmlStore();
        $store->store('token1', '<html>hello</html>');
        $result = $store->retrieve('token1');
        $this->assertNotNull($result);
        $this->assertEquals('<html>hello</html>', $result['html']);
    }

    public function test_return_null_for_unknown_token(): void
    {
        $store = new HtmlStore();
        $this->assertNull($store->retrieve('nonexistent'));
    }

    public function test_expired_token_returns_null(): void
    {
        $store = new HtmlStore();
        $store->store('token_exp', '<html>expired</html>', 1);
        usleep(2000);
        $this->assertNull($store->retrieve('token_exp'));
    }

    public function test_single_read_token_expires_after_first_read(): void
    {
        $store = new HtmlStore();
        $store->store('token_once', '<html>once</html>', 5000, 1);
        $first = $store->retrieve('token_once');
        $this->assertNotNull($first);
        $second = $store->retrieve('token_once');
        $this->assertNull($second);
    }

    public function test_respects_max_tokens(): void
    {
        $store = new HtmlStore();
        for ($i = 0; $i < 5010; $i++) {
            $store->store("token_{$i}", "<html>{$i}</html>", 5000);
        }
        $this->assertNull($store->retrieve('token_0'));
        $this->assertNotNull($store->retrieve('token_5009'));
    }

    public function test_remove(): void
    {
        $store = new HtmlStore();
        $store->store('token_rm', '<html>remove</html>');
        $store->remove('token_rm');
        $this->assertNull($store->retrieve('token_rm'));
    }
}
