<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Application\Fixture;

use Doctrine\Persistence\ObjectManager;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Webmozart\Assert\Assert;

/**
 * Adds locales to existing channels. Sylius' channel fixture can only create channels, and Sylius' order fixture fails
 * now and then once a channel has more than one locale (an order in one locale hydrates products with only that
 * locale's translation, and a later order in another locale then inserts an empty translation). So the suite adds the
 * locales after the orders
 */
final class ChannelLocalesFixture extends AbstractFixture
{
    /**
     * @param ChannelRepositoryInterface<ChannelInterface> $channelRepository
     * @param RepositoryInterface<LocaleInterface> $localeRepository
     */
    public function __construct(
        private readonly ChannelRepositoryInterface $channelRepository,
        private readonly RepositoryInterface $localeRepository,
        private readonly ObjectManager $channelManager,
    ) {
    }

    public function getName(): string
    {
        return 'test_channel_locales';
    }

    public function load(array $options): void
    {
        /** @var array<string, list<string>> $channels */
        $channels = $options['channels'];

        foreach ($channels as $channelCode => $localeCodes) {
            $channel = $this->channelRepository->findOneByCode($channelCode);
            Assert::isInstanceOf($channel, ChannelInterface::class, sprintf('There is no channel "%s"', $channelCode));

            foreach ($localeCodes as $localeCode) {
                $locale = $this->localeRepository->findOneBy(['code' => $localeCode]);
                Assert::isInstanceOf($locale, LocaleInterface::class, sprintf('There is no locale "%s"', $localeCode));

                $channel->addLocale($locale);
            }
        }

        $this->channelManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('channels')
                    ->useAttributeAsKey('code')
                    ->arrayPrototype()->scalarPrototype()->end()->end()
                ->end()
        ;
    }
}
