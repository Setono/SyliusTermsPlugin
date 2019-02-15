<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

final class NewWindowClickStrategyApplicator implements ClickStrategyApplicatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function applyClickStrategy(string $termsLink): string
    {
        return (string) preg_replace('/\<a/', '<a target="_blank"', $termsLink);
    }
}
