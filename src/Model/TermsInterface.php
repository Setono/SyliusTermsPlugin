<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;

interface TermsInterface extends ResourceInterface, CodeAwareInterface, ChannelsAwareInterface, TranslatableInterface, TimestampableInterface, ToggleableInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getLabel(): ?string;

    public function setLabel(string $label): void;

    public function getContent(): ?string;

    public function setContent(string $content): void;

    /**
     * @return list<class-string>
     */
    public function getForms(): array;

    /**
     * @param class-string $form
     */
    public function addForm(string $form): void;

    public function removeForm(string $form): void;

    public function getTranslation(?string $locale = null): TermsTranslationInterface;
}
