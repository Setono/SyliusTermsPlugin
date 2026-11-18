<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class TermsVersionTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    /** Name, label and content by locale */
    private const TEXTS = [
        'en_US' => ['Terms and conditions', 'I accept the [link:terms]', 'Content'],
        'de_DE' => ['AGB', 'Ich akzeptiere die [link:AGB]', 'Inhalt'],
        'fr_FR' => ['CGV', 'J’accepte les [link:CGV]', 'Contenu'],
    ];

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();

        // MySQL's DATETIME has one-second precision, so the mock clock only ever moves in whole seconds
        $clock = self::mockTime('2026-11-16 12:00:00');
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function creating_a_terms_stores_version_1_at_the_clocks_time(): void
    {
        $termsId = $this->save(self::createTerms())->getId();

        $versions = $this->reloadVersions($termsId);

        self::assertCount(1, $versions);
        self::assertNotNull($versions[0]->getId());
        self::assertSame(1, $versions[0]->getNumber());
        self::assertSame('2026-11-16 12:00:00', $versions[0]->getInForceFrom()?->format('Y-m-d H:i:s'));
        self::assertSame(['de_DE' => self::TEXTS['de_DE'], 'en_US' => self::TEXTS['en_US']], self::texts($versions[0]));
    }

    #[Test]
    public function changing_only_the_de_DE_content_creates_version_2_holding_every_locale(): void
    {
        $termsId = $this->save(self::createTerms())->getId();
        $this->clock->sleep(60);

        $this->reloadTerms($termsId)->getTranslation('de_DE')->setContent('Neuer Inhalt');
        $this->flush();

        $versions = $this->reloadVersions($termsId);

        self::assertCount(2, $versions);
        self::assertSame(2, $versions[1]->getNumber());
        self::assertSame('2026-11-16 12:01:00', $versions[1]->getInForceFrom()?->format('Y-m-d H:i:s'));
        self::assertSame([
            'de_DE' => ['AGB', 'Ich akzeptiere die [link:AGB]', 'Neuer Inhalt'],
            'en_US' => self::TEXTS['en_US'],
        ], self::texts($versions[1]));

        // Version 1 is unchanged
        self::assertSame(1, $versions[0]->getNumber());
        self::assertSame('2026-11-16 12:00:00', $versions[0]->getInForceFrom()?->format('Y-m-d H:i:s'));
        self::assertSame(['de_DE' => self::TEXTS['de_DE'], 'en_US' => self::TEXTS['en_US']], self::texts($versions[0]));
    }

    #[Test]
    public function a_save_that_changes_only_placement_creates_no_version(): void
    {
        $termsId = $this->save(self::createTerms())->getId();
        $this->clock->sleep(60);

        $terms = $this->reloadTerms($termsId);
        $terms->addChannel($this->getChannel('FASHION_WEB'));
        $terms->setForms(['checkout_complete', 'contact']);
        $terms->setEnabled(false);
        $terms->setRequired(false);
        $terms->setPosition(0);
        $this->flush();

        self::assertCount(1, $this->reloadVersions($termsId));
    }

    #[Test]
    public function adding_a_translation_creates_a_version_and_so_does_removing_it_again(): void
    {
        $termsId = $this->save(self::createTerms())->getId();
        $this->clock->sleep(60);

        $this->reloadTerms($termsId)->addTranslation(self::createTranslation('fr_FR'));
        $this->flush();
        $this->clock->sleep(60);

        $terms = $this->reloadTerms($termsId);
        $terms->removeTranslation($terms->getTranslation('fr_FR'));
        $this->flush();

        $versions = $this->reloadVersions($termsId);

        self::assertCount(3, $versions);
        self::assertSame(['de_DE', 'en_US', 'fr_FR'], array_keys(self::texts($versions[1])));
        self::assertSame(['de_DE', 'en_US'], array_keys(self::texts($versions[2])));
    }

    #[Test]
    public function saving_twice_in_one_request_numbers_the_versions_in_order(): void
    {
        $terms = $this->save(self::createTerms());
        $terms->getTranslation('en_US')->setContent('New content');
        $this->flush();
        $terms->getTranslation('en_US')->setContent('Newer content');
        $this->flush();

        self::assertSame([1, 2, 3], array_map(
            static fn (TermsVersionInterface $version): ?int => $version->getNumber(),
            $this->reloadVersions($terms->getId()),
        ));
    }

    #[Test]
    public function the_in_force_lookup_returns_the_latest_version_saved_at_or_before_the_moment(): void
    {
        // Version 1 holds en_US only, version 2 adds de_DE a day later
        $termsId = $this->save(self::createTerms(['en_US']))->getId();
        $this->clock->sleep(86400);
        $this->reloadTerms($termsId)->addTranslation(self::createTranslation('de_DE'));
        $this->flush();
        $terms = $this->reloadTerms($termsId);

        self::assertNull($this->findOneInForce($terms, '2026-11-16 11:59:59', 'en_US'), 'Nothing before version 1');
        self::assertInForce(1, 'Terms and conditions', $this->findOneInForce($terms, '2026-11-16 12:00:00', 'en_US'));
        self::assertInForce(1, 'Terms and conditions', $this->findOneInForce($terms, '2026-11-17 11:59:59', 'en_US'));
        self::assertInForce(2, 'Terms and conditions', $this->findOneInForce($terms, '2026-11-17 12:00:00', 'en_US'));
        self::assertInForce(2, 'AGB', $this->findOneInForce($terms, '2026-11-17 12:00:00', 'de_DE'));
        self::assertNull($this->findOneInForce($terms, '2026-11-17 11:59:59', 'de_DE'), 'No fallback to the next version');
        self::assertNull($this->findOneInForce($terms, '2026-11-17 12:00:00', 'fr_FR'), 'No fallback to another locale');
    }

    #[Test]
    public function the_in_force_lookup_ignores_other_terms_versions(): void
    {
        $terms = $this->save(self::createTerms());
        $this->clock->sleep(60);
        $otherTerms = $this->save(self::createTerms(code: 'other_terms'));
        $otherTerms->getTranslation('en_US')->setContent('Other content');
        $this->flush();

        self::assertInForce(1, 'Terms and conditions', $this->findOneInForce($terms, '2026-11-16 12:01:00', 'en_US'));
    }

    #[Test]
    public function deleting_a_terms_deletes_its_versions(): void
    {
        $terms = $this->save(self::createTerms());
        $terms->getTranslation('de_DE')->setContent('Neuer Inhalt');
        $this->flush();
        $termsId = $terms->getId();
        $versionIds = array_map(static fn (TermsVersionInterface $version): ?int => $version->getId(), $terms->getVersions()->toArray());
        self::assertCount(2, $versionIds);

        $this->getEntityManager()->remove($terms);
        $this->flush();

        $connection = $this->getEntityManager()->getConnection();
        self::assertEquals(0, $connection->fetchOne(
            'SELECT COUNT(*) FROM setono_sylius_terms__terms_version WHERE terms_id = ?',
            [$termsId],
        ));
        self::assertEquals(0, $connection->fetchOne(
            'SELECT COUNT(*) FROM setono_sylius_terms__terms_version_translation WHERE translatable_id IN (?)',
            [$versionIds],
            [ArrayParameterType::INTEGER],
        ));
    }

    /**
     * @param callable(TermsVersionInterface): void $edit
     */
    #[Test]
    #[DataProvider('provideVersionEdits')]
    public function a_saved_version_cannot_be_edited(callable $edit): void
    {
        $termsId = $this->save(self::createTerms())->getId();
        $version = $this->reloadTerms($termsId)->getLatestVersion();
        self::assertNotNull($version);

        $edit($version);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A saved Terms version can\'t be edited.');

        $this->flush();
    }

    /**
     * @return \Generator<string, array{callable(TermsVersionInterface): void}>
     */
    public static function provideVersionEdits(): \Generator
    {
        yield 'number' => [static fn (TermsVersionInterface $version) => $version->setNumber(7)];
        yield 'moment it is in force from' => [static fn (TermsVersionInterface $version) => $version->setInForceFrom(new \DateTimeImmutable('2020-01-01'))];
        yield 'content' => [static function (TermsVersionInterface $version): void {
            $translation = $version->getTranslations()->get('de_DE');
            self::assertInstanceOf(TermsVersionTranslationInterface::class, $translation);
            $translation->setContent('Geänderter Inhalt');
        }];
        yield 'translation added' => [static function (TermsVersionInterface $version): void {
            $translation = new TermsVersionTranslation();
            $translation->setLocale('fr_FR');
            [$name, $label, $content] = self::TEXTS['fr_FR'];
            $translation->setName($name);
            $translation->setLabel($label);
            $translation->setContent($content);
            $version->addTranslation($translation);
        }];
        yield 'translation removed' => [static function (TermsVersionInterface $version): void {
            $translation = $version->getTranslations()->get('de_DE');
            self::assertNotNull($translation);
            $version->removeTranslation($translation);
        }];
    }

    /**
     * @param list<string> $localeCodes
     */
    private static function createTerms(array $localeCodes = ['en_US', 'de_DE'], string $code = 'versioned_terms'): TermsInterface
    {
        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_terms.factory.terms');

        $terms = $factory->createNew();
        self::assertInstanceOf(TermsInterface::class, $terms);
        $terms->setCode($code);

        foreach ($localeCodes as $localeCode) {
            $terms->addTranslation(self::createTranslation($localeCode, $code));
        }

        return $terms;
    }

    private static function createTranslation(string $localeCode, string $code = 'versioned_terms'): TermsTranslationInterface
    {
        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_terms.factory.terms_translation');

        [$name, $label, $content] = self::TEXTS[$localeCode];

        $translation = $factory->createNew();
        self::assertInstanceOf(TermsTranslationInterface::class, $translation);
        $translation->setLocale($localeCode);
        $translation->setName($name);
        $translation->setSlug(str_replace('_', '-', $code));
        $translation->setLabel($label);
        $translation->setContent($content);

        return $translation;
    }

    /**
     * @return array<string, array{string|null, string|null, string|null}>
     */
    private static function texts(TermsVersionInterface $version): array
    {
        $texts = [];

        /** @var TermsVersionTranslationInterface $translation */
        foreach ($version->getTranslations() as $translation) {
            $texts[(string) $translation->getLocale()] = [$translation->getName(), $translation->getLabel(), $translation->getContent()];
        }

        ksort($texts);

        return $texts;
    }

    private static function assertInForce(int $number, string $name, ?TermsVersionTranslationInterface $translation): void
    {
        self::assertNotNull($translation);
        self::assertNotNull($translation->getId());
        self::assertSame($number, $translation->getVersion()->getNumber());
        self::assertSame($name, $translation->getName());
    }

    private function findOneInForce(TermsInterface $terms, string $at, string $localeCode): ?TermsVersionTranslationInterface
    {
        /** @var TermsVersionRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms_version');

        return $repository->findOneInForce($terms, new \DateTimeImmutable($at), $localeCode);
    }

    private function save(TermsInterface $terms): TermsInterface
    {
        $this->getEntityManager()->persist($terms);
        $this->flush();

        return $terms;
    }

    private function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * Clears the entity manager first, so the Terms and its versions come from the database
     */
    private function reloadTerms(?int $id): TermsInterface
    {
        $this->getEntityManager()->clear();

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $terms = $repository->find($id);
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    /**
     * @return list<TermsVersionInterface>
     */
    private function reloadVersions(?int $termsId): array
    {
        return array_values($this->reloadTerms($termsId)->getVersions()->toArray());
    }

    private function getChannel(string $code): ChannelInterface
    {
        /** @var ChannelRepositoryInterface<ChannelInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.channel');

        $channel = $repository->findOneByCode($code);
        self::assertNotNull($channel);

        return $channel;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('setono_sylius_terms.manager.terms');

        return $entityManager;
    }
}
