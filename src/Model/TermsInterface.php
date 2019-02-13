<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Channel\Model\ChannelAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface TermsInterface extends ResourceInterface, CodeAwareInterface, ChannelAwareInterface, TranslatableInterface, TimestampableInterface
{
    /**
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * @param string|null $code
     */
    public function setCode(?string $code): void;

    /**
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * @param string $name
     */
    public function setName(string $name): void;

    /**
     * @return string|null
     */
    public function getSlug(): ?string;

    /**
     * @param string|null $slug
     */
    public function setSlug(?string $slug): void;

    /**
     * @return string|null
     */
    public function getExplanation(): ?string;

    /**
     * @param string $explanation
     */
    public function setExplanation(string $explanation): void;

    /**
     * @return string|null
     */
    public function getContent(): ?string;

    /**
     * @param string $content
     */
    public function setContent(string $content): void;

    /**
     * @param string|null $locale
     * @return TermsTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface;
}
