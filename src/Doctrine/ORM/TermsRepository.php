<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

use Doctrine\ORM\QueryBuilder;
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
    public function findByChannel(ChannelInterface $channel, ?string $locale = null): array
    {
        $queryBuilder = $this->createListQueryBuilder()
            ->andWhere('o.channel = :channel')
            ->setParameter('channel', $channel)
        ;

        if (null !== $locale) {
            $queryBuilder
                ->andWhere('translation.locale = :locale')
                ->setParameter('locale', $locale)
            ;
        }

        return $queryBuilder
            ->getQuery()
            ->getResult()
        ;
    }
}
