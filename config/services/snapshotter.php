<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(TermsVersionSnapshotter::class)
        ->args([
            service('setono_sylius_terms.factory.terms_version'),
            service('setono_sylius_terms.factory.terms_version_translation'),
            service('clock'),
        ])
    ;

    $services->alias(TermsVersionSnapshotterInterface::class, TermsVersionSnapshotter::class);
};
