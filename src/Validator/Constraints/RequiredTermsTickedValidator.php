<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Validator\Constraints;

use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class RequiredTermsTickedValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RequiredTermsCheckerInterface $requiredTermsChecker,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RequiredTermsTicked) {
            throw new UnexpectedTypeException($constraint, RequiredTermsTicked::class);
        }

        // Unlike most constraints, null isn't skipped: a value that ticks nothing must not get past the required Terms
        $value ??= [];

        if (!self::isListOfCodes($value)) {
            throw new UnexpectedValueException($value, 'list<string>');
        }

        $untickedRequiredTerms = $this->requiredTermsChecker->getUntickedRequiredTerms(
            $constraint->form,
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
            $value,
        );

        foreach ($untickedRequiredTerms as $translation) {
            $this->context->buildViolation($constraint->message)
                ->atPath(sprintf('[%s]', (string) $translation->getVersion()->getTerms()?->getCode()))
                ->setParameter('{{ name }}', $this->formatValue($translation->getName()))
                ->setCode(RequiredTermsTicked::NOT_TICKED_ERROR)
                ->addViolation()
            ;
        }
    }

    /**
     * @phpstan-assert-if-true list<string> $value
     */
    private static function isListOfCodes(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && [] === array_filter($value, static fn (mixed $code): bool => !is_string($code));
    }
}
