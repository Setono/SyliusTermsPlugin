<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(RequiredTermsTickedValidator::class)
        ->args([
            service(RequiredTermsCheckerInterface::class),
            service('sylius.context.channel'),
            service('sylius.context.locale'),
        ])
        ->tag('validator.constraint_validator')
    ;
};
