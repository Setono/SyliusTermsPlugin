<?php

declare(strict_types=1);

namespace spec\Setono\SyliusTermsPlugin\Menu;

use Knp\Menu\ItemInterface;
use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Setono\SyliusTermsPlugin\Menu\AdminMenuListener;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

class AdminMenuListenerSpec extends ObjectBehavior
{
    public function it_is_initializable(): void
    {
        $this->shouldHaveType(AdminMenuListener::class);
    }

    public function it_throws_exception(MenuBuilderEvent $event, ItemInterface $item): void
    {
        $event->getMenu()->willReturn($item);

        $this->shouldThrow(\RuntimeException::class)->during('addItems', [$event]);
    }

    public function it_adds_item(MenuBuilderEvent $event, ItemInterface $menu, ItemInterface $header, ItemInterface $child): void
    {
        $menu->getChild('configuration')->willReturn($header);
        $event->getMenu()->willReturn($menu);
        $child->setLabel(Argument::any())->shouldBeCalled()->willReturn($child);
        $child->setLabelAttribute(Argument::cetera())->shouldBeCalled();

        $header->addChild(Argument::cetera())->shouldBeCalled()->willReturn($child);

        $this->addItems($event);
    }
}
