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
     * Stores the Content with LF line breaks, whoever sets it: the admin form, the fixtures or app code. CRLF and CR
     * become LF
     */
    public function setContent(?string $content): void;
}
