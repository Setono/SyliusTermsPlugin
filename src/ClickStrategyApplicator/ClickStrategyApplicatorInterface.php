<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

interface ClickStrategyApplicatorInterface
{
    public function applyClickStrategy(string $termsLink): string;
}
