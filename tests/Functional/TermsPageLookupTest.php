<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Each Terms the tests create has the slug of its code, with dashes for underscores
 */
final class TermsPageLookupTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTermsTrait;

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();

        // MySQL's DATETIME has one-second precision, so the mock clock only ever moves in whole seconds
        $clock = self::mockTime('2026-11-16 12:00:00');
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function it_finds_an_enabled_terms_in_the_channel_and_locale_by_code_and_by_slug(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');
        self::createTerms('shown', channels: [self::createChannel('OTHER_WEB'), $fashionWeb], localeCodes: ['en_US', 'de_DE']);

        foreach (['en_US', 'de_DE'] as $localeCode) {
            $byCode = self::getLookup()->lookupByCode('shown', $fashionWeb, $localeCode);
            self::assertNotNull($byCode);
            self::assertSame($localeCode, $byCode->getLocale());
            self::assertSame(sprintf('shown (%s)', $localeCode), $byCode->getName());
            self::assertSame(1, $byCode->getVersion()->getNumber());
            self::assertSame('shown', $byCode->getVersion()->getTerms()?->getCode());

            self::assertSame($byCode, self::getLookup()->lookupBySlug('shown', $fashionWeb, $localeCode));
        }
    }

    #[Test]
    public function it_finds_nothing_where_the_terms_page_does_not_exist(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        self::createTerms('disabled', channels: [$fashionWeb], enabled: false);
        self::createTerms('in_another_channel', channels: [self::createChannel('OTHER_WEB')]);
        self::createTerms('in_no_channel');
        self::createTerms('in_en_US', channels: [$fashionWeb], localeCodes: ['en_US']);

        foreach ([['disabled', 'en_US'], ['in_another_channel', 'en_US'], ['in_no_channel', 'en_US'], ['in_en_US', 'de_DE'], ['unknown', 'en_US']] as [$code, $localeCode]) {
            self::assertNull(self::getLookup()->lookupByCode($code, $fashionWeb, $localeCode), $code . ' by code');
            self::assertNull(self::getLookup()->lookupBySlug(str_replace('_', '-', $code), $fashionWeb, $localeCode), $code . ' by slug');
        }
    }

    #[Test]
    public function it_finds_the_version_in_force_now_and_nothing_before_the_first_version(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        $terms = self::createTerms('versioned', channels: [$fashionWeb]);
        $this->clock->sleep(3600);
        $terms->getTranslation('en_US')->setName('Versioned, version 2');
        self::getEntityManager()->flush();

        self::assertSame('Versioned, version 2', self::getLookup()->lookupByCode('versioned', $fashionWeb, 'en_US')?->getName());
        self::assertSame(2, self::getLookup()->lookupBySlug('versioned', $fashionWeb, 'en_US')?->getVersion()->getNumber());

        // Version 2 isn't in force yet, so version 1 is shown, with its own name
        self::mockTime('2026-11-16 12:59:59');
        self::assertSame('versioned (en_US)', self::getLookup()->lookupByCode('versioned', $fashionWeb, 'en_US')?->getName());

        self::mockTime('2026-11-16 11:59:59');
        self::assertNull(self::getLookup()->lookupByCode('versioned', $fashionWeb, 'en_US'));
        self::assertNull(self::getLookup()->lookupBySlug('versioned', $fashionWeb, 'en_US'));
    }

    private static function getLookup(): TermsPageLookupInterface
    {
        /** @var TermsPageLookupInterface $lookup */
        $lookup = self::getContainer()->get(TermsPageLookupInterface::class);

        return $lookup;
    }
}
