<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Channel\Model\ChannelInterface;
use Webmozart\Assert\Assert;

class TermsRepository extends EntityRepository implements TermsRepositoryInterface
{
    public function createListQueryBuilder(string $locale): QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->addSelect('translation')
            ->leftJoin('o.translations', 'translation', Join::WITH, 'translation.locale = :locale')
            ->setParameter('locale', $locale)
        ;
    }

    public function findByFormAndChannel(string $form, ChannelInterface $channel): array
    {
        $termsList = $this->createQueryBuilder('o')
            ->andWhere('o.enabled = true')
            ->andWhere('o.forms IS NOT NULL')
            ->andWhere(':channel MEMBER OF o.channels')
            ->setParameter('channel', $channel)
            ->addOrderBy('o.position', 'ASC')
            // Upgraded 2.x Terms share position 0 until the upgrade command numbers them
            ->addOrderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        Assert::isArray($termsList);
        Assert::allIsInstanceOf($termsList, TermsInterface::class);

        // The Form codes are matched here rather than in SQL, where they are a JSON list that no portable DQL can search
        return array_values(array_filter(
            $termsList,
            static fn (TermsInterface $terms): bool => in_array($form, $terms->getForms(), true),
        ));
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
