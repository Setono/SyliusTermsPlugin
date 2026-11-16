<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Checker\RequiredTermsChecker;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeChecker;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeCheckerInterface;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(VersionedTextChangeChecker::class);

    $services->alias(VersionedTextChangeCheckerInterface::class, VersionedTextChangeChecker::class);

    $services->set(RequiredTermsChecker::class)
        ->args([
            service(FormTermsLookupInterface::class),
        ])
    ;

    $services->alias(RequiredTermsCheckerInterface::class, RequiredTermsChecker::class);
};
