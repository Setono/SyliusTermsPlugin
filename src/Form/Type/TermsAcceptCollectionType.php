<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\TermLinkGenerator\TermLinkGeneratorInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TermsAcceptCollectionType extends AbstractType
{
    public function __construct(
        private readonly TermLinkGeneratorInterface $termLinkGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function checkAcceptedTerms(FormEvent $event): void
    {
        $form = $event->getForm();
        $options = $form->getConfig()->getOptions();

        /** @var TermsInterface $terms */
        foreach ($options['terms'] as $terms) {
            $termsField = $form->get((string) $terms->getCode());
            if (false === $termsField->getData()) {
                /** @var string $messageTemplate */
                $messageTemplate = $options['error_message'];
                $messageOptions = ['{{ name }}' => $terms->getName()];
                $message = $this->translator->trans($messageTemplate, $messageOptions);
                $termsField->addError(new FormError($message, $messageTemplate, $messageOptions));
            }
        }
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::POST_SUBMIT, $this->checkAcceptedTerms(...));

        /** @var TermsInterface $terms */
        foreach ($options['terms'] as $terms) {
            if (!$terms instanceof TermsInterface) {
                throw new InvalidConfigurationException(
                    sprintf('Each object passed as terms list must implement "%s"', TermsInterface::class),
                );
            }

            $builder->add((string) $terms->getCode(), TermsAcceptType::class, [
                'label' => $terms->getName() ?? $terms->getCode(),
                'terms_link' => $this->termLinkGenerator->generate($terms),
                'required' => false,
                'value' => true,
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired([
                'terms',
                'error_message',
            ])
            ->setAllowedTypes('terms', ['array'])
            ->setDefaults([
                'label' => 'setono_sylius_terms.form.terms_accept.label',
                'mapped' => false,
                'terms' => null,
                'error_message' => 'setono_sylius_terms.form.terms_accept.terms_should_be_accepted',
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_accept_collection';
    }
}
