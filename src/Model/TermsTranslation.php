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
     * Stores the Content with LF line breaks, whoever sets it: the admin form, the fixtures or app code. Browsers submit
     * a textarea's line breaks as CRLF, which symfony/form keeps before 6.4.31 and 7.4.3, and the check for a new Terms
     * version compares Content byte for byte, so a CRLF save of unchanged Content would add a version
     */
    public function setContent(?string $content): void
    {
        $this->content = null === $content ? null : str_replace(["\r\n", "\r"], "\n", $content);
    }
}
