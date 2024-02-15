<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.translations', 'translation')
        ;
    }

    public function findByChannel(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByChannelAndSlug(ChannelInterface $channel, string $slug): ?TermsInterface
    {
        return $this->createListQueryBuilder()
            ->andWhere('translation.slug = :slug')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('channel', $channel)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
