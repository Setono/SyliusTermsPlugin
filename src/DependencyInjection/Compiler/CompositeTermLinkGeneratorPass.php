<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class CompositeTermLinkGeneratorPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition('setono_sylius_terms.term_link_generator.composite')) {
            return;
        }

        $definition = $container->getDefinition('setono_sylius_terms.term_link_generator.composite');

        $termLinkGenerators = $this->findAndSortTaggedServices('setono_sylius_terms.term_link_generator', $container);
        foreach ($termLinkGenerators as $termLinkGenerator) {
            $definition->addMethodCall('addTermLinkGenerator', [$termLinkGenerator]);
        }
    }
}
