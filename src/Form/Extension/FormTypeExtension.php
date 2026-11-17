<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Adds the terms field to the form types of the configured Forms, with the Terms the Form lookup finds in the current
 * channel and locale, now
 */
final class FormTypeExtension extends AbstractTypeExtension
{
    /**
     * The codes of the Forms, by their form type class
     *
     * @var array<class-string, string>
     */
    private readonly array $formCodes;

    /**
     * @param array<string, array{type: class-string}> $forms
     */
    public function __construct(
        private readonly FormTermsLookupInterface $formTermsLookup,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        array $forms,
    ) {
        $formCodes = [];
        foreach ($forms as $code => $form) {
            $formCodes[$form['type']] ??= $code;
        }

        $this->formCodes = $formCodes;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The type of the form being built, which may extend FormType through other types
        $formCode = $this->formCodes[$builder->getType()->getInnerType()::class] ?? null;
        if (null === $formCode) {
            return;
        }

        $terms = $this->formTermsLookup->lookup(
            $formCode,
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
        );

        // A Form with no Terms renders nothing
        if ([] === $terms) {
            return;
        }

        $builder->add('terms', TermsCheckboxCollectionType::class, [
            'form' => $formCode,
            'terms' => $terms,
        ]);
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
