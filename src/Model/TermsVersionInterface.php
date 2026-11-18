<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslatableInterface;

/**
 * An immutable, numbered snapshot of a Terms' name, label and content in every locale. The translations hold the text
 * per locale. A saved version can't be edited (a flush that changes one fails): a change to the Terms creates the
 * next version instead
 */
interface TermsVersionInterface extends ResourceInterface, TranslatableInterface
{
    public function getId(): ?int;

    public function getTerms(): ?TermsInterface;

    public function setTerms(?TermsInterface $terms): void;

    /**
     * Counted per Terms, starting at 1
     */
    public function getNumber(): ?int;

    public function setNumber(int $number): void;

    /**
     * The moment the version was saved. It is in force from then until the next version's moment
     */
    public function getInForceFrom(): ?\DateTimeImmutable;

    public function setInForceFrom(\DateTimeImmutable $inForceFrom): void;
}
