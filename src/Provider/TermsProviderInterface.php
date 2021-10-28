<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermsProviderInterface
{
    /**
     * Returns an array of terms based on the current channel and locale
     * Returns an empty array if there are no valid terms
     *
     * @return TermsInterface[]
     */
    public function getTerms(): array;

    /**
     * Returns an array of terms based on the current channel and locale and place
     * Returns an empty array if there are no valid terms
     *
     * @return TermsInterface[]
     */
    public function getEnabledTermsForCompleteForm(): array;

    /**
     * Returns an array of terms based on the current channel and locale and place
     * Returns an empty array if there are no valid terms
     *
     * @return TermsInterface[]
     */
    public function getEnabledTermsForCustomerRegistrationForm(): array;

    /**
     * Returns an array of terms based on the current channel and locale and place
     * Returns an empty array if there are no valid terms
     *
     * @return TermsInterface[]
     */
    public function getEnabledTermsForFooterTemplate(): array;
}
