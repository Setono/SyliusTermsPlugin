<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;

final class TermsVersionTest extends TestCase
{
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

    private static function createVersion(int $number): TermsVersion
    {
        $version = new TermsVersion();
        $version->setNumber($number);

        return $version;
    }
}
