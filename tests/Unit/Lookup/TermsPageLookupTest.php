<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Lookup;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookup;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\Clock\MockClock;

/**
 * The repository queries, which leave out disabled Terms, Terms in other channels and untranslated Terms, are covered by
 * Tests\Functional\TermsPageLookupTest
 */
final class TermsPageLookupTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<TermsRepositoryInterface> */
    private ObjectProphecy $termsRepository;

    /** @var ObjectProphecy<TermsVersionRepositoryInterface> */
    private ObjectProphecy $termsVersionRepository;

    private ChannelInterface $channel;

    private TermsPageLookup $lookup;

    protected function setUp(): void
    {
        $this->termsRepository = $this->prophesize(TermsRepositoryInterface::class);
        $this->termsVersionRepository = $this->prophesize(TermsVersionRepositoryInterface::class);
        $this->channel = $this->prophesize(ChannelInterface::class)->reveal();

        $this->lookup = new TermsPageLookup(
            $this->termsRepository->reveal(),
            $this->termsVersionRepository->reveal(),
            new MockClock('2026-11-17 12:00:00'),
        );
    }

    #[Test]
    public function it_returns_the_translation_of_the_version_in_force_now_by_slug_and_by_code(): void
    {
        $terms = $this->prophesize(TermsInterface::class)->reveal();
        $translation = $this->prophesize(TermsVersionTranslationInterface::class)->reveal();

        $this->termsRepository->findOneByChannelAndLocaleAndSlug($this->channel, 'da_DK', 'vilkaar')->willReturn($terms);
        $this->termsRepository->findOneByChannelAndLocaleAndCode($this->channel, 'da_DK', 'terms_and_conditions')->willReturn($terms);
        $this->termsVersionRepository
            ->findOneInForce($terms, new \DateTimeImmutable('2026-11-17 12:00:00'), 'da_DK')
            ->willReturn($translation)
            ->shouldBeCalledTimes(2)
        ;

        self::assertSame($translation, $this->lookup->lookupBySlug('vilkaar', $this->channel, 'da_DK'));
        self::assertSame($translation, $this->lookup->lookupByCode('terms_and_conditions', $this->channel, 'da_DK'));
    }

    #[Test]
    public function it_returns_null_when_no_version_is_in_force(): void
    {
        $terms = $this->prophesize(TermsInterface::class)->reveal();

        $this->termsRepository->findOneByChannelAndLocaleAndCode($this->channel, 'da_DK', 'upcoming')->willReturn($terms);
        $this->termsVersionRepository->findOneInForce($terms, Argument::any(), 'da_DK')->willReturn(null);

        self::assertNull($this->lookup->lookupByCode('upcoming', $this->channel, 'da_DK'));
    }

    #[Test]
    public function it_returns_null_without_a_terms_and_looks_no_further(): void
    {
        $this->termsRepository->findOneByChannelAndLocaleAndSlug($this->channel, 'da_DK', 'unknown')->willReturn(null);
        $this->termsRepository->findOneByChannelAndLocaleAndCode($this->channel, 'da_DK', 'unknown')->willReturn(null);
        $this->termsVersionRepository->findOneInForce(Argument::cetera())->shouldNotBeCalled();

        self::assertNull($this->lookup->lookupBySlug('unknown', $this->channel, 'da_DK'));
        self::assertNull($this->lookup->lookupByCode('unknown', $this->channel, 'da_DK'));
    }
}
