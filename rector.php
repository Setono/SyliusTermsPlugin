<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->cacheDirectory('./.build/rector');

    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        __DIR__ . '/tests/Application',
        // The Sylius 1 form types the upgrade from 2.x maps are names stored in 2.x data, not classes the plugin uses, and
        // Sylius may remove them
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/src/Upgrade/FormClassMapper.php',
            __DIR__ . '/tests/Functional/Upgrade/UpgradeFrom2xCommandTest.php',
            __DIR__ . '/tests/Unit/Command/UpgradeFrom2xCommandTest.php',
            __DIR__ . '/tests/Unit/Upgrade/FormClassMapperTest.php',
        ],
    ]);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
    ]);
};
