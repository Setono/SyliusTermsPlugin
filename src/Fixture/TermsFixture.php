<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class TermsFixture extends AbstractResourceFixture
{
    public function getName(): string
    {
        return 'setono_terms';
    }

    /**
     * Options left out here get their defaults from TermsExampleFactory. forms, channels and locales are variable nodes,
     * because an array node defaults to an empty list, which would override those defaults
     */
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->scalarNode('slug')->cannotBeEmpty()->end()
                ->scalarNode('label')->cannotBeEmpty()->end()
                ->scalarNode('content')->cannotBeEmpty()->end()
                ->booleanNode('enabled')->end()
                ->booleanNode('required')->end()
                ->integerNode('position')->end()
                ->variableNode('forms')->end()
                ->variableNode('channels')->end()
                ->variableNode('locales')->end()
                ->variableNode('translations')->end()
        ;
    }
}
