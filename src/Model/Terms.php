<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Resource\Model\TranslatableTrait;

class Terms implements TermsInterface
{
    use TimestampableTrait;
    use ToggleableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    protected ?int $id = null;

    protected ?string $code = null;

    /** @var Collection<array-key, ChannelInterface> */
    protected Collection $channels;

    protected bool $required = true;

    protected ?int $position = null;

    /** @var list<string>|null */
    protected ?array $forms = [];

    /** @var Collection<array-key, TermsVersionInterface> */
    protected Collection $versions;

    public function __construct()
    {
        $this->channels = new ArrayCollection();
        $this->versions = new ArrayCollection();

        $this->initializeTranslationsCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getName(): ?string
    {
        return $this->getTranslation()->getName();
    }

    public function setName(?string $name): void
    {
        $this->getTranslation()->setName($name);
    }

    public function getSlug(): ?string
    {
        return $this->getTranslation()->getSlug();
    }

    public function setSlug(?string $slug): void
    {
        $this->getTranslation()->setSlug($slug);
    }

    public function getLabel(): ?string
    {
        return $this->getTranslation()->getLabel();
    }

    public function setLabel(?string $label): void
    {
        $this->getTranslation()->setLabel($label);
    }

    public function getContent(): ?string
    {
        return $this->getTranslation()->getContent();
    }

    public function setContent(?string $content): void
    {
        $this->getTranslation()->setContent($content);
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        // Gedmo's sortable listener appends a new Terms whose position is null, but keeps a null position on an
        // update, which the NOT NULL column then rejects. -1 means "last" to Gedmo in both cases
        $this->position = $position ?? -1;
    }

    public function getChannels(): Collection
    {
        return $this->channels;
    }

    public function hasChannel(ChannelInterface $channel): bool
    {
        return $this->channels->contains($channel);
    }

    public function addChannel(ChannelInterface $channel): void
    {
        if (!$this->hasChannel($channel)) {
            $this->channels->add($channel);
        }
    }

    public function removeChannel(ChannelInterface $channel): void
    {
        if ($this->hasChannel($channel)) {
            $this->channels->removeElement($channel);
        }
    }

    public function getForms(): array
    {
        return $this->forms ?? [];
    }

    public function setForms(array $forms): void
    {
        $this->forms = null;

        foreach ($forms as $form) {
            $this->addForm($form);
        }
    }

    public function addForm(string $form): void
    {
        $this->forms ??= [];

        if (in_array($form, $this->forms, true)) {
            return;
        }

        $this->forms[] = $form;
    }

    public function removeForm(string $form): void
    {
        if (null === $this->forms) {
            return;
        }

        $forms = array_values(array_filter($this->forms, static fn (string $f) => $f !== $form));

        $this->forms = [] === $forms ? null : $forms;
    }

    public function getVersions(): Collection
    {
        return $this->versions;
    }

    public function addVersion(TermsVersionInterface $version): void
    {
        $version->setTerms($this);
        $this->versions->add($version);
    }

    public function getLatestVersion(): ?TermsVersionInterface
    {
        $latestVersion = null;

        foreach ($this->versions as $version) {
            if (null === $latestVersion || $version->getNumber() > $latestVersion->getNumber()) {
                $latestVersion = $version;
            }
        }

        return $latestVersion;
    }

    public function getTranslation(?string $locale = null): TermsTranslationInterface
    {
        /** @var TermsTranslationInterface $translation */
        $translation = $this->doGetTranslation($locale);

        return $translation;
    }

    protected function createTranslation(): TermsTranslationInterface
    {
        return new TermsTranslation();
    }
}
