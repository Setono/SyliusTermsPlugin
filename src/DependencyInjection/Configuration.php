<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Repository\TermsRepository;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\ResourceBundle\Controller\ResourceController;
use Sylius\Component\Resource\Factory\Factory;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('setono_sylius_terms');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        /** @psalm-suppress UndefinedInterfaceMethod,PossiblyNullReference,MixedMethodCall */
        $rootNode
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('forms')
                    ->defaultValue([CompleteType::class => ['label' => null]])
                    ->useAttributeAsKey('class')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('label')->defaultNull()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('terms')
                            ->defaultValue('terms')
                            ->cannotBeEmpty()
                            ->info('The path prefix for displaying terms on the frontend. Example: https://example.com/terms/privacy-policy - here "terms" is the path prefix')
        ;

        $this->addResourcesSection($rootNode);

        return $treeBuilder;
    }

    private function addResourcesSection(ArrayNodeDefinition $node): void
    {
        /** @psalm-suppress UndefinedInterfaceMethod,PossiblyNullReference,MixedMethodCall */
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
        ;
    }
}
