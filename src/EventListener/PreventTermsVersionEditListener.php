<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\EventListener;

use Doctrine\ORM\Event\OnFlushEventArgs;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;

/**
 * Refuses a flush that changes a saved Terms version: its number, the moment it is in force from, a locale's name,
 * label or content, or which locales it holds. A version records what customers accepted, so a change to the Terms
 * creates the next version instead
 */
final class PreventTermsVersionEditListener
{
    public function onFlush(OnFlushEventArgs $eventArgs): void
    {
        $unitOfWork = $eventArgs->getObjectManager()->getUnitOfWork();

        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof TermsVersionInterface || $entity instanceof TermsVersionTranslationInterface) {
                throw self::createException();
            }
        }

        // A translation added to or removed from a saved version changes the version's translations collection
        foreach ($unitOfWork->getScheduledCollectionUpdates() as $collection) {
            $owner = $collection->getOwner();

            if ($owner instanceof TermsVersionInterface && !$unitOfWork->isScheduledForInsert($owner)) {
                throw self::createException();
            }
        }
    }

    private static function createException(): \LogicException
    {
        return new \LogicException('A saved Terms version can\'t be edited. Change the Terms instead: saving it creates its next version.');
    }
}
