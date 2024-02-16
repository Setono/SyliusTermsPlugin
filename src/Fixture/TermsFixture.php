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

    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        /** @psalm-suppress UndefinedInterfaceMethod,PossiblyNullReference,MixedMethodCall */
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->scalarNode('slug')->cannotBeEmpty()->end()
                ->scalarNode('label')->cannotBeEmpty()->end()
                ->scalarNode('content')->cannotBeEmpty()->end()

                ->variableNode('translations')->cannotBeEmpty()->defaultValue([])->end()
                ->variableNode('channels')->cannotBeEmpty()->defaultValue([])->end()

                ->scalarNode('created_at')->cannotBeEmpty()->end()
                ->scalarNode('updated_at')->cannotBeEmpty()->end()
        ;
    }
}
