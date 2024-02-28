<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\Form\FormTypeInterface;

interface TermsProviderInterface
{
    /**
     * Returns an array of terms based on the current channel and locale
     * Returns an empty array if there are no valid terms
     *
     * @param class-string<FormTypeInterface> $form
     *
     * @return TermsInterface[]
     */
    public function getTerms(string $form): array;
}
