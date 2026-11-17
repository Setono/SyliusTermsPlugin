<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$configuration = (new Configuration())
    ->addPathToExclude(__DIR__ . '/tests')
    // The plugin type-hints Psr\Clock\ClockInterface and injects the 'clock' service, which symfony/clock provides
    ->ignoreErrorsOnPackage('symfony/clock', [ErrorType::UNUSED_DEPENDENCY])
    // The admin templates render Twig components ({% component %}), which symfony/ux-twig-component provides
    ->ignoreErrorsOnPackage('symfony/ux-twig-component', [ErrorType::UNUSED_DEPENDENCY])
;

// The Terms PDFs are opt-in, so their tooling is suggested, not required. Only code that runs when
// setono_sylius_terms.order_confirmation_email.pdf is true uses it, after the extension checked that it is installed.
// The tests install it through require-dev. CI analyses without require-dev: there sylius/pdf-generation-bundle isn't
// installed, and symfony/http-client is, because sylius/core requires it
/** @var array{require-dev?: array<string, string>} $composerJson */
$composerJson = json_decode((string) file_get_contents(__DIR__ . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

if (isset($composerJson['require-dev'])) {
    $configuration->ignoreErrorsOnPackages(['sylius/pdf-generation-bundle', 'symfony/http-client'], [ErrorType::DEV_DEPENDENCY_IN_PROD]);
} else {
    $configuration
        ->ignoreUnknownClassesRegex('~^Sylius\\\\PdfGenerationBundle\\\\~')
        ->ignoreErrorsOnPackage('symfony/http-client', [ErrorType::SHADOW_DEPENDENCY])
    ;
}

return $configuration;
