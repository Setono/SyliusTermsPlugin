<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The Terms are placed on a newsletter Form that no baseline Terms uses, so only the Terms a test creates are found
 */
final class FormTermsLookupTest extends KernelTestCase
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
    public function it_returns_only_enabled_terms_placed_on_the_form_in_the_channel(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');
        $otherWeb = self::createChannel('OTHER_WEB');

        self::createTerms('shown', ['newsletter'], [$fashionWeb]);
        self::createTerms('shown_on_several_forms_and_channels', ['checkout_complete', 'newsletter'], [$otherWeb, $fashionWeb]);
        self::createTerms('shown_optional', ['newsletter'], [$fashionWeb], required: false);
        self::createTerms('disabled', ['newsletter'], [$fashionWeb], enabled: false);
        self::createTerms('in_another_channel', ['newsletter'], [$otherWeb]);
        self::createTerms('in_no_channel', ['newsletter']);
        self::createTerms('on_another_form', ['registration'], [$fashionWeb]);
        self::createTerms('on_no_form', [], [$fashionWeb]);

        self::assertSame(
            ['shown', 'shown_on_several_forms_and_channels', 'shown_optional'],
            self::codes($this->lookup('newsletter', $fashionWeb, 'en_US')),
        );
        self::assertSame(
            ['shown_on_several_forms_and_channels', 'in_another_channel'],
            self::codes($this->lookup('newsletter', $otherWeb, 'en_US')),
        );
        self::assertSame([], $this->lookup('unknown', $fashionWeb, 'en_US'));
    }

    #[Test]
    public function it_skips_terms_with_no_version_in_force_or_none_in_the_locale(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        self::createTerms('in_en_US', ['newsletter'], [$fashionWeb], ['en_US']);
        self::createTerms('in_en_US_and_de_DE', ['newsletter'], [$fashionWeb], ['en_US', 'de_DE']);

        self::assertSame(['in_en_US', 'in_en_US_and_de_DE'], self::codes($this->lookup('newsletter', $fashionWeb, 'en_US')));
        self::assertSame(['in_en_US_and_de_DE'], self::codes($this->lookup('newsletter', $fashionWeb, 'de_DE')), 'No fallback to another locale');
        self::assertSame([], $this->lookup('newsletter', $fashionWeb, 'en_US', '2026-11-16 11:59:59'), 'Nothing before version 1');
    }

    #[Test]
    public function it_returns_the_terms_in_position_order(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        $second = self::createTerms('second', ['newsletter'], [$fashionWeb]);
        self::createTerms('third', ['newsletter'], [$fashionWeb]);
        // Created last, but put in front of the second
        self::createTerms('first', ['newsletter'], [$fashionWeb], position: $second->getPosition());
        self::getEntityManager()->clear();

        self::assertSame(['first', 'second', 'third'], self::codes($this->lookup('newsletter', self::getChannel('FASHION_WEB'), 'en_US')));
    }

    #[Test]
    public function it_picks_the_version_in_force_at_the_moment_with_the_current_placement(): void
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        // Version 1 holds en_US only, version 2 an hour later adds de_DE
        $terms = self::createTerms('versioned', ['newsletter'], [$fashionWeb], ['en_US']);
        $this->clock->sleep(3600);
        $terms->addTranslation(self::createTermsTranslation('versioned', 'de_DE'));
        self::getEntityManager()->flush();
        $this->clock->sleep(60);

        self::assertSame([1], self::versionNumbers($this->lookup('newsletter', $fashionWeb, 'en_US', '2026-11-16 12:59:59')));
        self::assertSame([2], self::versionNumbers($this->lookup('newsletter', $fashionWeb, 'en_US', '2026-11-16 13:00:00')));
        self::assertSame([2], self::versionNumbers($this->lookup('newsletter', $fashionWeb, 'en_US')), 'The moment defaults to now');
        self::assertSame([], $this->lookup('newsletter', $fashionWeb, 'de_DE', '2026-11-16 12:59:59'));
        self::assertSame([2], self::versionNumbers($this->lookup('newsletter', $fashionWeb, 'de_DE', '2026-11-16 13:00:00')));

        // Placement isn't versioned: a Terms taken off the Form is gone from the lookup at every moment
        $terms->setForms([]);
        self::getEntityManager()->flush();

        self::assertSame([], $this->lookup('newsletter', $fashionWeb, 'en_US', '2026-11-16 12:59:59'));
    }

    /**
     * @return list<TermsVersionTranslationInterface>
     */
    private function lookup(string $form, ChannelInterface $channel, string $localeCode, ?string $at = null): array
    {
        /** @var FormTermsLookupInterface $lookup */
        $lookup = self::getContainer()->get(FormTermsLookupInterface::class);

        return $lookup->lookup($form, $channel, $localeCode, null === $at ? null : new \DateTimeImmutable($at));
    }

    /**
     * @param list<TermsVersionTranslationInterface> $inForce
     *
     * @return list<string|null>
     */
    private static function codes(array $inForce): array
    {
        return array_map(
            static fn (TermsVersionTranslationInterface $translation): ?string => $translation->getVersion()->getTerms()?->getCode(),
            $inForce,
        );
    }

    /**
     * @param list<TermsVersionTranslationInterface> $inForce
     *
     * @return list<int|null>
     */
    private static function versionNumbers(array $inForce): array
    {
        return array_map(
            static fn (TermsVersionTranslationInterface $translation): ?int => $translation->getVersion()->getNumber(),
            $inForce,
        );
    }
}
