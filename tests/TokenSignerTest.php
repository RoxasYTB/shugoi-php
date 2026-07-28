<?php
namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\TokenSigner;
use Shugoi\Config;

class TokenSignerTest extends TestCase
{
    private Config $config;
    private TokenSigner $signer;

    protected function setUp(): void
    {
        $this->config = new Config([
            'siteKey' => 'sg_sk_test_abc',
            'secret' => 'my_test_secret_key_for_hmac',
        ]);
        $this->signer = new TokenSigner($this->config);
    }

    public function test_sign_returns_4_part_token(): void
    {
        $ts = 1722000000000;
        $token = $this->signer->sign($ts);
        $parts = explode(':', $token);
        $this->assertCount(4, $parts);
        $this->assertEquals('sg_sk_test_abc', $parts[0]);
        $this->assertEquals((string)$ts, $parts[1]);
        $this->assertEquals(16, strlen($parts[2]));
        $this->assertEquals(64, strlen($parts[3]));
    }

    public function test_verify_valid_token_returns_parts(): void
    {
        $token = $this->signer->sign(time() * 1000);
        $result = $this->signer->verify($token);
        $this->assertNotNull($result);
        $this->assertEquals('sg_sk_test_abc', $result['siteKey']);
    }

    public function test_verify_tampered_token_returns_null(): void
    {
        $token = $this->signer->sign(time() * 1000);
        $parts = explode(':', $token);
        $parts[3] = str_repeat('0', 64);
        $this->assertNull($this->signer->verify(implode(':', $parts)));
    }

    public function test_verify_wrong_secret_returns_null(): void
    {
        $token = $this->signer->sign(time() * 1000);
        $this->assertNull($this->signer->verify($token, 'wrong_secret'));
    }
}
