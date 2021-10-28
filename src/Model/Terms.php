<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\ToggleableTrait;
use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class Terms implements TermsInterface
{
    use ToggleableTrait;
    use TimestampableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    public function __construct()
    {
        $this->channels = new ArrayCollection();
        $this->initializeTranslationsCollection();
    }

    /** @var int */
    protected $id;

    /** @var Collection|ChannelInterface[] */
    protected $channels;

    /** @var string|null */
    protected $code;

    /** @var int|null */
    private $position;

    /** @var bool */
    private $displayInFooter = false;

    /** @var bool */
    private $displayInCheckout = true;

    /** @var bool */
    private $displayInCustomerSignup = false;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function isDisplayInFooter(): bool
    {
        return $this->displayInFooter;
    }
    public function setDisplayInFooter(bool $displayInFooter): void
    {
        $this->displayInFooter = $displayInFooter;
    }

    public function isDisplayInCheckout(): bool
    {
        return $this->displayInCheckout;
    }

    public function setDisplayInCheckout(bool $displayInCheckout): void
    {
        $this->displayInCheckout = $displayInCheckout;
    }

    public function isDisplayInCustomerSignup(): bool
    {
        return $this->displayInCustomerSignup;
    }

    public function setDisplayInCustomerSignup(bool $displayInCustomerSignup): void
    {
        $this->displayInCustomerSignup = $displayInCustomerSignup;
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

    public function getExplanation(): ?string
    {
        return $this->getTranslation()->getExplanation();
    }

    public function setExplanation(?string $explanation): void
    {
        $this->getTranslation()->setExplanation($explanation);
    }

    public function getContent(): ?string
    {
        return $this->getTranslation()->getContent();
    }

    public function setContent(?string $content): void
    {
        $this->getTranslation()->setContent($content);
    }

    /**
     * @return TermsTranslationInterface
     */
    public function getTranslation(?string $locale = null): TranslationInterface
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
