<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Checker;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeChecker;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Setono\SyliusTermsPlugin\Tests\Unit\ContentWhitespaceCases;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\CoreBundle\Form\Type\ContactType;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Factory\Factory;
use Sylius\Resource\Model\TranslationInterface;
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

    /**
     * Content upgraded from 2.x keeps the line breaks it was saved with, while its version has LF line breaks
     */
    #[Test]
    #[DataProvider('provideContentWithOtherLineBreaks')]
    public function content_that_differs_from_the_version_only_in_its_line_breaks_has_not_changed(string $content): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent("## Cookies\n\nWe use cookies.\n");
        self::snapshot($terms);

        self::loadContent($terms->getTranslation('en_US'), $content);

        self::assertFalse($this->checker->hasChanged($terms));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideContentWithOtherLineBreaks(): iterable
    {
        yield 'CRLF' => ["## Cookies\r\n\r\nWe use cookies.\r\n"];
        yield 'CR' => ["## Cookies\r\rWe use cookies.\r"];
        yield 'mixed' => ["## Cookies\r\n\rWe use cookies.\n"];
    }

    /**
     * Content from 2.x keeps its whitespace, while its version is stored without the whitespace Markdown ignores
     */
    #[Test]
    #[DataProviderExternal(ContentWhitespaceCases::class, 'provideContentWithWhitespaceMarkdownIgnores')]
    public function content_that_differs_from_the_version_only_in_the_whitespace_markdown_ignores_has_not_changed(string $content, string $stored): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent($stored);
        self::snapshot($terms);

        self::loadContent($terms->getTranslation('en_US'), $content);

        self::assertFalse($this->checker->hasChanged($terms));
    }

    /**
     * A version stored before its setter dropped the whitespace Markdown ignores is compared the same way
     */
    #[Test]
    #[DataProviderExternal(ContentWhitespaceCases::class, 'provideContentWithWhitespaceMarkdownIgnores')]
    public function a_version_whose_content_differs_only_in_the_whitespace_markdown_ignores_has_not_changed(string $content, string $stored): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent($stored);
        self::snapshot($terms);

        $version = $terms->getLatestVersion();
        self::assertNotNull($version);
        self::loadVersionContent($version->getTranslation('en_US'), $content);

        self::assertFalse($this->checker->hasChanged($terms));
    }

    #[Test]
    #[DataProviderExternal(ContentWhitespaceCases::class, 'provideContentThatIsOnlyWhitespace')]
    public function content_that_is_only_whitespace_has_not_changed_from_a_version_without_content(string $content): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent(null);
        self::snapshot($terms);

        self::loadContent($terms->getTranslation('en_US'), $content);

        self::assertFalse($this->checker->hasChanged($terms));
    }

    /**
     * Markdown gives meaning to the whitespace from the first non-blank line's indentation to the last non-blank
     * character
     */
    #[Test]
    #[DataProvider('provideWhitespaceChangesMarkdownSees')]
    public function changing_whitespace_that_markdown_sees_counts_as_a_change(string $content): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent("We use cookies.\nNo tracking.");
        self::snapshot($terms);

        self::loadContent($terms->getTranslation('en_US'), $content);

        self::assertTrue($this->checker->hasChanged($terms));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideWhitespaceChangesMarkdownSees(): iterable
    {
        yield 'the first line indented into a code block' => ["    We use cookies.\nNo tracking."];
        yield 'a hard line break at the end of an inner line' => ["We use cookies.  \nNo tracking."];
        yield 'a blank line between the lines' => ["We use cookies.\n\nNo tracking."];
    }

    #[Test]
    public function changed_content_with_crlf_line_breaks_counts_as_a_change(): void
    {
        $terms = self::createTerms();
        $terms->getTranslation('en_US')->setContent("## Cookies\n\nWe use cookies.\n");
        self::snapshot($terms);

        self::loadContent($terms->getTranslation('en_US'), "## Cookies\r\n\r\nWe use no cookies.\r\n");

        self::assertTrue($this->checker->hasChanged($terms));
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

    #[Test]
    public function it_fails_on_a_terms_translation_of_another_class(): void
    {
        $terms = self::createVersionedTerms();

        $translation = $this->prophesize(TranslationInterface::class);
        $translation->getLocale()->willReturn('da_DK');
        $translation->setTranslatable($terms)->shouldBeCalled();
        $terms->addTranslation($translation->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TermsTranslationInterface::class);

        $this->checker->hasChanged($terms);
    }

    #[Test]
    public function it_fails_on_a_version_translation_of_another_class(): void
    {
        $terms = self::createVersionedTerms();
        $version = $terms->getLatestVersion();
        self::assertNotNull($version);

        $translation = $this->prophesize(TranslationInterface::class);
        $translation->getLocale()->willReturn('da_DK');
        $translation->setTranslatable($version)->shouldBeCalled();
        $version->addTranslation($translation->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TermsVersionTranslationInterface::class);

        $this->checker->hasChanged($terms);
    }

    /**
     * Sets the Content past the setter, as Doctrine loads it
     */
    private static function loadContent(TermsTranslationInterface $translation, string $content): void
    {
        (new \ReflectionProperty(TermsTranslation::class, 'content'))->setValue($translation, $content);
    }

    /**
     * Sets the version's Content past the setter, as Doctrine loads it
     */
    private static function loadVersionContent(TermsVersionTranslationInterface $translation, string $content): void
    {
        (new \ReflectionProperty(TermsVersionTranslation::class, 'content'))->setValue($translation, $content);
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
