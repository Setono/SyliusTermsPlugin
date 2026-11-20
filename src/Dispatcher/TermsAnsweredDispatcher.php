<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Dispatcher;

use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds the events of the Forms with a saved_event until that event is dispatched. It listens to the saved_event of
 * every enabled Form (see SetonoSyliusTermsExtension), and is reset between requests, which drops the events whose
 * save never came
 */
final class TermsAnsweredDispatcher implements TermsAnsweredDispatcherInterface, ResetInterface
{
    /**
     * The events waiting for their Form's saved_event, by the name of that event
     *
     * @var array<string, list<TermsAnsweredEvent>>
     */
    private array $waiting = [];

    /**
     * @param array<string, array{saved_event: string|null}> $forms the enabled Forms, keyed by code (the setono_sylius_terms.forms parameter)
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly array $forms,
    ) {
    }

    public function dispatch(TermsAnsweredEvent $event): void
    {
        $savedEvent = $this->forms[$event->form]['saved_event'] ?? null;

        if (null === $savedEvent) {
            $this->eventDispatcher->dispatch($event);
        } else {
            $this->waiting[$savedEvent][] = $event;
        }
    }

    /**
     * Listens to the saved_event of each Form that has one, and dispatches the events waiting for it, once
     */
    public function onSaved(object $savedEvent, string $savedEventName): void
    {
        $waiting = $this->waiting[$savedEventName] ?? [];
        unset($this->waiting[$savedEventName]);

        foreach ($waiting as $event) {
            $this->eventDispatcher->dispatch($event);
        }
    }

    public function reset(): void
    {
        $this->waiting = [];
    }
}
