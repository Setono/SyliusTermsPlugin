<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Model\TermsInterface;

interface TermsProviderInterface
{
    /**
     * Returns the Terms the Form lookup finds for the Form in the current channel and locale, now
     *
     * @param string $form a Form code
     *
     * @return list<TermsInterface>
     */
    public function getTerms(string $form): array;
}
