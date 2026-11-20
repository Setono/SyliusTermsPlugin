<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Stringable;
use Sylius\Resource\Model\AbstractTranslation;

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

    /**
     * Before 6.4.31 and 7.4.3, symfony/form keeps the CRLF line breaks browsers submit a textarea with
     */
    public function setContent(?string $content): void
    {
        $this->content = null === $content ? null : str_replace(["\r\n", "\r"], "\n", $content);
    }
}
