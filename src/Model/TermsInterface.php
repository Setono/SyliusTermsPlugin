<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Component\Resource\Model\CodeAwareInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;
use Sylius\Component\Resource\Model\ToggleableInterface;
use Sylius\Component\Resource\Model\TranslatableInterface;
use Sylius\Component\Resource\Model\TranslationInterface;

interface TermsInterface extends
    ResourceInterface,
    CodeAwareInterface,
    ChannelsAwareInterface,
    TranslatableInterface,
    TimestampableInterface,
    ToggleableInterface
{
    public function getPosition(): ?int;

    public function setPosition(?int $position): void;

    public function isDisplayInFooter(): bool;

    public function setDisplayInFooter(bool $displayInFooter): void;

    public function isDisplayInCheckout(): bool;

    public function setDisplayInCheckout(bool $displayInCheckout): void;

    public function isDisplayInCustomerSignup(): bool;

    public function setDisplayInCustomerSignup(bool $displayInCustomerSignup): void;

    public function getName(): ?string;

    public function setName(string $name): void;

    public function getSlug(): ?string;

    public function setSlug(?string $slug): void;

    public function getExplanation(): ?string;

    public function setExplanation(string $explanation): void;

    public function getContent(): ?string;

    public function setContent(string $content): void;

    /**
     * @return TermsTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface;
}
