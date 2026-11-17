<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Type;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTicked;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Webmozart\Assert\Assert;

/**
 * One checkbox per Terms shown on a Form, named by the Terms' code and labelled with the Label of the Terms version in
 * force. Its data is the list of the ticked Terms' codes, which the RequiredTermsTicked constraint checks
 */
final class TermsCheckboxCollectionType extends AbstractType
{
    /**
     * The field validates in its own group. Symfony resolves a field's own groups before its parent's, so required
     * Terms are enforced whatever groups the Form validates in
     */
    public const VALIDATION_GROUP = 'setono_sylius_terms_checkboxes';

    public function __construct(private readonly LabelRendererInterface $labelRenderer)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var list<TermsVersionTranslationInterface> $translations */
        $translations = $options['terms'];

        $codes = [];

        foreach ($translations as $translation) {
            $terms = $translation->getVersion()->getTerms();
            Assert::notNull($terms);

            $code = (string) $terms->getCode();
            $codes[] = $code;

            $builder->add($code, CheckboxType::class, [
                'label' => $this->labelRenderer->render($translation),
                'label_html' => true,
                // The label is already rendered, so it must not be read as a translation key
                'translation_domain' => false,
                // Adds the HTML attribute and the shop theme's required class. Shop forms are novalidate, so only
                // the RequiredTermsTicked constraint enforces it
                'required' => $terms->isRequired(),
            ]);
        }

        $builder->addModelTransformer(new CallbackTransformer(
            // The ticked codes to an answer per checkbox
            static fn (?array $tickedCodes): array => array_combine($codes, array_map(
                static fn (string $code): bool => in_array($code, $tickedCodes ?? [], true),
                $codes,
            )),
            // An answer per checkbox to the ticked codes. A numeric code is an int key, hence the cast
            static fn (?array $answers): array => array_map(
                strval(...),
                array_keys(array_filter($answers ?? [], static fn (mixed $ticked): bool => true === $ticked)),
            ),
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['form', 'terms'])
            ->setAllowedTypes('form', 'string')
            ->setAllowedTypes('terms', TermsVersionTranslationInterface::class . '[]')
            ->setInfo('form', 'The code of the Form the checkboxes are on')
            ->setInfo('terms', 'The Terms version in force of each Terms shown, in the locale shown, as the Form lookup returns them')
            ->setDefaults([
                'label' => false,
                'mapped' => false,
                'validation_groups' => [self::VALIDATION_GROUP],
                'constraints' => static function (Options $options): array {
                    /** @var string $form */
                    $form = $options['form'];

                    return [new RequiredTermsTicked(form: $form, groups: [self::VALIDATION_GROUP])];
                },
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'setono_sylius_terms_terms_checkbox_collection';
    }
}
