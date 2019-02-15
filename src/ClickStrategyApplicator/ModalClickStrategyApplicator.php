<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

final class ModalClickStrategyApplicator implements ClickStrategyApplicatorInterface
{
    /**
     * {@inheritdoc}
     */
    public function applyClickStrategy(string $termsLink): string
    {
        // Dirty implementation, but...
        $termsLink = preg_replace('/href\="(.*?)"/', 'href="\1/partial"', $termsLink);

        return (string) preg_replace('/\<a/', '<a class="setono-terms-modal-link"', $termsLink);
    }
}
