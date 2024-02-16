<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Stringable;
use Sylius\Component\Resource\Model\AbstractTranslation;

class TermsTranslation extends AbstractTranslation implements TermsTranslationInterface, Stringable
{
    protected ?int $id = null;

    protected ?string $name = null;

    protected ?string $slug = null;

    protected ?string $label = null;

    protected ?string $content = null;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}
