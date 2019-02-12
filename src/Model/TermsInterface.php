<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Model;

use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Model\ResourceInterface;
use Sylius\Component\Resource\Model\TimestampableInterface;

interface TermsInterface extends ResourceInterface, TimestampableInterface
{
    /**
     * Returns the channel where these terms are active
     *
     * @return ChannelInterface|null
     */
    public function getChannel(): ?ChannelInterface;

    /**
     * @param ChannelInterface $channel
     */
    public function setChannel(ChannelInterface $channel): void;

    /**
     * Returns the locale where these terms are active
     *
     * @return LocaleInterface|null
     */
    public function getLocale(): ?LocaleInterface;

    /**
     * @param LocaleInterface $locale
     */
    public function setLocale(LocaleInterface $locale): void;

    /**
     * @return string|null
     */
    public function getCode(): ?string;

    /**
     * @param string $code
     */
    public function setCode(string $code): void;

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
    public function getContent(): ?string;

    /**
     * @param string $content
     */
    public function setContent(string $content): void;
}
