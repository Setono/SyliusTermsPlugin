<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Upgrade;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Upgrade\TermsUpgrade;
use Setono\SyliusTermsPlugin\Upgrade\UpgradePlan;

final class TermsUpgradeTest extends TestCase
{
    /**
     * @param array<string, string> $mappedForms
     */
    #[Test]
    #[DataProvider('provideTermsUpgrades')]
    public function it_writes_a_terms_from_2x_and_a_terms_with_mapped_forms(?int $position, array $mappedForms, bool $from2x, bool $hasWrites): void
    {
        $termsUpgrade = self::createTermsUpgrade($position, $mappedForms);

        self::assertSame($from2x, $termsUpgrade->isFrom2x());
        self::assertSame($hasWrites, $termsUpgrade->hasWrites());
        self::assertSame($hasWrites, (new UpgradePlan(new \DateTimeImmutable(), [$termsUpgrade]))->hasWrites());
    }

    /**
     * @return \Generator<string, array{int|null, array<string, string>, bool, bool}>
     */
    public static function provideTermsUpgrades(): \Generator
    {
        $mappedForms = ['App\Form\Type\NewsletterType' => 'newsletter'];

        yield 'from 2.x' => [0, [], true, true];
        yield 'from 2.x with mapped forms' => [3, $mappedForms, true, true];
        yield 'with a version and mapped forms' => [null, $mappedForms, false, true];
        yield 'with a version and no mapped forms' => [null, [], false, false];
    }

    #[Test]
    public function the_plan_lists_the_terms_from_2x_in_order(): void
    {
        $withVersion = self::createTermsUpgrade(null, []);
        $first = self::createTermsUpgrade(0, []);
        $second = self::createTermsUpgrade(1, []);

        $plan = new UpgradePlan(new \DateTimeImmutable(), [$withVersion, $first, $withVersion, $second]);

        self::assertSame([$first, $second], $plan->getTermsUpgradesFrom2x());
    }

    #[Test]
    public function a_plan_without_terms_writes_nothing(): void
    {
        self::assertFalse((new UpgradePlan(new \DateTimeImmutable(), []))->hasWrites());
    }

    /**
     * @param array<string, string> $mappedForms
     */
    private static function createTermsUpgrade(?int $position, array $mappedForms): TermsUpgrade
    {
        return new TermsUpgrade(new Terms(), $position, $mappedForms, array_values($mappedForms), [], []);
    }
}
