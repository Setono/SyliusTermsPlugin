<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

use function Safe\preg_replace;

final class ModalClickStrategyApplicator implements ClickStrategyApplicatorInterface
{
    public function applyClickStrategy(string $termsLink): string
    {
        // Dirty implementation, but...
        $termsLink = preg_replace('/href\="(.*?)"/', 'href="\1/partial"', $termsLink);

        return (string) preg_replace('/\<a/', '<a class="setono-terms-modal-link"', $termsLink);
    }
}
