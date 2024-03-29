<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Webmozart\Assert\Assert;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    public function findByFormAndChannelAndLocale(string $form, ChannelInterface $channel, string $locale): array
    {
        // See https://dev.mysql.com/doc/refman/8.0/en/string-comparison-functions.html
        // The manual says: To search for \, specify it as \\\\
        // That is why below we replace \ with \\\\
        $form = str_replace('\\', '\\\\\\\\', $form);

        $objs = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere('o.enabled = true')
            ->andWhere('o.forms IS NOT NULL')
            ->andWhere('o.forms LIKE :form')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('locale', $locale)
            ->setParameter('form', '%"' . $form . '"%')
            ->setParameter('channel', $channel)
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($objs);
        Assert::allIsInstanceOf($objs, TermsInterface::class);

        return $objs;
    }

    public function findOneByChannelAndLocaleAndSlug(ChannelInterface $channel, string $locale, string $slug): ?TermsInterface
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

    public function findOneByChannelAndLocaleAndCode(
        ChannelInterface $channel,
        string $locale,
        string $code,
    ): ?TermsInterface {
        $obj = $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->innerJoin('o.translations', 'translation', 'WITH', 'translation.locale = :locale')
            ->andWhere(':channel MEMBER OF o.channels')
            ->andWhere('o.code = :code')
            ->andWhere('o.enabled = true')
            ->setParameter('locale', $locale)
            ->setParameter('channel', $channel)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($obj, TermsInterface::class);

        return $obj;
    }
}
