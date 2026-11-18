<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Event;

use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;

/**
 * Whether the customer ticked the checkbox of a Terms when submitting a Form. The plugin doesn't store it (ADR 0001)
 */
final readonly class Answer
{
    /**
     * @param TermsVersionInterface $version the Terms version shown, which was in force when the form was built. Its
     *                                       Terms is getTerms(), and its text in the locale shown is
     *                                       getTranslation(<the event's locale code>)
     * @param bool $ticked always true for a required Terms, since a Form can't be submitted with one left unticked
     */
    public function __construct(
        public TermsVersionInterface $version,
        public bool $ticked,
    ) {
    }
}
