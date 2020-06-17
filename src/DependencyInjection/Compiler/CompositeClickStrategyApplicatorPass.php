<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection\Compiler;

use function Safe\sprintf;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class CompositeClickStrategyApplicatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (false === $container->hasDefinition('setono_sylius_terms.click_strategy_applicator.composite')) {
            return;
        }

        $definition = $container->getDefinition('setono_sylius_terms.click_strategy_applicator.composite');
        $tagName = 'setono_sylius_terms.click_strategy_applicator';
        foreach ($container->findTaggedServiceIds($tagName) as $clickStrategyApplicatorId => $attributes) {
            if (empty($attributes[0]['alias'])) {
                throw new \RuntimeException(sprintf(
                    'Service %s tagged with %s should have alias',
                    $clickStrategyApplicatorId,
                    $tagName
                ));
            }

            $definition->addMethodCall('addClickStrategyApplicator', [new Reference($clickStrategyApplicatorId), $attributes[0]['alias']]);
        }
    }
}
