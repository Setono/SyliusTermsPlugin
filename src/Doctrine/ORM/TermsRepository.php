<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

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

    public function findEnabledByChannel(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.enabled = :enabled')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->addOrderBy('o.position', 'asc')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findEnabledByChannelForCompleteForm(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.enabled = :enabled')
            ->andWhere('o.displayInCheckout = :displayInCheckout')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->setParameter('displayInCheckout', true)
            ->addOrderBy('o.position', 'asc')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findEnabledByChannelForCustomerRegistrationForm(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.enabled = :enabled')
            ->andWhere('o.displayInCustomerSignup = :displayInCustomerSignup')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->setParameter('displayInCustomerSignup', true)
            ->addOrderBy('o.position', 'asc')
            ->getQuery()
            ->getResult()
            ;
    }

    public function findEnabledByChannelForFooterTemplate(ChannelInterface $channel): array
    {
        return $this->createListQueryBuilder()
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.enabled = :enabled')
            ->andWhere('o.displayInFooter = :displayInFooter')
            ->setParameter('channel', $channel)
            ->setParameter('enabled', true)
            ->setParameter('displayInFooter', true)
            ->addOrderBy('o.position', 'asc')
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
