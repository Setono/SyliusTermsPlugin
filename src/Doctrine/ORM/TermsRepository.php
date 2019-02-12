<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Doctrine\ORM;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    public function findByChannelAndLocale(ChannelInterface $channel, string $locale): array
    {
        $qb = $this->createQueryBuilder('o');
        $qb
            ->join('o.locale', 'l')
            ->andWhere('l.code = :locale')
            ->andWhere('o.channel = :channel')
            ->setParameters([
                'locale' => $locale,
                'channel' => $channel,
            ])
        ;

        return $qb->getQuery()->getResult();
    }
}
