<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Resource\Model\AbstractTranslation;
use Webmozart\Assert\Assert;

class TermsVersionTranslation extends AbstractTranslation implements TermsVersionTranslationInterface
{
    protected ?int $id = null;

    protected ?string $name = null;

    protected ?string $label = null;

    protected ?string $content = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): TermsVersionInterface
    {
        $version = $this->getTranslatable();
        Assert::isInstanceOf($version, TermsVersionInterface::class);

        return $version;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
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
        $this->content = ContentNormalizer::normalize($content);
    }
}
