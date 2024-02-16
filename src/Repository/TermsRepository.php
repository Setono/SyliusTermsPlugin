<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Webmozart\Assert\Assert;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    public function findByClassAndChannelAndLocale(string $class, ChannelInterface $channel, string $locale): array
    {
        // See https://dev.mysql.com/doc/refman/8.0/en/string-comparison-functions.html
        // The manual says: To search for \, specify it as \\\\
        // That is why below we replace \ with \\\\
        $class = str_replace('\\', '\\\\\\\\', $class);

        $objs = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('o.enabled = true')
            ->andWhere('o.forms IS NOT NULL')
            ->andWhere('o.forms LIKE :class')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('locale', $locale)
            ->setParameter('class', '%"' . $class . '"%')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, TermsInterface::class);

        return $objs;
    }

    public function findOneByChannelAndSlug(ChannelInterface $channel, string $locale, string $slug): ?TermsInterface
    {
        $obj = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('translation.slug = :slug')
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.enabled = true')
            ->setParameter('channel', $channel)
            ->setParameter('locale', $locale)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, TermsInterface::class);

        return $obj;
    }
}
