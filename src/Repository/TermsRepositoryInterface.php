<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface TermsRepositoryInterface extends RepositoryInterface
{
    /**
     * @param ChannelInterface $channel
     * @param string $locale
     *
     * @return array
     */
    public function findByChannelAndLocale(ChannelInterface $channel, string $locale): array;
}
