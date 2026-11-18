<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Command\UpgradeFrom2xCommand;
use Setono\SyliusTermsPlugin\Converter\ContentConverter;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;
use Setono\SyliusTermsPlugin\Upgrade\ContentCaseDetector;
use Setono\SyliusTermsPlugin\Upgrade\FormClassMapper;
use Setono\SyliusTermsPlugin\Upgrade\Upgrader;
use Setono\SyliusTermsPlugin\Upgrade\UpgraderInterface;

// The upgrade from 2.x, which 4.0 removes
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(FormClassMapper::class)
        ->args([
            param('setono_sylius_terms.forms'),
        ])
    ;

    $services->set(ContentCaseDetector::class)
        ->args([
            service(ContentConverter::class),
        ])
    ;

    $services->set(Upgrader::class)
        ->args([
            service('doctrine'),
            param('setono_sylius_terms.model.terms.class'),
            service(FormClassMapper::class),
            service(ContentCaseDetector::class),
            service(TermsVersionSnapshotterInterface::class),
            service('clock'),
        ])
    ;

    $services->alias(UpgraderInterface::class, Upgrader::class);

    $services->set(UpgradeFrom2xCommand::class)
        ->args([
            service(UpgraderInterface::class),
        ])
        ->tag('console.command')
    ;
};
