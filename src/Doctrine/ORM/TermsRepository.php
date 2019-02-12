<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.translations', 'translation')
            ;
    }

    /**
     * {@inheritdoc}
     */
    public function findByChannel(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere('o.channel = :channel')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function findOneByChannelAndSlug(ChannelInterface $channel, string $slug): ?TermsInterface
    {
        return $this->createListQueryBuilder()
            ->andWhere('translation.slug = :slug')
            ->andWhere('o.channel = :channel')
            ->setParameter('channel', $channel)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

}
