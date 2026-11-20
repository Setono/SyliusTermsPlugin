<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Setono\SyliusTermsPlugin\Command\UpgradeFrom2xCommand;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Upgrade\ContentCase;
use Setono\SyliusTermsPlugin\Upgrade\TermsUpgrade;
use Setono\SyliusTermsPlugin\Upgrade\UpgradePlan;
use Setono\SyliusTermsPlugin\Upgrade\UpgraderInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class UpgradeFrom2xCommandTest extends TestCase
{
    use ProphecyTrait;

    private const COMPLETE_TYPE = 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType';

    private const ADDRESS_TYPE = 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType';

    /** @var ObjectProphecy<UpgraderInterface> */
    private ObjectProphecy $upgrader;

    protected function setUp(): void
    {
        $this->upgrader = $this->prophesize(UpgraderInterface::class);
    }

    #[Test]
    public function it_is_named_and_has_a_dry_run_option(): void
    {
        $command = new UpgradeFrom2xCommand($this->upgrader->reveal());

        self::assertSame('setono:sylius-terms:upgrade-from-2x', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('dry-run'));
    }

    #[Test]
    public function it_prints_the_plan_and_applies_it(): void
    {
        $plan = self::createPlan();
        $this->upgrader->plan()->willReturn($plan);
        $this->upgrader->apply($plan)->shouldBeCalledOnce();

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();

        // newsletter_consent has a version, so it is in neither the versions nor the positions
        $expected = sprintf(
            <<<'DISPLAY'
                Upgrading the Terms from 2.x
                ============================

                Terms versions and positions

                 These Terms have no version, so they are 2.x data. Each gets version 1, in force from 2026-11-18 12:00:00 UTC, and a position.
                  Terms                  Position
                  terms_and_conditions   0
                  privacy_policy         1

                Forms

                 These form type classes become Form codes.
                  Terms                  Form type class   Form code
                  terms_and_conditions   %1$s              checkout_complete
                  terms_and_conditions   %2$s              checkout_address

                Forms that aren't configured

                 These Terms are placed on Forms that aren't configured, so they show on none of them. The admin shows each as "(not configured)".
                 Configure a Form for each one in setono_sylius_terms.forms, or untick it on the Terms in the admin. For a form type class, configure a Form with that type, then run the command again to map it.
                  Terms                  Form
                  terms_and_conditions   checkout_address
                  newsletter_consent     App\Form\Type\NewsletterType

                Content that may render differently

                 Content is Markdown that allows HTML in 3.0, so this Content of Terms from 2.x may render differently. Check it on the Terms pages, and edit it where needed.
                 The command changes no Content.
                  Terms                  Locale   Case
                  terms_and_conditions   de_DE    %3$s
                  terms_and_conditions   de_DE    %4$s
                  terms_and_conditions   en_US    %5$s

                 [OK] The Terms are upgraded.
                DISPLAY,
            self::COMPLETE_TYPE,
            self::ADDRESS_TYPE,
            ContentCase::ListOrHeading->describe(),
            ContentCase::ClassOrStyle->describe(),
            ContentCase::IndentedCode->describe(),
        );

        self::assertSame(self::normalize($expected), self::normalize($tester->getDisplay()));
    }

    #[Test]
    public function a_dry_run_prints_the_same_plan_and_writes_nothing(): void
    {
        $plan = self::createPlan();
        $this->upgrader->plan()->willReturn($plan);

        // By the real run only
        $this->upgrader->apply(Argument::any())->shouldBeCalledOnce();

        $display = $this->execute()->getDisplay();
        $dryRunDisplay = $this->execute(['--dry-run' => true])->getDisplay();

        self::assertStringContainsString('[NOTE] This was a dry run, so nothing was written.', self::normalize($dryRunDisplay));
        self::assertSame(strstr($display, ' [OK]', true), strstr($dryRunDisplay, ' ! [NOTE]', true));
    }

    #[Test]
    public function it_writes_nothing_when_there_is_nothing_to_upgrade(): void
    {
        $plan = new UpgradePlan(new \DateTimeImmutable('2026-11-18 12:00:00 UTC'), [
            new TermsUpgrade(self::createTerms('newsletter_consent'), null, [], ['contact'], [], []),
        ]);
        $this->upgrader->plan()->willReturn($plan);
        $this->upgrader->apply(Argument::any())->shouldNotBeCalled();

        $tester = $this->execute();

        $tester->assertCommandIsSuccessful();

        $expected = <<<'DISPLAY'
            Upgrading the Terms from 2.x
            ============================

            Terms versions and positions

             Every Terms has a version already.

            Forms

             No Terms is placed on a form type class that becomes a Form code.

            Forms that aren't configured

             Every Terms is placed on configured Forms only.

            Content that may render differently

             No Content of a Terms from 2.x may render differently.

             [OK] There is nothing to upgrade.
            DISPLAY;

        self::assertSame(self::normalize($expected), self::normalize($tester->getDisplay()));
    }

    /**
     * Two Terms from 2.x and one that has a version
     */
    private static function createPlan(): UpgradePlan
    {
        return new UpgradePlan(new \DateTimeImmutable('2026-11-18 12:00:00 UTC'), [
            new TermsUpgrade(
                self::createTerms('terms_and_conditions'),
                0,
                [self::COMPLETE_TYPE => 'checkout_complete', self::ADDRESS_TYPE => 'checkout_address'],
                ['checkout_complete', 'checkout_address'],
                ['checkout_address'],
                [
                    'de_DE' => [ContentCase::ListOrHeading, ContentCase::ClassOrStyle],
                    'en_US' => [ContentCase::IndentedCode],
                ],
            ),
            new TermsUpgrade(self::createTerms('privacy_policy'), 1, [], [], [], []),
            new TermsUpgrade(self::createTerms('newsletter_consent'), null, [], ['App\Form\Type\NewsletterType'], ['App\Form\Type\NewsletterType'], []),
        ]);
    }

    private static function createTerms(string $code): TermsInterface
    {
        $terms = new Terms();
        $terms->setCode($code);

        return $terms;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(array $input = []): CommandTester
    {
        $tester = new CommandTester(new UpgradeFrom2xCommand($this->upgrader->reveal()));
        $tester->execute($input);

        return $tester;
    }

    /**
     * Drops the lines of dashes that underline the sections and border the tables, and collapses the line breaks and
     * padding of wrapped text and table cells into single spaces
     */
    private static function normalize(string $display): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) preg_replace('/^[ -]*-[ -]*$/m', '', $display)));
    }
}
