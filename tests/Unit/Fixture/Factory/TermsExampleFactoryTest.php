<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Fixture\Factory;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

final class TermsExampleFactoryTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_places_the_terms_on_the_checkout_complete_form_by_default(): void
    {
        $termsFactory = $this->prophesize(FactoryInterface::class);
        $termsFactory->createNew()->willReturn(new Terms());

        $termsRepository = $this->prophesize(TermsRepositoryInterface::class);
        $termsRepository->findOneBy(['code' => 'eula'])->willReturn(null);

        $localeRepository = $this->prophesize(RepositoryInterface::class);
        $localeRepository->findAll()->willReturn([]);

        $factory = new TermsExampleFactory(
            $termsFactory->reveal(),
            $termsRepository->reveal(),
            $localeRepository->reveal(),
            $this->prophesize(ChannelRepositoryInterface::class)->reveal(),
        );

        self::assertSame(['checkout_complete'], $factory->create(['code' => 'eula', 'channels' => []])->getForms());
    }
}
