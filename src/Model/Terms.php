<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Model\TimestampableTrait;
use Sylius\Component\Resource\Model\TranslatableTrait;
use Sylius\Component\Resource\Model\TranslationInterface;

class Terms implements TermsInterface
{
    use TimestampableTrait;
    use TranslatableTrait {
        __construct as private initializeTranslationsCollection;
        getTranslation as private doGetTranslation;
    }

    public function __construct()
    {
        $this->initializeTranslationsCollection();
    }

    /**
     * @var int
     */
    protected $id;

    /**
     * @var ChannelInterface
     */
    protected $channel;

    /**
     * @var string
     */
    protected $code;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getChannel(): ?ChannelInterface
    {
        return $this->channel;
    }

    public function setChannel(?ChannelInterface $channel): void
    {
        $this->channel = $channel;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): void
    {
        $this->code = $code;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): ?string
    {
        return $this->getTranslation()->getName();
    }

    /**
     * {@inheritdoc}
     */
    public function setName(?string $name): void
    {
        $this->getTranslation()->setName($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getSlug(): ?string
    {
        return $this->getTranslation()->getSlug();
    }

    /**
     * {@inheritdoc}
     */
    public function setSlug(?string $slug): void
    {
        $this->getTranslation()->setSlug($slug);
    }

    /**
     * {@inheritdoc}
     */
    public function getContent(): ?string
    {
        return $this->getTranslation()->getContent();
    }

    /**
     * {@inheritdoc}
     */
    public function setContent(?string $description): void
    {
        $this->getTranslation()->setContent($description);
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

    /**
     * {@inheritdoc}
     */
    protected function createTranslation(): TermsTranslationInterface
    {
        return new TermsTranslation();
    }
}
