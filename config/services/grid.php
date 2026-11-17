<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Grid\TermsGrid;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The grid's name and resource class come from its #[AsGrid] attribute
    $services->set(TermsGrid::class)
        ->args([
            param('setono_sylius_terms.forms'),
        ])
        ->tag('sylius.invokable_grid')
    ;
};
