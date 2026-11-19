<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Clock\ClockInterface;
use Setono\Doctrine\ORMTrait;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;
use Webmozart\Assert\Assert;

final class Upgrader implements UpgraderInterface
{
    use ORMTrait;

    /**
     * @param class-string<TermsInterface> $termsClass
     */
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly string $termsClass,
        private readonly FormClassMapper $formClassMapper,
        private readonly ContentCaseDetector $contentCaseDetector,
        private readonly TermsVersionSnapshotterInterface $termsVersionSnapshotter,
        private readonly ClockInterface $clock,
    ) {
        $this->managerRegistry = $managerRegistry;
    }

    public function plan(): UpgradePlan
    {
        $termsList = $this->getRepository($this->termsClass)->findBy([], ['id' => 'ASC']);
        Assert::allIsInstanceOf($termsList, TermsInterface::class);

        // The Terms that have a version keep their positions. The Terms from 2.x, which all have the position the schema
        // migration gave them, follow them in id order, the order 2.x showed them in
        $position = 0;
        foreach ($termsList as $terms) {
            if (!$terms->getVersions()->isEmpty()) {
                $position = max($position, (int) $terms->getPosition() + 1);
            }
        }

        $termsUpgrades = [];

        foreach ($termsList as $terms) {
            $isFrom2x = $terms->getVersions()->isEmpty();

            $mappedForms = [];
            foreach ($terms->getForms() as $form) {
                $code = $this->formClassMapper->mapOne($form);
                if ($code !== $form) {
                    $mappedForms[$form] = $code;
                }
            }

            $forms = $this->formClassMapper->map($terms->getForms());

            $termsUpgrades[] = new TermsUpgrade(
                $terms,
                $isFrom2x ? $position++ : null,
                $mappedForms,
                $forms,
                array_values(array_filter($forms, fn (string $form): bool => !$this->formClassMapper->isConfigured($form))),
                $isFrom2x ? $this->detectContentCases($terms) : [],
            );
        }

        return new UpgradePlan($this->clock->now(), $termsUpgrades);
    }

    public function apply(UpgradePlan $plan): void
    {
        $this->getManager($this->termsClass)->wrapInTransaction(function (EntityManagerInterface $manager) use ($plan): void {
            foreach ($plan->termsUpgrades as $termsUpgrade) {
                $terms = $termsUpgrade->terms;

                if ($termsUpgrade->isFrom2x()) {
                    // Every version 1 is in force from the moment the plan read from the clock
                    $this->termsVersionSnapshotter->snapshot($terms)->setInForceFrom($plan->inForceFrom);
                }

                if ([] !== $termsUpgrade->mappedForms) {
                    $terms->setForms($termsUpgrade->forms);
                }
            }

            $manager->flush();

            // Gedmo's sortable listener moves the Terms around a changed position, which goes wrong while the Terms from
            // 2.x share one. A DQL update bypasses it
            foreach ($plan->getTermsUpgradesFrom2x() as $termsUpgrade) {
                $manager->createQueryBuilder()
                    ->update($this->termsClass, 'o')
                    ->set('o.position', ':position')
                    ->andWhere('o.id = :id')
                    ->setParameter('position', $termsUpgrade->position)
                    ->setParameter('id', $termsUpgrade->terms->getId())
                    ->getQuery()
                    ->execute()
                ;

                // The update bypasses the entity too, which still has the old position
                $manager->refresh($termsUpgrade->terms);
            }
        });
    }

    /**
     * @return array<string, non-empty-list<ContentCase>>
     */
    private function detectContentCases(TermsInterface $terms): array
    {
        $contentCases = [];

        foreach ($terms->getTranslations() as $translation) {
            Assert::isInstanceOf($translation, TermsTranslationInterface::class);

            $cases = $this->contentCaseDetector->detect((string) $translation->getContent());
            if ([] !== $cases) {
                $contentCases[(string) $translation->getLocale()] = $cases;
            }
        }

        ksort($contentCases);

        return $contentCases;
    }
}
