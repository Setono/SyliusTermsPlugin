<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface TermsVersionRepositoryInterface extends RepositoryInterface
{
    /**
     * Returns the version of the Terms that was in force at the given moment, read in the given locale. That is the
     * latest version saved at or before the moment. For an order, the moment is when checkout completed and the
     * locale is the order's locale.
     *
     * Returns null when the moment is before the Terms' first version, or when the version in force has no
     * translation in the locale. There is no fallback to another locale or to another version.
     *
     * The returned translation holds the name, label and content; its version holds the number and the moment it is
     * in force from
     */
    public function findOneInForce(TermsInterface $terms, \DateTimeInterface $at, string $localeCode): ?TermsVersionTranslationInterface;
}
