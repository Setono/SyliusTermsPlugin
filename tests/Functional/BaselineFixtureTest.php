<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Bundle\FixturesBundle\Fixture\FixtureInterface;
use Sylius\Bundle\FixturesBundle\Suite\SuiteRegistryInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class BaselineFixtureTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const BASELINE = [
        'terms_and_conditions' => ['checkout_complete'],
        'privacy_policy' => ['registration', 'contact'],
    ];

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-16 12:00:00');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function loading_the_test_apps_suite_creates_both_baseline_terms_at_version_1(): void
    {
        // The test database holds the baseline already. It is deleted inside the test's rolled-back transaction, so
        // the fixture creates it anew
        foreach (array_keys(self::BASELINE) as $code) {
            $terms = self::getTermsRepository()->findOneBy(['code' => $code]);
            if (null !== $terms) {
                self::getEntityManager()->remove($terms);
            }
        }
        self::getEntityManager()->flush();

        [$fixture, $options] = self::getTermsFixtureOfTheDefaultSuite();
        $fixture->load($options);

        foreach (self::BASELINE as $code => $forms) {
            $terms = self::getTermsRepository()->findOneBy(['code' => $code]);
            self::assertInstanceOf(TermsInterface::class, $terms, $code);

            self::assertTrue($terms->isEnabled(), $code);
            self::assertTrue($terms->isRequired(), $code);
            self::assertSame($forms, $terms->getForms(), $code);
            self::assertSame(['FASHION_WEB'], $terms->getChannels()->map(static fn (ChannelInterface $channel): ?string => $channel->getCode())->getValues(), $code);
            self::assertEqualsCanonicalizing(['en_US', 'de_DE'], $terms->getTranslations()->getKeys(), $code);

            foreach (['en_US', 'de_DE'] as $localeCode) {
                $content = (string) $terms->getTranslation($localeCode)->getContent();
                self::assertGreaterThanOrEqual(2, preg_match_all('/^## /m', $content), sprintf('%s in %s has two sections', $code, $localeCode));
                self::assertMatchesRegularExpression('/^\|[-:| ]+\|$/m', $content, sprintf('%s in %s has a table', $code, $localeCode));
                self::assertStringContainsString('](/images/terms-wide.png)', $content, sprintf('%s in %s has the wide image', $code, $localeCode));
            }

            $versions = $terms->getVersions()->getValues();
            self::assertCount(1, $versions, $code);
            self::assertSame(1, $versions[0]->getNumber(), $code);
            self::assertSame('2026-11-16 12:00:00', $versions[0]->getInForceFrom()?->format('Y-m-d H:i:s'), $code);
            self::assertEqualsCanonicalizing(['en_US', 'de_DE'], $versions[0]->getTranslations()->getKeys(), $code);
        }

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        self::assertFileExists($projectDir . '/public/images/terms-wide.png');
    }

    /**
     * @return array{FixtureInterface, array<mixed>}
     */
    private static function getTermsFixtureOfTheDefaultSuite(): array
    {
        $suiteRegistry = self::getContainer()->get('sylius_fixtures.suite_registry');
        self::assertInstanceOf(SuiteRegistryInterface::class, $suiteRegistry);

        foreach ($suiteRegistry->getSuite('default')->getFixtures() as $fixture => $options) {
            if ('setono_terms' === $fixture->getName()) {
                return [$fixture, $options];
            }
        }

        self::fail('The default suite has no setono_terms fixture');
    }

    private static function getTermsRepository(): TermsRepositoryInterface
    {
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');
        self::assertInstanceOf(TermsRepositoryInterface::class, $repository);

        return $repository;
    }

    private static function getEntityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);

        return $entityManager;
    }
}
