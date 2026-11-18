<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcher;
use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcherInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // SetonoSyliusTermsExtension tags it as a listener of each Form's saved_event
    $services->set(TermsAnsweredDispatcher::class)
        ->args([
            service('event_dispatcher'),
            param('setono_sylius_terms.forms'),
        ])
        // Drops the events whose save didn't come in the request
        ->tag('kernel.reset', ['method' => 'reset'])
    ;

    $services->alias(TermsAnsweredDispatcherInterface::class, TermsAnsweredDispatcher::class);
};
