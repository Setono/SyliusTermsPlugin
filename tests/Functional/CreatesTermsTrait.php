<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;

/**
 * Creates the Terms a test needs inline with the plugin's factories, inside the test's rolled-back transaction
 */
trait CreatesTermsTrait
{
    /**
     * Persists and flushes a Terms. Saving it creates version 1, in force from the clock's now
     *
     * @param list<string> $forms
     * @param list<ChannelInterface> $channels
     * @param list<string> $localeCodes
     */
    private static function createTerms(
        string $code,
        array $forms = [],
        array $channels = [],
        array $localeCodes = ['en_US'],
        bool $enabled = true,
        bool $required = true,
        ?int $position = null,
    ): TermsInterface {
        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_terms.factory.terms');

        $terms = $factory->createNew();
        self::assertInstanceOf(TermsInterface::class, $terms);
        $terms->setCode($code);
        $terms->setForms($forms);
        $terms->setEnabled($enabled);
        $terms->setRequired($required);
        $terms->setPosition($position);

        foreach ($channels as $channel) {
            $terms->addChannel($channel);
        }

        foreach ($localeCodes as $localeCode) {
            $terms->addTranslation(self::createTermsTranslation($code, $localeCode));
        }

        self::getEntityManager()->persist($terms);
        self::getEntityManager()->flush();

        return $terms;
    }

    private static function createTermsTranslation(string $code, string $localeCode): TermsTranslationInterface
    {
        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_terms.factory.terms_translation');

        $translation = $factory->createNew();
        self::assertInstanceOf(TermsTranslationInterface::class, $translation);
        $translation->setLocale($localeCode);
        $translation->setName(sprintf('%s (%s)', $code, $localeCode));
        $translation->setSlug(str_replace('_', '-', $code));
        $translation->setLabel(sprintf('I accept the [link:%s]', $code));
        $translation->setContent(sprintf('The content of %s', $code));

        return $translation;
    }

    /**
     * Clears the entity manager first, so the Terms comes from the database
     */
    private static function reloadTerms(TermsInterface $terms): TermsInterface
    {
        $id = $terms->getId();
        self::getEntityManager()->clear();

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $reloaded = $repository->find($id);
        self::assertInstanceOf(TermsInterface::class, $reloaded);

        return $reloaded;
    }

    private static function getChannel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.channel');

        $channel = $repository->findOneByCode($code);
        self::assertInstanceOf(ChannelInterface::class, $channel);

        return $channel;
    }

    /**
     * Creates a channel that shares FASHION_WEB's default locale and base currency
     */
    private static function createChannel(string $code): ChannelInterface
    {
        $fashionWeb = self::getChannel('FASHION_WEB');

        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('sylius.factory.channel');

        $channel = $factory->createNew();
        self::assertInstanceOf(ChannelInterface::class, $channel);
        $channel->setCode($code);
        $channel->setName($code);
        $channel->setDefaultLocale($fashionWeb->getDefaultLocale());
        $channel->setBaseCurrency($fashionWeb->getBaseCurrency());
        $channel->setTaxCalculationStrategy('order_items_based');

        self::getEntityManager()->persist($channel);
        self::getEntityManager()->flush();

        return $channel;
    }

    private static function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('setono_sylius_terms.manager.terms');

        return $entityManager;
    }
}
