<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Repository\TermsRepository;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepository;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    /**
     * The Forms every app has, unless it switches one off with enabled: false. Each is hooked at priority 50, just
     * before the hookable that renders the submit button at 0
     */
    private const DEFAULT_FORMS = [
        'checkout_complete' => [
            'type' => CompleteType::class,
            'hooks' => ['sylius_shop.checkout.complete.content.form' => 50],
            'saved_event' => 'sylius.order.post_complete',
        ],
        'registration' => [
            'type' => CustomerRegistrationType::class,
            'hooks' => ['sylius_shop.account.register.content.form' => 50],
            'saved_event' => 'sylius.customer.post_register',
        ],
        'contact' => [
            'type' => ContactType::class,
            'hooks' => ['sylius_shop.contact.contact_request.content.form' => 50],
        ],
    ];

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_terms');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('forms')
                    ->info('The shop forms a Terms can be placed on, keyed by Form code. Entries add to the checkout_complete, registration and contact defaults. An entry with a default\'s code overrides the keys it sets and keeps the others. The processed value holds only the enabled Forms, with every key set')
                    ->useAttributeAsKey('code')
                    ->normalizeKeys(false)
                    // Used when no config sets forms at all. Otherwise the validate() below adds the defaults
                    ->defaultValue(self::resolveForms([]))
                    ->arrayPrototype()
                        // No child has a default value, so resolveForms() can tell the keys an app left out from the
                        // keys it set, and fill the left-out ones from the default Form with the same code
                        ->children()
                            ->scalarNode('type')
                                ->info('The form type class. Required, except on an entry that overrides a default Form')
                                ->cannotBeEmpty()
                                ->example('App\Form\Type\NewsletterType')
                            ->end()
                            ->scalarNode('label')
                                ->info('The translation key the admin shows for the Form. Defaults to setono_sylius_terms.form.terms.term_form.<code>')
                                ->example('app.form.newsletter')
                            ->end()
                            ->variableNode('hooks')
                                ->info('The Twig hooks that render the Terms checkboxes, mapped to the priority of the hookable. Set [] to render form.terms by hand')
                                ->example(['app.newsletter.form' => 50])
                                ->validate()
                                    ->ifTrue(static fn (mixed $hooks): bool => !self::isHookMap($hooks))
                                    ->thenInvalid('The hooks must map each hook name to an integer priority, or be [], but got %s')
                                ->end()
                            ->end()
                            ->scalarNode('saved_event')
                                ->info('The event dispatched once the data the Form submitted is saved')
                                ->example('app.newsletter.post_subscribe')
                            ->end()
                            ->booleanNode('enabled')
                                ->info('Set false to switch a default Form off')
                            ->end()
                        ->end()
                    ->end()
                    ->validate()
                        ->always(self::resolveForms(...))
                    ->end()
                ->end()
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('terms')
                            ->defaultValue('terms')
                            ->cannotBeEmpty()
                            ->info('The path prefix for displaying terms on the frontend. Example: https://example.com/terms/privacy-policy - here "terms" is the path prefix')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('order_confirmation_email')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('pdf')
                            ->defaultFalse()
                            ->info('Set true to generate Terms PDFs for the order confirmation email. It needs sylius/pdf-generation-bundle, dompdf/dompdf and symfony/http-client, with SyliusPdfGenerationBundle registered')
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    /**
     * Adds the default Forms to the configured ones, fills each key an entry left out, from the default Form with the
     * same code or else from the key's own default, drops the disabled Forms and fails when two enabled Forms share a
     * form type
     *
     * @param array<array-key, array<string, mixed>> $forms
     *
     * @return array<string, array{type: string, label: string, hooks: array<string, int>, saved_event: string|null}>
     */
    private static function resolveForms(array $forms): array
    {
        $resolved = [];

        /** @var array<string, string> $codesByType */
        $codesByType = [];

        // The union lists the default codes first, then the app's own codes
        foreach (array_keys(self::DEFAULT_FORMS + $forms) as $code) {
            $code = (string) $code;

            /** @var array{type?: string, label: string|null, hooks: array<string, int>, saved_event: string|null, enabled: bool} $form */
            $form = ($forms[$code] ?? []) + (self::DEFAULT_FORMS[$code] ?? []) + [
                'label' => null,
                'hooks' => [],
                'saved_event' => null,
                'enabled' => true,
            ];

            if (!$form['enabled']) {
                continue;
            }

            if (!isset($form['type'])) {
                throw new \InvalidArgumentException(sprintf('The Form "%s" has no type. Set it to the class of the form type the Terms checkboxes are added to.', $code));
            }

            // The plugin finds a Form by the type of the form being built, so two Forms with one type can't be told apart
            if (isset($codesByType[$form['type']])) {
                throw new \InvalidArgumentException(sprintf(
                    'The Forms "%s" and "%s" have the same form type, "%s". Give each Form its own form type: the plugin finds a Form by the type of the form being built, so it can\'t tell the two apart. To reuse the type of a default Form under your own code, switch the default off with enabled: false.',
                    $codesByType[$form['type']],
                    $code,
                    $form['type'],
                ));
            }

            $codesByType[$form['type']] = $code;

            $resolved[$code] = [
                'type' => $form['type'],
                'label' => $form['label'] ?? sprintf('setono_sylius_terms.form.terms.term_form.%s', $code),
                'hooks' => $form['hooks'],
                'saved_event' => $form['saved_event'],
            ];
        }

        return $resolved;
    }

    private static function isHookMap(mixed $hooks): bool
    {
        if (!is_array($hooks)) {
            return false;
        }

        foreach ($hooks as $hook => $priority) {
            if (!is_string($hook) || !is_int($priority)) {
                return false;
            }
        }

        return true;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('resources')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('terms')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(Terms::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(TermsRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                        ->scalarNode('form')->defaultValue(TermsType::class)->cannotBeEmpty()->end()
                                    ->end()
                                ->end()
                                ->arrayNode('translation')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->variableNode('options')->end()
                                        ->arrayNode('classes')
                                            ->addDefaultsIfNotSet()
                                            ->children()
                                                ->scalarNode('model')->defaultValue(TermsTranslation::class)->cannotBeEmpty()->end()
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                                ->scalarNode('form')->defaultValue(TermsTranslationType::class)->cannotBeEmpty()->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('terms_version')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->variableNode('options')->end()
                                ->arrayNode('classes')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->scalarNode('model')->defaultValue(TermsVersion::class)->cannotBeEmpty()->end()
                                        ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                        ->scalarNode('repository')->defaultValue(TermsVersionRepository::class)->cannotBeEmpty()->end()
                                        ->scalarNode('factory')->defaultValue(Factory::class)->end()
                                    ->end()
                                ->end()
                                ->arrayNode('translation')
                                    ->addDefaultsIfNotSet()
                                    ->children()
                                        ->variableNode('options')->end()
                                        ->arrayNode('classes')
                                            ->addDefaultsIfNotSet()
                                            ->children()
                                                ->scalarNode('model')->defaultValue(TermsVersionTranslation::class)->cannotBeEmpty()->end()
                                                ->scalarNode('controller')->defaultValue(ResourceController::class)->cannotBeEmpty()->end()
                                                ->scalarNode('repository')->cannotBeEmpty()->end()
                                                ->scalarNode('factory')->defaultValue(Factory::class)->end()
        ;
    }
}
