<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Sylius\Abstraction\StateMachine\StateMachineInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\AddressInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\Model\OrderItemInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Model\ShopUserInterface;
use Sylius\Component\Core\OrderCheckoutTransitions;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Sylius\Component\Order\Modifier\OrderItemQuantityModifierInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;

/**
 * Creates a cart that has reached checkout complete, inside the test's rolled-back transaction, the way Sylius' order
 * fixture does
 */
trait CreatesCartTrait
{
    /**
     * The shop user of Sylius' default fixtures. Log in as it, and the cart context finds the customer's cart
     */
    private static function getShopUser(): ShopUserInterface
    {
        /** @var UserRepositoryInterface<ShopUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.shop_user');

        $user = $repository->findOneByEmail('shop@example.com');
        self::assertInstanceOf(ShopUserInterface::class, $user);

        return $user;
    }

    /**
     * Persists and flushes a cart of the shop user's customer in FASHION_WEB, with one item, addressed and with the
     * default shipping and payment methods selected, so its next step is checkout complete
     */
    private static function createCartReadyToComplete(ShopUserInterface $user): OrderInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $channelRepository */
        $channelRepository = self::getContainer()->get('sylius.repository.channel');

        $channel = $channelRepository->findOneByCode('FASHION_WEB');
        self::assertInstanceOf(ChannelInterface::class, $channel);

        /** @var FactoryInterface $orderFactory */
        $orderFactory = self::getContainer()->get('sylius.factory.order');

        $order = $orderFactory->createNew();
        self::assertInstanceOf(OrderInterface::class, $order);
        $order->setChannel($channel);
        // Not created by a guest, so the cart context finds it for the logged-in customer
        $order->setCustomerWithAuthorization($user->getCustomer());
        $order->setCurrencyCode($channel->getBaseCurrency()?->getCode());
        $order->setLocaleCode('en_US');
        $order->addItem(self::createOrderItem($channel));

        $order->setShippingAddress(self::createAddress());
        $order->setBillingAddress(self::createAddress());

        /** @var StateMachineInterface $stateMachine */
        $stateMachine = self::getContainer()->get('sylius_abstraction.state_machine');

        // Each transition processes the order, which adds the shipment and the payment with the default methods
        foreach ([
            OrderCheckoutTransitions::TRANSITION_ADDRESS,
            OrderCheckoutTransitions::TRANSITION_SELECT_SHIPPING,
            OrderCheckoutTransitions::TRANSITION_SELECT_PAYMENT,
        ] as $transition) {
            $stateMachine->apply($order, OrderCheckoutTransitions::GRAPH, $transition);
        }

        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('sylius.manager.order');
        $entityManager->persist($order);
        $entityManager->flush();

        return $order;
    }

    private static function createOrderItem(ChannelInterface $channel): OrderItemInterface
    {
        /** @var ProductRepositoryInterface<ProductInterface> $productRepository */
        $productRepository = self::getContainer()->get('sylius.repository.product');

        $products = $productRepository->findLatestByChannel($channel, 'en_US', 1);
        self::assertCount(1, $products);

        $variant = $products[0]->getEnabledVariants()->first();
        self::assertInstanceOf(ProductVariantInterface::class, $variant);

        /** @var FactoryInterface $orderItemFactory */
        $orderItemFactory = self::getContainer()->get('sylius.factory.order_item');

        $item = $orderItemFactory->createNew();
        self::assertInstanceOf(OrderItemInterface::class, $item);
        $item->setVariant($variant);

        /** @var OrderItemQuantityModifierInterface $quantityModifier */
        $quantityModifier = self::getContainer()->get(OrderItemQuantityModifierInterface::class);
        $quantityModifier->modify($item, 1);

        return $item;
    }

    private static function createAddress(): AddressInterface
    {
        /** @var FactoryInterface $addressFactory */
        $addressFactory = self::getContainer()->get('sylius.factory.address');

        $address = $addressFactory->createNew();
        self::assertInstanceOf(AddressInterface::class, $address);
        $address->setFirstName('Jane');
        $address->setLastName('Doe');
        $address->setStreet('1 Main Street');
        $address->setCity('Springfield');
        $address->setPostcode('12345');
        $address->setCountryCode('US');

        return $address;
    }
}
