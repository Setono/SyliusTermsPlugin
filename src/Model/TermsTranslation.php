<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Resource\Model\AbstractTranslation;

class TermsTranslation extends AbstractTranslation implements TermsTranslationInterface
{
    /**
     * @var mixed
     */
    protected $id;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $slug;

    /**
     * @var string|null
     */
    protected $explanation;

    /**
     * @var string|null
     */
    protected $content;

    public function __toString(): string
    {
        return (string) $this->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * {@inheritdoc}
     */
    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * {@inheritdoc}
     */
    public function setSlug(?string $slug): void
    {
        $this->slug = $slug;
    }

    /**
     * @inheritdoc
     */
    public function getExplanation(): ?string
    {
        return $this->explanation;
    }

    /**
     * @inheritdoc
     */
    public function setExplanation(?string $explanation): void
    {
        $this->explanation = $explanation;
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * {@inheritdoc}
     */
    public function setContent(?string $content): void
    {
        $this->content = $content;
    }
}
