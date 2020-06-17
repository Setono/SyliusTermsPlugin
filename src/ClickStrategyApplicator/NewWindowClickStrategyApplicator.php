<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

use function Safe\preg_replace;

final class NewWindowClickStrategyApplicator implements ClickStrategyApplicatorInterface
{
    public function applyClickStrategy(string $termsLink): string
    {
        return preg_replace('/<a/', '<a target="_blank"', $termsLink);
    }
}
