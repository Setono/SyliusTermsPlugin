<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Validator\Constraints;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTicked;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Channel\Model\Channel;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\ConstraintValidatorInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<RequiredTermsTickedValidator>
 */
final class RequiredTermsTickedValidatorTest extends ConstraintValidatorTestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<RequiredTermsCheckerInterface> */
    private ObjectProphecy $requiredTermsChecker;

    private ChannelInterface $channel;

    protected function createValidator(): ConstraintValidatorInterface
    {
        $this->requiredTermsChecker = $this->prophesize(RequiredTermsCheckerInterface::class);
        $this->channel = new Channel();

        $channelContext = $this->prophesize(ChannelContextInterface::class);
        $channelContext->getChannel()->willReturn($this->channel);

        $localeContext = $this->prophesize(LocaleContextInterface::class);
        $localeContext->getLocaleCode()->willReturn('en_US');

        return new RequiredTermsTickedValidator(
            $this->requiredTermsChecker->reveal(),
            $channelContext->reveal(),
            $localeContext->reveal(),
        );
    }

    #[Test]
    public function it_raises_no_violation_when_every_required_terms_is_ticked(): void
    {
        $this->requiredTermsChecker
            ->getUntickedRequiredTerms('checkout_complete', $this->channel, 'en_US', ['terms_and_conditions'])
            ->willReturn([])
        ;

        $this->validator->validate(['terms_and_conditions'], new RequiredTermsTicked(form: 'checkout_complete'));

        $this->assertNoViolation();
    }

    #[Test]
    public function it_raises_a_violation_at_the_code_of_each_unticked_required_terms(): void
    {
        $this->requiredTermsChecker
            ->getUntickedRequiredTerms('checkout_complete', $this->channel, 'en_US', ['newsletter'])
            ->willReturn([
                self::inForce('terms_and_conditions', 'Terms and conditions'),
                self::inForce('privacy_policy', 'Privacy policy'),
            ])
        ;

        $this->validator->validate(['newsletter'], new RequiredTermsTicked(form: 'checkout_complete'));

        $this->buildViolation('setono_sylius_terms.terms_checkbox.required')
            ->atPath('property.path[terms_and_conditions]')
            ->setParameter('{{ name }}', '"Terms and conditions"')
            ->setCode(RequiredTermsTicked::NOT_TICKED_ERROR)
            ->buildNextViolation('setono_sylius_terms.terms_checkbox.required')
            ->atPath('property.path[privacy_policy]')
            ->setParameter('{{ name }}', '"Privacy policy"')
            ->setCode(RequiredTermsTicked::NOT_TICKED_ERROR)
            ->assertRaised()
        ;
    }

    #[Test]
    public function it_uses_a_custom_message(): void
    {
        $this->requiredTermsChecker
            ->getUntickedRequiredTerms('registration', $this->channel, 'en_US', [])
            ->willReturn([self::inForce('privacy_policy', 'Privacy policy')])
        ;

        $this->validator->validate([], new RequiredTermsTicked(form: 'registration', message: 'app.terms.required'));

        $this->buildViolation('app.terms.required')
            ->atPath('property.path[privacy_policy]')
            ->setParameter('{{ name }}', '"Privacy policy"')
            ->setCode(RequiredTermsTicked::NOT_TICKED_ERROR)
            ->assertRaised()
        ;
    }

    #[Test]
    public function null_counts_as_nothing_ticked(): void
    {
        $this->requiredTermsChecker
            ->getUntickedRequiredTerms('checkout_complete', $this->channel, 'en_US', [])
            ->willReturn([self::inForce('terms_and_conditions', 'Terms and conditions')])
            ->shouldBeCalledOnce()
        ;

        $this->validator->validate(null, new RequiredTermsTicked(form: 'checkout_complete'));

        self::assertCount(1, $this->context->getViolations());
    }

    #[Test]
    #[DataProvider('provideInvalidValues')]
    public function it_only_accepts_a_list_of_codes(mixed $value): void
    {
        $this->requiredTermsChecker->getUntickedRequiredTerms(Argument::cetera())->shouldNotBeCalled();

        $this->expectException(UnexpectedValueException::class);

        $this->validator->validate($value, new RequiredTermsTicked(form: 'checkout_complete'));
    }

    /**
     * @return \Generator<string, array{mixed}>
     */
    public static function provideInvalidValues(): \Generator
    {
        yield 'a string' => ['terms_and_conditions'];
        yield 'a map of answers' => [['terms_and_conditions' => true]];
        yield 'a list holding a non-string' => [['terms_and_conditions', 1]];
    }

    #[Test]
    public function it_only_validates_its_own_constraint(): void
    {
        $this->expectException(UnexpectedTypeException::class);

        $this->validator->validate([], new NotNull());
    }

    #[Test]
    public function the_constraint_keeps_its_groups_and_payload(): void
    {
        $constraint = new RequiredTermsTicked(form: 'contact', groups: ['setono_sylius_terms'], payload: 'payload');

        self::assertSame('contact', $constraint->form);
        self::assertSame(['setono_sylius_terms'], $constraint->groups);
        self::assertSame('payload', $constraint->payload);
        self::assertSame('NOT_TICKED_ERROR', RequiredTermsTicked::getErrorName(RequiredTermsTicked::NOT_TICKED_ERROR));
    }

    private static function inForce(string $code, string $name): TermsVersionTranslationInterface
    {
        $terms = new Terms();
        $terms->setCode($code);

        $version = new TermsVersion();
        $terms->addVersion($version);

        $translation = new TermsVersionTranslation();
        $translation->setLocale('en_US');
        $translation->setName($name);
        $version->addTranslation($translation);

        return $translation;
    }
}
