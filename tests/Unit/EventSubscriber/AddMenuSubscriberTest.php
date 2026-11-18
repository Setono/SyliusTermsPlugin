<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\EventSubscriber;

use Knp\Menu\Integration\Symfony\RoutingExtension;
use Knp\Menu\ItemInterface;
use Knp\Menu\MenuFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\EventSubscriber\AddMenuSubscriber;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AddMenuSubscriberTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function it_subscribes_to_the_admin_main_menu(): void
    {
        self::assertSame(['sylius.menu.admin.main' => 'add'], AddMenuSubscriber::getSubscribedEvents());
    }

    #[Test]
    public function it_adds_terms_under_configuration(): void
    {
        $factory = $this->createMenuFactory();
        $menu = $factory->createItem('root');
        $menu->addChild('catalog');
        $configuration = $menu->addChild('configuration');

        (new AddMenuSubscriber())->add(new MenuBuilderEvent($factory, $menu));

        $terms = $configuration->getChild('terms');
        self::assertInstanceOf(ItemInterface::class, $terms);
        self::assertSame('/admin/terms/', $terms->getUri());
        self::assertSame('setono_sylius_terms.menu.admin.main.configuration.terms', $terms->getLabel());
        self::assertSame('tabler:circle-check', $terms->getLabelAttribute('icon'));
        self::assertSame([
            // The routing extension adds the item's own route after the ones the item sets
            ['route' => 'setono_sylius_terms_admin_terms_create'],
            ['route' => 'setono_sylius_terms_admin_terms_update'],
            ['route' => 'setono_sylius_terms_admin_terms_version_show'],
            ['route' => 'setono_sylius_terms_admin_terms_index', 'parameters' => []],
        ], $terms->getExtra('routes'));
    }

    #[Test]
    public function it_adds_terms_under_the_first_item_without_a_configuration_item(): void
    {
        $factory = $this->createMenuFactory();
        $menu = $factory->createItem('root');
        $catalog = $menu->addChild('catalog');
        $menu->addChild('sales');

        (new AddMenuSubscriber())->add(new MenuBuilderEvent($factory, $menu));

        self::assertNotNull($catalog->getChild('terms'));
    }

    private function createMenuFactory(): MenuFactory
    {
        $urlGenerator = $this->prophesize(UrlGeneratorInterface::class);
        $urlGenerator->generate('setono_sylius_terms_admin_terms_index', [], Argument::any())->willReturn('/admin/terms/');

        $factory = new MenuFactory();
        $factory->addExtension(new RoutingExtension($urlGenerator->reveal()));

        return $factory;
    }
}
