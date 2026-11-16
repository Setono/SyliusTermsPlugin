<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeCheckerInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;

/**
 * Creates a Terms version whenever a flush saves a Terms whose versioned text changed. Listening to Doctrine rather
 * than to the admin means every save creates the version: the admin, the fixture and app code alike. The version is
 * written in the same transaction as the Terms
 */
final readonly class CreateTermsVersionListener
{
    public function __construct(
        private VersionedTextChangeCheckerInterface $versionedTextChangeChecker,
        private TermsVersionSnapshotterInterface $termsVersionSnapshotter,
    ) {
    }

    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $entityManager = $eventArgs->getObjectManager();
        $unitOfWork = $entityManager->getUnitOfWork();

        foreach (self::getSavedTerms($unitOfWork) as $terms) {
            if (!$this->versionedTextChangeChecker->hasChanged($terms)) {
                continue;
            }

            $version = $this->termsVersionSnapshotter->snapshot($terms);

            // Entities persisted during onFlush need their change sets computed by hand
            $entityManager->persist($version);
            $unitOfWork->computeChangeSet($entityManager->getClassMetadata($version::class), $version);

            foreach ($version->getTranslations() as $translation) {
                $unitOfWork->computeChangeSet($entityManager->getClassMetadata($translation::class), $translation);
            }
        }
    }

    /**
     * Returns the Terms this flush inserts or updates, directly or through their translations, and leaves out the ones
     * it deletes
     *
     * @return list<TermsInterface>
     */
    private static function getSavedTerms(UnitOfWork $unitOfWork): array
    {
        $savedTerms = [];

        foreach ([...$unitOfWork->getScheduledEntityInsertions(), ...$unitOfWork->getScheduledEntityUpdates()] as $entity) {
            if ($entity instanceof TermsTranslationInterface) {
                $entity = $entity->getTranslatable();
            }

            if ($entity instanceof TermsInterface) {
                $savedTerms[spl_object_id($entity)] = $entity;
            }
        }

        // A removed translation is gone from the Terms' translations, so its Terms is found through the changed collection
        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();

            if ($owner instanceof TermsInterface) {
                $savedTerms[spl_object_id($owner)] = $owner;
            }
        }

        return array_values(array_filter(
            $savedTerms,
            static fn (TermsInterface $terms): bool => !$unitOfWork->isScheduledForDelete($terms),
        ));
    }
}
