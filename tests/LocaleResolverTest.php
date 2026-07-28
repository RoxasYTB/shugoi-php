<?php
namespace Shugoi\Tests;

use PHPUnit\Framework\TestCase;
use Shugoi\LocaleResolver;

class LocaleResolverTest extends TestCase
{
    public function testExplicitLocaleTakesPrecedence(): void
    {
        $this->assertSame('fr', LocaleResolver::resolve('fr', 'en-US,en;q=0.9'));
        $this->assertSame('en', LocaleResolver::resolve('en', 'fr,en;q=0.9'));
    }

    public function testAcceptLanguageFrenchReturnsFr(): void
    {
        $this->assertSame('fr', LocaleResolver::resolve(null, 'fr-FR,fr;q=0.9,en;q=0.8'));
        $this->assertSame('fr', LocaleResolver::resolve(null, 'fr'));
        $this->assertSame('fr', LocaleResolver::resolve(null, 'en, fr;q=0.9'));
    }

    public function testAcceptLanguageEnglishReturnsEn(): void
    {
        $this->assertSame('en', LocaleResolver::resolve(null, 'en-US,en;q=0.9'));
        $this->assertSame('en', LocaleResolver::resolve(null, 'de-DE,de;q=0.9,en;q=0.8'));
    }

    public function testNullAcceptLanguageReturnsEn(): void
    {
        $this->assertSame('en', LocaleResolver::resolve(null, null));
    }

    public function testExplicitNullAndNullAcceptReturnsEn(): void
    {
        $this->assertSame('en', LocaleResolver::resolve(null, null));
    }

    public function testExplicitEmptyStringDoesNotMatchFrench(): void
    {
        $this->assertSame('en', LocaleResolver::resolve('', null));
    }
}
