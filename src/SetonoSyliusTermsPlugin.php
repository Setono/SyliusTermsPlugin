<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin;

use Setono\SyliusTermsPlugin\DependencyInjection\Compiler\CompositeClickStrategyApplicatorPass;
use Setono\SyliusTermsPlugin\DependencyInjection\Compiler\CompositeTermLinkGeneratorPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Sylius\Bundle\ResourceBundle\AbstractResourceBundle;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class SetonoSyliusTermsPlugin extends AbstractResourceBundle
{
    use SyliusPluginTrait;

    public function getSupportedDrivers(): array
    {
        return [
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
        ];
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new CompositeTermLinkGeneratorPass());
        $container->addCompilerPass(new CompositeClickStrategyApplicatorPass());
    }
}
