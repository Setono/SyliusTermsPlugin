<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Resource\Model\AbstractTranslation;

class TermsTranslation extends AbstractTranslation implements TermsTranslationInterface
{
    /** @var mixed */
    protected $id;

    /** @var string|null */
    protected $name;

    /** @var string|null */
    protected $slug;

    /** @var string|null */
    protected $explanation;

    /** @var string|null */
    protected $content;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    /**
     * @return mixed
     */
    public function getId()
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

    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    public function setExplanation(?string $explanation): void
    {
        $this->explanation = $explanation;
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
