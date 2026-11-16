<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeCheckerInterface;
use Setono\SyliusTermsPlugin\EventListener\CreateTermsVersionListener;
use Setono\SyliusTermsPlugin\EventListener\PreventTermsVersionEditListener;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(CreateTermsVersionListener::class)
        ->args([
            service(VersionedTextChangeCheckerInterface::class),
            service(TermsVersionSnapshotterInterface::class),
        ])
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
    ;

    $services->set(PreventTermsVersionEditListener::class)
        ->tag('doctrine.event_listener', ['event' => 'onFlush'])
    ;
};
