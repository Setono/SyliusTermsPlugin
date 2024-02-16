<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\SlugAwareInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface TermsTranslationInterface extends SlugAwareInterface, ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getLabel(): ?string;

    public function setLabel(?string $label): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;
}
