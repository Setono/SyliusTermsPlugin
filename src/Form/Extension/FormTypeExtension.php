<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

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
        private readonly TermsProviderInterface $termsProvider,
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
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event): void {
            $formCode = $this->formCodes[$event->getForm()->getConfig()->getType()->getInnerType()::class] ?? null;
            if (null === $formCode) {
                return;
            }

            $terms = $this->termsProvider->getTerms($formCode);
            if ([] === $terms) {
                return;
            }

            $event->getForm()->add('terms', TermsCheckboxCollectionType::class, [
                'terms' => $terms,
            ]);
        });
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }
}
