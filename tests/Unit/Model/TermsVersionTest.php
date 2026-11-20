<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Resource\Model\TranslationInterface;

final class TermsVersionTest extends TestCase
{
    use ProphecyTrait;

    #[Test]
    public function a_terms_without_versions_has_no_latest_version(): void
    {
        self::assertNull((new Terms())->getLatestVersion());
    }

    #[Test]
    public function the_latest_version_of_a_terms_is_the_one_with_the_highest_number(): void
    {
        $terms = new Terms();
        $terms->addVersion(self::createVersion(2));
        $terms->addVersion($latestVersion = self::createVersion(3));
        $terms->addVersion(self::createVersion(1));

        self::assertSame($latestVersion, $terms->getLatestVersion());
    }

    #[Test]
    public function its_translations_are_terms_version_translations_that_know_their_version(): void
    {
        $version = new TermsVersion();
        $version->setFallbackLocale('en_US');

        $translation = $version->getTranslation('en_US');

        self::assertInstanceOf(TermsVersionTranslation::class, $translation);
        self::assertSame($version, $translation->getVersion());
    }

    #[Test]
    public function it_throws_when_its_translation_is_not_a_terms_version_translation(): void
    {
        $version = new TermsVersion();

        $translation = $this->prophesize(TranslationInterface::class);
        $translation->getLocale()->willReturn('en_US');
        $translation->setTranslatable($version)->shouldBeCalled();
        $version->addTranslation($translation->reveal());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TermsVersionTranslationInterface::class);

        $version->getTranslation('en_US');
    }

    #[Test]
    public function an_extending_model_can_create_its_own_translations(): void
    {
        $version = new class() extends TermsVersion {
            protected function createTranslation(): TermsVersionTranslationInterface
            {
                $translation = new TermsVersionTranslation();
                $translation->setLabel('I accept');

                return $translation;
            }
        };
        $version->setFallbackLocale('en_US');

        self::assertSame('I accept', $version->getTranslation('en_US')->getLabel());
    }

    #[Test]
    public function a_translation_of_something_other_than_a_version_throws_when_asked_for_its_version(): void
    {
        $translation = new TermsVersionTranslation();
        $translation->setLocale('en_US');
        $translation->setTranslatable(new Terms());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TermsVersionInterface::class);

        $translation->getVersion();
    }

    private static function createVersion(int $number): TermsVersion
    {
        $version = new TermsVersion();
        $version->setNumber($number);

        return $version;
    }
}
