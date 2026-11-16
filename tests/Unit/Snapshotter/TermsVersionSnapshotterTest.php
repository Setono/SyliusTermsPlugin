<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Snapshotter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Clock\MockClock;

final class TermsVersionSnapshotterTest extends TestCase
{
    private MockClock $clock;

    private TermsVersionSnapshotter $snapshotter;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-16 12:00:00');
        $this->snapshotter = new TermsVersionSnapshotter(
            new Factory(TermsVersion::class),
            new Factory(TermsVersionTranslation::class),
            $this->clock,
        );
    }

    #[Test]
    public function it_copies_name_label_and_content_for_every_locale(): void
    {
        $terms = self::createTerms();

        $version = $this->snapshotter->snapshot($terms);

        $texts = [];
        foreach ($version->getTranslations() as $localeCode => $translation) {
            self::assertInstanceOf(TermsVersionTranslationInterface::class, $translation);
            self::assertSame($localeCode, $translation->getLocale());
            $texts[$localeCode] = [$translation->getName(), $translation->getLabel(), $translation->getContent()];
        }

        self::assertSame([
            'en_US' => ['Terms and conditions', 'I accept the [link:terms]', 'Content'],
            'de_DE' => ['AGB', 'Ich akzeptiere die [link:AGB]', null],
        ], $texts);
    }

    #[Test]
    public function it_numbers_the_first_version_1(): void
    {
        self::assertSame(1, $this->snapshotter->snapshot(self::createTerms())->getNumber());
    }

    #[Test]
    public function it_numbers_a_new_version_after_the_latest(): void
    {
        $terms = self::createTerms();
        $this->snapshotter->snapshot($terms);
        $this->snapshotter->snapshot($terms);

        self::assertSame(3, $this->snapshotter->snapshot($terms)->getNumber());
    }

    #[Test]
    public function it_takes_the_moment_it_is_in_force_from_from_the_clock(): void
    {
        $terms = self::createTerms();
        $this->snapshotter->snapshot($terms);
        $this->clock->sleep(90);

        $version = $this->snapshotter->snapshot($terms);

        self::assertEquals(new \DateTimeImmutable('2026-11-16 12:01:30'), $version->getInForceFrom());
    }

    #[Test]
    public function it_adds_the_version_to_the_terms(): void
    {
        $terms = self::createTerms();

        $version = $this->snapshotter->snapshot($terms);

        self::assertSame($terms, $version->getTerms());
        self::assertSame([$version], $terms->getVersions()->toArray());
        self::assertSame($version, $terms->getLatestVersion());
    }

    private static function createTerms(): TermsInterface
    {
        $terms = new Terms();
        $terms->addTranslation(self::createTranslation('en_US', 'Terms and conditions', 'I accept the [link:terms]', 'Content'));
        $terms->addTranslation(self::createTranslation('de_DE', 'AGB', 'Ich akzeptiere die [link:AGB]', null));

        return $terms;
    }

    private static function createTranslation(string $localeCode, string $name, string $label, ?string $content): TermsTranslation
    {
        $translation = new TermsTranslation();
        $translation->setLocale($localeCode);
        $translation->setName($name);
        $translation->setSlug('slug-' . $localeCode);
        $translation->setLabel($label);
        $translation->setContent($content);

        return $translation;
    }
}
