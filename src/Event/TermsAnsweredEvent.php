<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Event;

use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Carries the Answers of one valid submit of a Form, once the Form's work is saved (ADR 0004): after the Form's
 * saved_event, or right after the submit for a Form without one. Listeners that change the data flush their own
 * changes, since Sylius' own flush has already run
 */
final class TermsAnsweredEvent extends Event
{
    /**
     * @param string $form the code of the Form, such as checkout_complete
     * @param mixed $data the form's data: the Order on checkout_complete, the Customer on registration and the array of
     *                    submitted values on contact
     * @param string $localeCode the locale the Terms were shown in
     * @param list<Answer> $answers one per Terms shown, in the order shown
     */
    public function __construct(
        public readonly string $form,
        public readonly mixed $data,
        public readonly ChannelInterface $channel,
        public readonly string $localeCode,
        public readonly array $answers,
    ) {
    }
}
