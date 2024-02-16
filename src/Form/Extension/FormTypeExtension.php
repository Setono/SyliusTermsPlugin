<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Generator;
use Setono\SyliusTermsPlugin\Form\Type\TermsAcceptCollectionType;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormTypeInterface;

final class FormTypeExtension extends AbstractTypeExtension
{
    /** @var list<class-string<FormTypeInterface>> */
    private array $forms;

    /**
     * @param array<class-string<FormTypeInterface>, array{label: string}> $forms
     */
    public function __construct(
        private readonly TermsProviderInterface $termsProvider,
        array $forms,
    ) {
        $this->forms = array_keys($forms);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (PreSetDataEvent $event): void {
            $formTypeClass = $event->getForm()->getConfig()->getType()->getInnerType();
            if (!in_array($formTypeClass::class, $this->forms, true)) {
                return;
            }

            $terms = $this->termsProvider->getTerms($formTypeClass::class);
            if ([] === $terms) {
                return;
            }

            $event->getForm()->add('terms', TermsAcceptCollectionType::class, [
                'terms' => $terms,
            ]);
        });
    }

    public static function getExtendedTypes(): Generator
    {
        yield FormType::class;
    }
}
