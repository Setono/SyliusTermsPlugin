<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

interface ClickStrategyApplicatorInterface
{
    /**
     * @param string $termsLink
     * @return string
     */
    public function applyClickStrategy(string $termsLink): string;
}
