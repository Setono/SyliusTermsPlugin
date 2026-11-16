<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Checker;

use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

/**
 * Enforces Required terms outside Symfony forms. The RequiredTermsTicked constraint wraps it
 */
interface RequiredTermsCheckerInterface
{
    /**
     * Returns the required Terms shown on the Form now (see FormTermsLookupInterface) whose code isn't among the
     * ticked ones, as their version in force, in position order. Optional Terms, and ticked codes of Terms that aren't
     * shown on the Form, are ignored
     *
     * @param string $form a Form code
     * @param list<string> $tickedTerms the codes of the ticked Terms
     *
     * @return list<TermsVersionTranslationInterface>
     */
    public function getUntickedRequiredTerms(string $form, ChannelInterface $channel, string $localeCode, array $tickedTerms): array;
}
