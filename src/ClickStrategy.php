<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin;

final class ClickStrategy
{
    private function __construct()
    {
    }

    public const CLICK_STRATEGY_NEW_WINDOW = 'new_window';

    public const CLICK_STRATEGY_MODAL = 'modal';

    /**
     * @return list<string>
     */
    public static function getClickStrategies(): array
    {
        return [
            self::CLICK_STRATEGY_NEW_WINDOW,
            self::CLICK_STRATEGY_MODAL,
        ];
    }
}
