<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\EventSubscriber\AddMenuSubscriber;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(AddMenuSubscriber::class)
        ->tag('kernel.event_subscriber')
    ;
};
