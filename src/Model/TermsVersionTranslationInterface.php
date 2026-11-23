<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TranslationInterface;

/**
 * A Terms version's name, label and content in one locale, stored as entered, except for the Content's line breaks and
 * the whitespace Markdown ignores (see setContent()). Content is the source text, never rendered HTML
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

    /**
     * Stores the Content like TermsTranslationInterface::setContent(): with LF line breaks, without the whitespace
     * Markdown ignores, and as null when it is only whitespace
     */
    public function setContent(?string $content): void;
}
