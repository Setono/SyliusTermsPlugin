<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Dispatcher;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcher;
use Setono\SyliusTermsPlugin\Event\TermsAnsweredEvent;
use Sylius\Component\Channel\Model\Channel;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class TermsAnsweredDispatcherTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy<EventDispatcherInterface> */
    private ObjectProphecy $eventDispatcher;

    private TermsAnsweredDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $this->eventDispatcher->dispatch(Argument::any())->willReturnArgument(0);

        $this->dispatcher = new TermsAnsweredDispatcher($this->eventDispatcher->reveal(), [
            'checkout_complete' => ['saved_event' => 'sylius.order.post_complete'],
            'registration' => ['saved_event' => 'sylius.customer.post_register'],
            'contact' => ['saved_event' => null],
        ]);
    }

    #[Test]
    public function it_dispatches_right_away_for_a_form_without_a_saved_event(): void
    {
        $event = self::createEvent('contact');

        $this->dispatcher->dispatch($event);

        $this->eventDispatcher->dispatch($event)->shouldHaveBeenCalledOnce();
    }

    #[Test]
    public function it_dispatches_right_away_for_a_form_that_is_not_configured(): void
    {
        $event = self::createEvent('newsletter');

        $this->dispatcher->dispatch($event);

        $this->eventDispatcher->dispatch($event)->shouldHaveBeenCalledOnce();
    }

    #[Test]
    public function it_holds_the_event_of_a_form_with_a_saved_event_until_that_event_and_dispatches_it_once(): void
    {
        $event = self::createEvent('checkout_complete');

        $this->dispatcher->dispatch($event);
        $this->eventDispatcher->dispatch(Argument::any())->shouldNotHaveBeenCalled();

        // Another Form's saved event leaves it waiting
        $this->dispatcher->onSaved(new GenericEvent(), 'sylius.customer.post_register');
        $this->eventDispatcher->dispatch(Argument::any())->shouldNotHaveBeenCalled();

        $this->dispatcher->onSaved(new GenericEvent(), 'sylius.order.post_complete');
        $this->dispatcher->onSaved(new GenericEvent(), 'sylius.order.post_complete');

        $this->eventDispatcher->dispatch($event)->shouldHaveBeenCalledOnce();
    }

    #[Test]
    public function the_saved_event_dispatches_every_event_waiting_for_it_in_order(): void
    {
        $first = self::createEvent('checkout_complete');
        $second = self::createEvent('checkout_complete');

        $this->dispatcher->dispatch($first);
        $this->dispatcher->dispatch($second);

        $dispatched = [];
        $this->eventDispatcher->dispatch(Argument::any())->will(static function (array $arguments) use (&$dispatched): object {
            /** @var object $event */
            $event = $arguments[0];
            $dispatched[] = $event;

            return $event;
        });

        $this->dispatcher->onSaved(new GenericEvent(), 'sylius.order.post_complete');

        self::assertSame([$first, $second], $dispatched);
    }

    #[Test]
    public function resetting_drops_the_events_whose_save_never_came(): void
    {
        $this->dispatcher->dispatch(self::createEvent('checkout_complete'));

        $this->dispatcher->reset();
        $this->dispatcher->onSaved(new GenericEvent(), 'sylius.order.post_complete');

        $this->eventDispatcher->dispatch(Argument::any())->shouldNotHaveBeenCalled();
    }

    private static function createEvent(string $form): TermsAnsweredEvent
    {
        return new TermsAnsweredEvent($form, null, new Channel(), 'en_US', []);
    }
}
