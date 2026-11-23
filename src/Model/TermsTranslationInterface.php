<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\SlugAwareInterface;
use Sylius\Resource\Model\TranslationInterface;

interface TermsTranslationInterface extends SlugAwareInterface, ResourceInterface, TranslationInterface
{
    public function getId(): ?int;

    public function getName(): ?string;

    public function setName(?string $name): void;

    public function getLabel(): ?string;

    public function setLabel(?string $label): void;

    public function getContent(): ?string;

    /**
     * Stores the Content the same way, whoever sets it: the admin form, the fixtures or app code. CRLF and CR become LF.
     * Then the whitespace Markdown ignores is dropped: the blank lines before the first non-blank line, with their line
     * breaks, and all whitespace at the end. A blank line holds nothing, or only spaces and tabs. The first non-blank
     * line keeps its leading spaces, since four of them start an indented code block, and everything up to the last
     * non-blank character is stored as given. Content that is only whitespace is stored as null
     */
    public function setContent(?string $content): void;
}
