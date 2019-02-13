<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Fixture;

use Sylius\Bundle\CoreBundle\Fixture\AbstractResourceFixture;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

final class TermsFixture extends AbstractResourceFixture
{
    /**
     * @return string
     */
    public function getName(): string
    {
        return 'setono_terms';
    }

    /**
     * {@inheritdoc}
     */
    protected function configureResourceNode(ArrayNodeDefinition $resourceNode): void
    {
        $resourceNode
            ->children()
                ->scalarNode('code')->cannotBeEmpty()->end()
                ->scalarNode('name')->cannotBeEmpty()->end()
                ->scalarNode('slug')->cannotBeEmpty()->end()
                ->scalarNode('explanation')->cannotBeEmpty()->end()
                ->scalarNode('content')->cannotBeEmpty()->end()

                ->variableNode('translations')->cannotBeEmpty()->defaultValue([])->end()
                ->variableNode('channels')->cannotBeEmpty()->defaultValue([])->end()

                ->scalarNode('created_at')->cannotBeEmpty()->end()
                ->scalarNode('updated_at')->cannotBeEmpty()->end()
            ->end()
        ;
    }
}
