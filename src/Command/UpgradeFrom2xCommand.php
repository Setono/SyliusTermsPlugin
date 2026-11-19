<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Command;

use Setono\SyliusTermsPlugin\Upgrade\UpgradePlan;
use Setono\SyliusTermsPlugin\Upgrade\UpgraderInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shipped in 3.0, and removed in 4.0, which upgrades from 3.x only
 */
#[AsCommand(
    name: 'setono:sylius-terms:upgrade-from-2x',
    description: 'Upgrades the Terms in a 2.x database to 3.0. Run it after the schema migration',
)]
final class UpgradeFrom2xCommand extends Command
{
    public function __construct(private readonly UpgraderInterface $upgrader)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what the command would do, and write nothing')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command upgrades the Terms in a 2.x database to 3.0. Run it after the schema migration, in the same deploy, before traffic. Until it runs, no Terms checkboxes show.

                  * Each Terms without a version is 2.x data. It gets version 1, in force from now, and a position after the Terms that have a version, in id order.
                  * The form type classes the Terms are placed on become Form codes: the code of the configured Form with that type, else the code in the table of Sylius 1 form types. Anything else stays as it is.
                  * It lists the Forms that aren't configured, and each Terms and locale whose Content may render differently in 3.0. It changes no Content.

                All writes happen in one transaction. The command is safe to run again, for example after configuring another Form.

                See what it would do first, without writing anything:

                  <info>php %command.full_name% --dry-run</info>
                HELP)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Upgrading the Terms from 2.x');

        $plan = $this->upgrader->plan();

        self::printVersionsAndPositions($io, $plan);
        self::printMappedForms($io, $plan);
        self::printUnconfiguredForms($io, $plan);
        self::printContentCases($io, $plan);

        if (true === $input->getOption('dry-run')) {
            $io->note('This was a dry run, so nothing was written. Run the command without --dry-run to upgrade.');

            return self::SUCCESS;
        }

        if (!$plan->hasWrites()) {
            $io->success('There is nothing to upgrade.');

            return self::SUCCESS;
        }

        $this->upgrader->apply($plan);

        $io->success('The Terms are upgraded.');

        return self::SUCCESS;
    }

    private static function printVersionsAndPositions(SymfonyStyle $io, UpgradePlan $plan): void
    {
        $io->section('Terms versions and positions');

        $rows = [];
        foreach ($plan->getTermsUpgradesFrom2x() as $termsUpgrade) {
            $rows[] = [$termsUpgrade->terms->getCode(), $termsUpgrade->position];
        }

        if ([] === $rows) {
            $io->text('Every Terms has a version already.');

            return;
        }

        $io->text(sprintf(
            'These Terms have no version, so they are 2.x data. Each gets version 1, in force from %s, and a position.',
            $plan->inForceFrom->format('Y-m-d H:i:s T'),
        ));
        $io->table(['Terms', 'Position'], $rows);
    }

    private static function printMappedForms(SymfonyStyle $io, UpgradePlan $plan): void
    {
        $io->section('Forms');

        $rows = [];
        foreach ($plan->termsUpgrades as $termsUpgrade) {
            foreach ($termsUpgrade->mappedForms as $class => $code) {
                $rows[] = [$termsUpgrade->terms->getCode(), $class, $code];
            }
        }

        if ([] === $rows) {
            $io->text('No Terms is placed on a form type class that becomes a Form code.');

            return;
        }

        $io->text('These form type classes become Form codes.');
        $io->table(['Terms', 'Form type class', 'Form code'], $rows);
    }

    private static function printUnconfiguredForms(SymfonyStyle $io, UpgradePlan $plan): void
    {
        $io->section('Forms that aren\'t configured');

        $rows = [];
        foreach ($plan->termsUpgrades as $termsUpgrade) {
            foreach ($termsUpgrade->unconfiguredForms as $form) {
                $rows[] = [$termsUpgrade->terms->getCode(), $form];
            }
        }

        if ([] === $rows) {
            $io->text('Every Terms is placed on configured Forms only.');

            return;
        }

        $io->text([
            'These Terms are placed on Forms that aren\'t configured, so they show on none of them. The admin shows each as "(not configured)".',
            'Configure a Form for each one in setono_sylius_terms.forms, or untick it on the Terms in the admin. For a form type class, configure a Form with that type, then run the command again to map it.',
        ]);
        $io->table(['Terms', 'Form'], $rows);
    }

    private static function printContentCases(SymfonyStyle $io, UpgradePlan $plan): void
    {
        $io->section('Content that may render differently');

        $rows = [];
        foreach ($plan->termsUpgrades as $termsUpgrade) {
            foreach ($termsUpgrade->contentCases as $locale => $cases) {
                foreach ($cases as $case) {
                    $rows[] = [$termsUpgrade->terms->getCode(), $locale, $case->describe()];
                }
            }
        }

        if ([] === $rows) {
            $io->text('No Content of a Terms from 2.x may render differently.');

            return;
        }

        $io->text([
            'Content is Markdown that allows HTML in 3.0, so this Content of Terms from 2.x may render differently. Check it on the Terms pages, and edit it where needed.',
            'The command changes no Content.',
        ]);
        $io->table(['Terms', 'Locale', 'Case'], $rows);
    }
}
