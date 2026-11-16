<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Webmozart\Assert\Assert;

class TermsVersionRepository extends EntityRepository implements TermsVersionRepositoryInterface
{
    public function findOneInForce(TermsInterface $terms, \DateTimeInterface $at, string $localeCode): ?TermsVersionTranslationInterface
    {
        // Selects the translation rather than the version, so the version's translations collection is never
        // hydrated with only the one locale
        $translation = $this->getEntityManager()->createQueryBuilder()
            ->select('translation', 'version')
            ->from($this->getClassMetadata()->getAssociationTargetClass('translations'), 'translation')
            ->innerJoin('translation.translatable', 'version')
            ->andWhere('version.terms = :terms')
            ->andWhere('translation.locale = :locale')
            ->andWhere(sprintf(
                'version.number = (SELECT MAX(inForce.number) FROM %s inForce WHERE inForce.terms = :terms AND inForce.inForceFrom <= :at)',
                $this->getEntityName(),
            ))
            ->setParameter('terms', $terms)
            ->setParameter('locale', $localeCode)
            ->setParameter('at', $at)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        Assert::nullOrIsInstanceOf($translation, TermsVersionTranslationInterface::class);

        return $translation;
    }
}
