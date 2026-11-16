<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeChecker;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeCheckerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(VersionedTextChangeChecker::class);

    $services->alias(VersionedTextChangeCheckerInterface::class, VersionedTextChangeChecker::class);
};
