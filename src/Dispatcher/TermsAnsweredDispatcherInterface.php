<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Dispatcher;

use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;

/**
 * Dispatches the Answers of a Form once the Form's work is saved (ADR 0004). It reads no request and no Symfony form,
 * so an API layer can hand it the Answers it takes
 */
interface TermsAnsweredDispatcherInterface
{
    /**
     * For a Form with a saved_event, the event waits until that event is dispatched in the same request, and is
     * dropped if it isn't, such as when a listener stops the save before it happens. A Form without a saved_event, or
     * one that isn't configured, gets the event dispatched right away
     */
    public function dispatch(TermsAnsweredEvent $event): void;
}
