<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelsAwareInterface;
use Sylius\Resource\Model\CodeAwareInterface;
use Sylius\Resource\Model\ResourceInterface;
use Sylius\Resource\Model\TimestampableInterface;
use Sylius\Resource\Model\ToggleableInterface;
use Sylius\Resource\Model\TranslatableInterface;

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
     * Whether the Terms must be ticked before a Form it is placed on can be submitted. Defaults to true. The flag holds
     * on every Form: a Terms needed as both required and optional becomes two Terms
     */
    public function isRequired(): bool;

    public function setRequired(bool $required): void;

    /**
     * The Terms' place among the Terms on a Form, lowest first. One position is used on every Form. Until the Terms
     * is saved, it is null on a new Terms, and -1 after setting a null position
     */
    public function getPosition(): ?int;

    /**
     * Setting a position moves the Terms there when it is saved, and shifts the Terms from there on one place down.
     * A null position puts the Terms last, whether it is new or already saved
     */
    public function setPosition(?int $position): void;

    /**
     * @return list<string> the codes of the Forms the Terms is placed on
     */
    public function getForms(): array;

    /**
     * @param list<string> $forms Form codes
     */
    public function setForms(array $forms): void;

    /**
     * @param string $form a Form code
     */
    public function addForm(string $form): void;

    public function removeForm(string $form): void;

    /**
     * @return Collection<array-key, TermsVersionInterface>
     */
    public function getVersions(): Collection;

    /**
     * Use TermsVersionSnapshotterInterface to create a version rather than calling this directly. There is no way to
     * remove a version: versions are deleted with their Terms
     */
    public function addVersion(TermsVersionInterface $version): void;

    /**
     * The version with the highest number, or null if the Terms has no version yet
     */
    public function getLatestVersion(): ?TermsVersionInterface;

    public function getTranslation(?string $locale = null): TermsTranslationInterface;
}
