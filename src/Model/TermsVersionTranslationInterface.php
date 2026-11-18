<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * A Terms version's name, label and content in one locale, stored as entered: Content is the source text, never
 * rendered HTML
 */
interface TermsVersionTranslationInterface extends ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getVersion(): TermsVersionInterface;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getLabel(): ?string;

    public function setLabel(?string $label): void;

    public function getContent(): ?string;

    public function setContent(?string $content): void;
}
