<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // The plugin type-hints Psr\Clock\ClockInterface and injects the 'clock' service, which symfony/clock provides
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::UNUSED_DEPENDENCY])
    // The admin templates render Twig components ({% component %}), which symfony/ux-twig-component provides
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::UNUSED_DEPENDENCY])
;
