<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Application\EventListener;

use Doctrine\DBAL\Connection;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Resource\Model\ResourceInterface;

/**
 * Records each TermsAnsweredEvent in the test environment, with what was saved of its data when it was dispatched, so
 * a test can tell whether it came after the Form's save
 */
final class TermsAnsweredEventRecorder
{
    /** @var list<array{event: TermsAnsweredEvent, id: mixed, storedCheckoutState: string|null}> */
    private array $recorded = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function __invoke(TermsAnsweredEvent $event): void
    {
        $data = $event->data;
        $id = $data instanceof ResourceInterface ? $data->getId() : null;

        $this->recorded[] = [
            'event' => $event,
            // A Customer gets its ID when it is inserted
            'id' => $id,
            // An Order's checkout state as flushed, read through the connection the test's transaction is on
            'storedCheckoutState' => $data instanceof OrderInterface && null !== $id ? $this->fetchCheckoutState($id) : null,
        ];
    }

    /**
     * @return list<array{event: TermsAnsweredEvent, id: mixed, storedCheckoutState: string|null}>
     */
    public function getRecorded(): array
    {
        return $this->recorded;
    }

    private function fetchCheckoutState(mixed $id): ?string
    {
        $state = $this->connection->fetchOne('SELECT checkout_state FROM sylius_order WHERE id = ?', [$id]);

        return is_string($state) ? $state : null;
    }
}
