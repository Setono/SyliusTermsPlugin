<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface TermsRepositoryInterface extends RepositoryInterface
{
    /**
     * @return QueryBuilder
     */
    public function createListQueryBuilder(): QueryBuilder;

    /**
     * @param ChannelInterface $channel
     * @param string|null $locale
     * @return array
     */
    public function findByChannel(ChannelInterface $channel, ?string $locale = null): array;
}
