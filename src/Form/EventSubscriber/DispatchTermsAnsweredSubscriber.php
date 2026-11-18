<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\EventSubscriber;

use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcherInterface;
use Setono\SyliusTermsPlugin\Event\Answer;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Hands the Answers of a valid submit of a Form to the TermsAnsweredEvent dispatcher. FormTypeExtension adds it to the
 * form of each Form that has Terms, with what the form was built with
 */
final readonly class DispatchTermsAnsweredSubscriber implements EventSubscriberInterface
{
    /**
     * Runs after Symfony's ValidationListener, at 0, has validated the root form, and after the listeners that add
     * their own errors once it has
     */
    public const PRIORITY = -1024;

    /**
     * @param string $form the code of the Form
     * @param list<TermsVersionTranslationInterface> $terms the Terms shown, as the Form lookup returned them
     */
    public function __construct(
        private TermsAnsweredDispatcherInterface $termsAnsweredDispatcher,
        private string $form,
        private ChannelInterface $channel,
        private string $localeCode,
        private array $terms,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::POST_SUBMIT => ['dispatch', self::PRIORITY],
        ];
    }

    public function dispatch(FormEvent $event): void
    {
        $form = $event->getForm();

        // Only the root form is validated, so a Form nested in another form can't tell a valid submit here
        if (!$form->isRoot() || !$form->isValid() || !$form->has('terms')) {
            return;
        }

        /** @var list<string> $tickedCodes */
        $tickedCodes = $form->get('terms')->getData() ?? [];

        $answers = [];
        foreach ($this->terms as $translation) {
            $version = $translation->getVersion();

            $answers[] = new Answer($version, in_array($version->getTerms()?->getCode(), $tickedCodes, true));
        }

        $this->termsAnsweredDispatcher->dispatch(new TermsAnsweredEvent(
            $this->form,
            $form->getData(),
            $this->channel,
            $this->localeCode,
            $answers,
        ));
    }
}
