<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Menu;

use Knp\Menu\ItemInterface;
use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function addItems(MenuBuilderEvent $event): void
    {
        $menu = $event->getMenu();

        $header = $this->getHeader($menu);

        $header
            ->addChild('terms', [
                'route' => 'setono_sylius_terms_admin_terms_index',
            ])
            ->setLabel('setono_sylius_terms.menu.admin.main.configuration.terms')
            ->setLabelAttribute('icon', 'check circle outline')
        ;
    }

    private function getHeader(ItemInterface $menu): ItemInterface
    {
        $header = $menu->getChild('configuration');

        if (null === $header) {
            throw new \RuntimeException('No header found with key `configuration`');
        }

        return $header;
    }
}
