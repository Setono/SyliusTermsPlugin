<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface TermsRepositoryInterface extends RepositoryInterface
{
    /**
     * @return array<array-key, TermsInterface>
     */
    public function findByClassAndChannelAndLocale(string $class, ChannelInterface $channel, string $locale): array;

    public function findOneByChannelAndLocaleAndSlug(ChannelInterface $channel, string $locale, string $slug): ?TermsInterface;

    public function findOneByChannelAndLocaleAndCode(ChannelInterface $channel, string $locale, string $code): ?TermsInterface;
}
