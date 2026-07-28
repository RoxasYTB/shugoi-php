<?php
namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\Locales;

class LocalesTest extends TestCase
{
    public function testFrenchRateLimitTitle(): void
    {
        $this->assertSame('Trop de requêtes', Locales::get('fr', 'rateLimitTitle'));
    }

    public function testEnglishRateLimitTitle(): void
    {
        $this->assertSame('Too Many Requests', Locales::get('en', 'rateLimitTitle'));
    }

    public function testFrenchRateLimitBodyWithArgs(): void
    {
        $result = Locales::get('fr', 'rateLimitBody', '2 minutes');
        $this->assertStringContainsString('2 minutes', $result);
        $this->assertStringContainsString('trop de requêtes', $result);
    }

    public function testEnglishRateLimitBodyWithArgs(): void
    {
        $result = Locales::get('en', 'rateLimitBody', '2 minutes');
        $this->assertStringContainsString('2 minutes', $result);
        $this->assertStringContainsString('too many requests', strtolower($result));
    }

    public function testFrenchRetryInSeconds(): void
    {
        $result = Locales::get('fr', 'retryInSeconds', 30);
        $this->assertSame('Il reste 30s avant de pouvoir réessayer.', $result);
    }

    public function testEnglishRetryInSeconds(): void
    {
        $result = Locales::get('en', 'retryInSeconds', 30);
        $this->assertSame('Retry in 30s.', $result);
    }

    public function testBlockedTitleFrench(): void
    {
        $this->assertSame('Accès bloqué', Locales::get('fr', 'blockedTitle'));
    }

    public function testBlockedTitleEnglish(): void
    {
        $this->assertSame('Access Blocked', Locales::get('en', 'blockedTitle'));
    }

    public function testBlockedBadgeFrench(): void
    {
        $this->assertSame('Blocage', Locales::get('fr', 'blockedBadge'));
    }

    public function testBlockedBadgeEnglish(): void
    {
        $this->assertSame('Blocked', Locales::get('en', 'blockedBadge'));
    }

    public function testTamperTitleFrench(): void
    {
        $this->assertSame('Remplacement de contenu client détecté', Locales::get('fr', 'tamperTitle'));
    }

    public function testTamperBodyEnglish(): void
    {
        $body = Locales::get('en', 'tamperBody');
        $this->assertStringContainsString('DevTools', $body);
    }

    public function testDevtoolsBodyFrench(): void
    {
        $body = Locales::get('fr', 'devtoolsBody');
        $this->assertStringContainsString('DevTools', $body);
    }

    public function testUnknownKeyReturnsKey(): void
    {
        $this->assertSame('nonexistentKey', Locales::get('en', 'nonexistentKey'));
        $this->assertSame('nonexistentKey', Locales::get('fr', 'nonexistentKey'));
    }

    public function testUnknownLocaleFallsBackToEnglish(): void
    {
        $this->assertSame('Access Blocked', Locales::get('de', 'blockedTitle'));
    }

    public function testRateLimitBadgeIsIdenticalBothLocales(): void
    {
        $this->assertSame(Locales::get('fr', 'rateLimitBadge'), Locales::get('en', 'rateLimitBadge'));
    }

    public function testMultipleArgsInSprintf(): void
    {
        $result = Locales::get('en', 'rateLimitBody', '5 minutes');
        $this->assertSame(
            'You have made too many requests in a short time. 5 minutes remaining before you can try again.',
            $result
        );
    }
}
