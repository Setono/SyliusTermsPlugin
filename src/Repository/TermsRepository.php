<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Webmozart\Assert\Assert;

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
        $objs = $this->createListQueryBuilder()
            ->andWhere('o.enabled = true')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, TermsInterface::class);

        return $objs;
    }

    public function findOneByChannelAndSlug(ChannelInterface $channel, string $slug): ?TermsInterface
    {
        $obj = $this->createListQueryBuilder()
            ->andWhere('o.enabled = true')
            ->andWhere('translation.slug = :slug')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('channel', $channel)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, TermsInterface::class);

        return $obj;
    }
}
