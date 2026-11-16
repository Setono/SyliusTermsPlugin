<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class TermsVersion implements TermsVersionInterface
{
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
    }

    protected ?int $id = null;

    protected ?TermsInterface $terms = null;

    protected ?int $number = null;

    protected ?\DateTimeImmutable $inForceFrom = null;

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTerms(): ?TermsInterface
    {
        return $this->terms;
    }

    public function setTerms(?TermsInterface $terms): void
    {
        $this->terms = $terms;
    }

    public function getNumber(): ?int
    {
        return $this->number;
    }

    public function setNumber(int $number): void
    {
        $this->number = $number;
    }

    public function getInForceFrom(): ?\DateTimeImmutable
    {
        return $this->inForceFrom;
    }

    public function setInForceFrom(\DateTimeImmutable $inForceFrom): void
    {
        $this->inForceFrom = $inForceFrom;
    }

    protected function createTranslation(): TranslationInterface
    {
        return new TermsVersionTranslation();
    }
}
