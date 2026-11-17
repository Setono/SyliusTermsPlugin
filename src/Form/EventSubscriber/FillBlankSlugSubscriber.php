<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\EventSubscriber;

use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Fills a blank slug from the name of the same translation when the Terms form is submitted, before it is validated.
 * It fills the translation, not the submitted values, so the Live form's re-renders leave the slug field as it is
 */
final readonly class FillBlankSlugSubscriber implements EventSubscriberInterface
{
    public function __construct(private SlugGeneratorInterface $slugGenerator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::SUBMIT => 'fill',
        ];
    }

    public function fill(FormEvent $event): void
    {
        $translation = $event->getData();
        if (!$translation instanceof TermsTranslationInterface) {
            return;
        }

        $name = $translation->getName();
        $slug = $translation->getSlug();

        if (null === $name || '' === trim($name) || (null !== $slug && '' !== trim($slug))) {
            return;
        }

        // The form of a translation is named after its locale, see ResourceTranslationsType. The translation's own
        // locale is set later, once the translations form is submitted
        $translation->setSlug($this->slugGenerator->generate($name, $event->getForm()->getName()));
    }
}
