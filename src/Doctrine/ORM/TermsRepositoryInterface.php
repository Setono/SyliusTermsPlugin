<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
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
     * @return array|TermsInterface[]
     */
    public function findByChannel(ChannelInterface $channel): array;

    /**
     * @param ChannelInterface $channel
     * @param string $slug
     * @return TermsInterface|null
     */
    public function findOneByChannelAndSlug(ChannelInterface $channel, string $slug): ?TermsInterface;
}
