<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsChecker;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\Channel;

final class RequiredTermsCheckerTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_reports_the_required_terms_left_unticked_in_position_order(): void
    {
        $termsAndConditions = self::inForce('terms_and_conditions', required: true);
        $newsletter = self::inForce('newsletter', required: false);
        $privacyPolicy = self::inForce('privacy_policy', required: true);
        $returnPolicy = self::inForce('return_policy', required: true);

        $checker = $this->createChecker([$termsAndConditions, $newsletter, $privacyPolicy, $returnPolicy]);

        self::assertSame(
            [$termsAndConditions, $returnPolicy],
            $checker->getUntickedRequiredTerms('checkout_complete', new Channel(), 'en_US', ['privacy_policy']),
        );
    }

    #[Test]
    public function it_ignores_optional_terms_left_unticked(): void
    {
        $checker = $this->createChecker([self::inForce('newsletter', required: false)]);

        self::assertSame([], $checker->getUntickedRequiredTerms('checkout_complete', new Channel(), 'en_US', []));
    }

    #[Test]
    public function it_ignores_ticked_codes_of_terms_that_are_not_shown_on_the_form(): void
    {
        $termsAndConditions = self::inForce('terms_and_conditions', required: true);

        $checker = $this->createChecker([$termsAndConditions]);

        self::assertSame([], $checker->getUntickedRequiredTerms('checkout_complete', new Channel(), 'en_US', ['terms_and_conditions', 'unknown']));
        self::assertSame([$termsAndConditions], $checker->getUntickedRequiredTerms('checkout_complete', new Channel(), 'en_US', ['unknown']));
    }

    #[Test]
    public function it_asks_the_lookup_for_the_terms_shown_on_the_form_in_the_channel_and_locale(): void
    {
        $channel = new Channel();

        $lookup = $this->prophesize(FormTermsLookupInterface::class);
        $lookup->lookup('registration', $channel, 'da_DK')->willReturn([])->shouldBeCalledOnce();

        (new RequiredTermsChecker($lookup->reveal()))->getUntickedRequiredTerms('registration', $channel, 'da_DK', []);
    }

    /**
     * @param list<TermsVersionTranslationInterface> $inForce
     */
    private function createChecker(array $inForce): RequiredTermsChecker
    {
        $lookup = $this->prophesize(FormTermsLookupInterface::class);
        $lookup->lookup('checkout_complete', Argument::type(Channel::class), 'en_US')->willReturn($inForce);

        return new RequiredTermsChecker($lookup->reveal());
    }

    private static function inForce(string $code, bool $required): TermsVersionTranslationInterface
    {
        $terms = new Terms();
        $terms->setCode($code);
        $terms->setRequired($required);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('en_US');
        $version->addTranslation($translation);

        return $translation;
    }
}
