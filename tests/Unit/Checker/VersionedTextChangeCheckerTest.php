<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeChecker;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Factory\Factory;
use Symfony\Component\Clock\MockClock;

final class VersionedTextChangeCheckerTest extends TestCase
{
    use ProphecyTrait;

    private VersionedTextChangeChecker $checker;

    protected function setUp(): void
    {
        $this->checker = new VersionedTextChangeChecker();
    }

    #[Test]
    public function a_terms_without_a_version_has_changed(): void
    {
        self::assertTrue($this->checker->hasChanged(self::createTerms()));
    }

    #[Test]
    public function a_terms_saved_as_its_latest_version_has_not_changed(): void
    {
        self::assertFalse($this->checker->hasChanged(self::createVersionedTerms()));
    }

    /**
     * @param callable(TermsInterface): void $change
     */
    #[Test]
    #[DataProvider('provideVersionedTextChanges')]
    public function changing_versioned_text_counts_as_a_change(callable $change): void
    {
        $terms = self::createVersionedTerms();

        $change($terms);

        self::assertTrue($this->checker->hasChanged($terms));
    }

    /**
     * @return \Generator<string, array{callable(TermsInterface): void}>
     */
    public static function provideVersionedTextChanges(): \Generator
    {
        yield 'name in en_US' => [static fn (TermsInterface $terms) => $terms->getTranslation('en_US')->setName('Terms of sale')];
        yield 'label in en_US' => [static fn (TermsInterface $terms) => $terms->getTranslation('en_US')->setLabel('I agree to the [link:terms]')];
        yield 'content in en_US' => [static fn (TermsInterface $terms) => $terms->getTranslation('en_US')->setContent('New content')];
        yield 'name in de_DE' => [static fn (TermsInterface $terms) => $terms->getTranslation('de_DE')->setName('Verkaufsbedingungen')];
        yield 'label in de_DE' => [static fn (TermsInterface $terms) => $terms->getTranslation('de_DE')->setLabel('Ich stimme den [link:AGB] zu')];
        yield 'content in de_DE' => [static fn (TermsInterface $terms) => $terms->getTranslation('de_DE')->setContent('Neuer Inhalt')];
        yield 'content cleared' => [static fn (TermsInterface $terms) => $terms->getTranslation('en_US')->setContent(null)];
        yield 'translation added' => [static fn (TermsInterface $terms) => $terms->addTranslation(self::createTranslation('fr_FR', 'CGV'))];
        yield 'translation removed' => [static fn (TermsInterface $terms) => $terms->removeTranslation($terms->getTranslation('de_DE'))];
    }

    /**
     * @param callable(TermsInterface, ChannelInterface): void $change
     */
    #[Test]
    #[DataProvider('provideUnversionedChanges')]
    public function changing_anything_else_does_not_count_as_a_change(callable $change): void
    {
        $terms = self::createVersionedTerms();

        $change($terms, $this->prophesize(ChannelInterface::class)->reveal());

        self::assertFalse($this->checker->hasChanged($terms));
    }

    /**
     * @return \Generator<string, array{callable(TermsInterface, ChannelInterface): void}>
     */
    public static function provideUnversionedChanges(): \Generator
    {
        yield 'slug' => [static fn (TermsInterface $terms) => $terms->getTranslation('de_DE')->setSlug('verkaufsbedingungen')];
        yield 'code' => [static fn (TermsInterface $terms) => $terms->setCode('terms_of_sale')];
        yield 'channels' => [static fn (TermsInterface $terms, ChannelInterface $channel) => $terms->addChannel($channel)];
        yield 'Forms' => [static fn (TermsInterface $terms) => $terms->setForms([CompleteType::class, ContactType::class])];
        yield 'enabled' => [static fn (TermsInterface $terms) => $terms->setEnabled(false)];
    }

    #[Test]
    public function it_compares_with_the_latest_version(): void
    {
        $terms = self::createVersionedTerms();
        $terms->getTranslation('en_US')->setContent('New content');
        self::snapshot($terms);

        $terms->getTranslation('en_US')->setContent('Content');

        self::assertTrue($this->checker->hasChanged($terms));
    }

    private static function createVersionedTerms(): TermsInterface
    {
        $terms = self::createTerms();
        self::snapshot($terms);

        return $terms;
    }

    private static function snapshot(TermsInterface $terms): void
    {
        (new TermsVersionSnapshotter(
            new Factory(TermsVersion::class),
            new Factory(TermsVersionTranslation::class),
            new MockClock(),
        ))->snapshot($terms);
    }

    private static function createTerms(): TermsInterface
    {
        $terms = new Terms();
        $terms->setCode('terms_and_conditions');
        $terms->setForms([CompleteType::class]);
        $terms->addTranslation(self::createTranslation('en_US', 'Terms and conditions'));
        $terms->addTranslation(self::createTranslation('de_DE', 'AGB'));

        return $terms;
    }

    private static function createTranslation(string $localeCode, string $name): TermsTranslation
    {
        $translation = new TermsTranslation();
        $translation->setLocale($localeCode);
        $translation->setName($name);
        $translation->setSlug('slug-' . $localeCode);
        $translation->setLabel('Label ' . $localeCode);
        $translation->setContent('Content');

        return $translation;
    }
}
