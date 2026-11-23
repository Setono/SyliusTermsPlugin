<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Upgrade;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Setono\SyliusTermsPlugin\Upgrade\ContentCase;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The Terms from 2.x are inserted with DBAL as the 3.0 schema migration leaves them: form type classes in forms, no
 * version, position 0 and required at its database default. The baseline Terms have versions, at positions 0 and 1
 */
final class UpgradeFrom2xCommandTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesTermsTrait;

    private const NOW = '2026-11-18 12:00:00';

    /** Configured by default */
    private const COMPLETE_TYPE = 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType';

    /** In the table, and configured by default under its Sylius 2 class, Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType */
    private const SYLIUS_1_REGISTRATION_TYPE = 'Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType';

    /** In the table, and not configured by default */
    private const ADDRESS_TYPE = 'Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType';

    /** Name, label and content by locale, with the whitespace and HTML a 2.x textarea gave them */
    private const TEXTS = [
        'en_US' => ['EULA', 'I accept the [link:EULA]', "<p class=\"lead\">The licence</p>\r\n\r\n    <p>Indented</p>\r\n"],
        'de_DE' => ['EULA (de)', 'Ich akzeptiere die [link:EULA]', "# Lizenz\r\n- Punkt"],
        'fr_FR' => ['CLUF', 'J’accepte le [link:CLUF]', null],
    ];

    /** The TEXTS as their version holds them, with LF line breaks */
    private const VERSIONED_TEXTS = [
        'en_US' => ['EULA', 'I accept the [link:EULA]', "<p class=\"lead\">The licence</p>\n\n    <p>Indented</p>\n"],
        'de_DE' => ['EULA (de)', 'Ich akzeptiere die [link:EULA]', "# Lizenz\n- Punkt"],
        'fr_FR' => ['CLUF', 'J’accepte le [link:CLUF]', null],
    ];

    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();

        // MySQL's DATETIME has one-second precision, so the mock clock only ever moves in whole seconds
        $clock = self::mockTime(self::NOW);
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function every_terms_from_2x_enabled_or_not_gets_version_1_at_the_clocks_time_holding_every_locales_text_with_lf_line_breaks(): void
    {
        $enabledId = self::insert2xTerms('eula', [self::COMPLETE_TYPE], self::TEXTS);
        $disabledId = self::insert2xTerms('withdrawal', [], ['en_US' => self::TEXTS['en_US']], enabled: false);

        self::runCommand();

        foreach ([$enabledId => self::VERSIONED_TEXTS, $disabledId => ['en_US' => self::VERSIONED_TEXTS['en_US']]] as $id => $texts) {
            $versions = self::reloadTermsById($id)->getVersions();

            self::assertCount(1, $versions);
            $version = $versions->first();
            self::assertNotFalse($version);
            self::assertSame(1, $version->getNumber());
            self::assertSame(self::NOW, $version->getInForceFrom()?->format('Y-m-d H:i:s'));

            $versionTexts = [];

            /** @var TermsVersionTranslationInterface $translation */
            foreach ($version->getTranslations() as $translation) {
                $versionTexts[(string) $translation->getLocale()] = [$translation->getName(), $translation->getLabel(), $translation->getContent()];
            }

            ksort($versionTexts);
            ksort($texts);
            self::assertSame($texts, $versionTexts);
        }

        // required is untouched, at the database default the schema migration gave it
        self::assertSame(['eula' => true, 'withdrawal' => true], array_map(
            static fn (array $row): bool => $row['required'],
            array_intersect_key(self::fetchTermsRows(), ['eula' => true, 'withdrawal' => true]),
        ));
    }

    /**
     * Version 1 has LF line breaks, while the Terms keeps the CRLF line breaks of 2.x until the admin saves it. Neither
     * the command's own flush nor that save counts as a change of text
     */
    #[Test]
    public function a_terms_from_2x_with_crlf_content_gets_only_version_1_and_an_admin_save_of_the_same_text_adds_no_version(): void
    {
        $content = "# Licence\r\n\r\nThe licence\r\nin two lines";
        $id = self::insert2xTerms('eula', [self::COMPLETE_TYPE], ['en_US' => ['EULA', 'I accept the [link:EULA]', $content]]);

        self::runCommand();

        self::assertSame([[1, self::NOW]], self::fetchVersions()['eula']);
        self::assertSame(['en_US' => $content], self::fetchContents($id), 'The command changes no Content');

        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->loginUser(self::getAdmin(), 'admin');
        $this->clock->sleep(60);

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', $id));
        $client->submit($crawler->filter('form[name="setono_sylius_terms_terms"]')->form());

        self::assertResponseRedirects(sprintf('/admin/terms/%d/edit', $id));
        self::assertSame(['en_US' => "# Licence\n\nThe licence\nin two lines"], self::fetchContents($id));
        self::assertSame([[1, self::NOW]], self::fetchVersions()['eula']);
    }

    #[Test]
    public function the_terms_from_2x_get_positions_in_id_order_after_the_terms_that_have_a_version_which_keep_theirs(): void
    {
        self::insert2xTerms('third', []);
        self::insert2xTerms('first', []);

        // Saved in 3.0, so it has a version: Gedmo puts it last, after the baseline Terms at 0 and 1. Placed on a form
        // type class by code of its own
        $current = self::createTerms('current', [self::COMPLETE_TYPE]);
        $this->clock->sleep(60);
        $current->getTranslation('en_US')->setContent('Changed content');
        self::getEntityManager()->flush();

        self::insert2xTerms('fourth', []);

        $versions = self::fetchVersions();
        self::assertSame([[1, '2026-11-18 12:00:00'], [2, '2026-11-18 12:01:00']], $versions['current']);

        self::runCommand();

        self::assertSame(
            ['terms_and_conditions' => 0, 'privacy_policy' => 1, 'third' => 3, 'first' => 4, 'current' => 2, 'fourth' => 5],
            array_map(static fn (array $row): int => $row['position'], self::fetchTermsRows()),
        );

        // The Terms that have a version keep their versions, and their class names are mapped all the same
        self::assertSame($versions, array_diff_key(self::fetchVersions(), ['first' => true, 'third' => true, 'fourth' => true]));
        self::assertSame(['checkout_complete'], self::fetchTermsRows()['current']['forms']);
    }

    #[Test]
    public function class_names_become_codes_through_the_config_then_the_table_and_leftovers_are_kept_and_listed(): void
    {
        self::insert2xTerms('eula', [
            self::COMPLETE_TYPE,
            self::SYLIUS_1_REGISTRATION_TYPE,
            self::ADDRESS_TYPE,
            NewsletterFormKernel::NEWSLETTER_TYPE,
            'contact',
        ]);

        $display = self::runCommand();

        self::assertSame(
            ['checkout_complete', 'registration', 'checkout_address', NewsletterFormKernel::NEWSLETTER_TYPE, 'contact'],
            self::fetchTermsRows()['eula']['forms'],
        );

        self::assertStringContainsString(sprintf(
            'eula %s checkout_complete eula %s registration eula %s checkout_address',
            self::COMPLETE_TYPE,
            self::SYLIUS_1_REGISTRATION_TYPE,
            self::ADDRESS_TYPE,
        ), $display);
        self::assertStringContainsString(sprintf('eula checkout_address eula %s', NewsletterFormKernel::NEWSLETTER_TYPE), $display);
    }

    #[Test]
    public function rerunning_creates_no_versions_and_leaves_positions_alone(): void
    {
        self::insert2xTerms('eula', [self::COMPLETE_TYPE, NewsletterFormKernel::NEWSLETTER_TYPE], self::TEXTS);
        self::insert2xTerms('withdrawal', [self::ADDRESS_TYPE]);

        self::runCommand();
        $terms = self::fetchTermsRows();
        $versions = self::fetchVersions();

        $this->clock->sleep(60);
        $display = self::runCommand();

        self::assertSame($terms, self::fetchTermsRows());
        self::assertSame($versions, self::fetchVersions());
        self::assertStringContainsString('Every Terms has a version already.', $display);
        self::assertStringContainsString('No Terms is placed on a form type class that becomes a Form code.', $display);
        self::assertStringContainsString('No Content of a Terms from 2.x may render differently.', $display);
        self::assertStringContainsString('[OK] There is nothing to upgrade.', $display);

        // Still listed, until they are configured or unticked
        self::assertStringContainsString(sprintf('eula %s withdrawal checkout_address', NewsletterFormKernel::NEWSLETTER_TYPE), $display);
    }

    #[Test]
    public function a_terms_from_2x_that_a_rerun_finds_is_numbered_after_the_terms_the_first_run_numbered(): void
    {
        self::insert2xTerms('eula', []);
        self::insert2xTerms('withdrawal', []);
        self::runCommand();

        self::insert2xTerms('late', []);
        self::runCommand();

        self::assertSame(
            ['terms_and_conditions' => 0, 'privacy_policy' => 1, 'eula' => 2, 'withdrawal' => 3, 'late' => 4],
            array_map(static fn (array $row): int => $row['position'], self::fetchTermsRows()),
        );
    }

    #[Test]
    public function after_a_form_is_added_to_the_config_a_rerun_maps_the_matching_leftover_class_name(): void
    {
        self::insert2xTerms('newsletter_consent', [NewsletterFormKernel::NEWSLETTER_TYPE, self::ADDRESS_TYPE]);

        self::runCommand();
        self::assertSame([NewsletterFormKernel::NEWSLETTER_TYPE, 'checkout_address'], self::fetchTermsRows()['newsletter_consent']['forms']);

        self::ensureKernelShutdown();
        static::$class = NewsletterFormKernel::class;
        self::bootKernel();

        $display = self::runCommand();

        self::assertSame(['newsletter', 'checkout_address'], self::fetchTermsRows()['newsletter_consent']['forms']);
        self::assertStringContainsString(sprintf('newsletter_consent %s newsletter', NewsletterFormKernel::NEWSLETTER_TYPE), $display);
        self::assertStringContainsString('[OK] The Terms are upgraded.', $display);
    }

    #[Test]
    public function a_dry_run_writes_nothing_and_prints_the_same_plan(): void
    {
        self::insert2xTerms('eula', [self::COMPLETE_TYPE, self::ADDRESS_TYPE], self::TEXTS);
        self::insert2xTerms('withdrawal', [NewsletterFormKernel::NEWSLETTER_TYPE], enabled: false);

        $terms = self::fetchTermsRows();
        $versions = self::fetchVersions();

        $dryRunDisplay = self::runCommand(['--dry-run' => true]);

        self::assertSame($terms, self::fetchTermsRows());
        self::assertSame($versions, self::fetchVersions());
        self::assertStringContainsString('[NOTE] This was a dry run, so nothing was written.', $dryRunDisplay);

        $display = self::runCommand();

        self::assertNotSame($terms, self::fetchTermsRows());
        self::assertSame(strstr($display, ' [OK]', true), strstr($dryRunDisplay, ' ! [NOTE]', true));
        self::assertStringContainsString('eula 2 withdrawal 3', $display);
    }

    #[Test]
    public function the_output_lists_the_content_cases_per_terms_and_locale(): void
    {
        self::insert2xTerms('eula', [], self::TEXTS);
        self::insert2xTerms('withdrawal', [], ['en_US' => ['Withdrawal', 'I accept the [link:withdrawal terms]', '<p>Clean</p>']]);

        $display = self::runCommand();

        self::assertStringContainsString(sprintf(
            'eula de_DE %s eula en_US %s eula en_US %s',
            ContentCase::ListOrHeading->describe(),
            ContentCase::IndentedCode->describe(),
            ContentCase::ClassOrStyle->describe(),
        ), $display);
        self::assertStringNotContainsString('eula fr_FR', $display);
        self::assertStringNotContainsString('withdrawal en_US', $display);

        // The Content is as it was
        self::assertSame(self::TEXTS['en_US'][2], self::reloadTermsById(self::fetchTermsRows()['eula']['id'])->getTranslation('en_US')->getContent());
    }

    #[Test]
    public function after_the_command_checkout_complete_shows_the_terms_from_2x_but_not_to_an_order_completed_before(): void
    {
        $channel = self::createChannel('TEST_WEB');
        self::insert2xTerms('withdrawal', [self::COMPLETE_TYPE], channels: [$channel]);
        self::insert2xTerms('eula', [self::COMPLETE_TYPE, self::ADDRESS_TYPE], self::TEXTS, channels: [$channel]);
        self::insert2xTerms('disabled', [self::COMPLETE_TYPE], enabled: false, channels: [$channel]);

        // Until the command runs, a Terms from 2.x has no version in force, and its Forms are form type classes
        self::assertSame([], self::lookup($channel));

        self::runCommand();

        $translations = self::lookup($channel);
        self::assertSame(['withdrawal', 'eula'], array_map(static fn (TermsVersionTranslationInterface $translation): ?string => $translation->getVersion()->getTerms()?->getCode(), $translations));
        self::assertSame([1, 1], array_map(static fn (TermsVersionTranslationInterface $translation): ?int => $translation->getVersion()->getNumber(), $translations));

        // An order completed a second before the command ran has no inferred Terms version
        self::assertSame([], self::lookup($channel, new \DateTimeImmutable('2026-11-18 11:59:59')));
    }

    /**
     * Inserts a Terms as 2.x left it after the 3.0 schema migration: no version, position 0, and required left to its
     * database default
     *
     * @param list<string> $forms
     * @param array<string, array{string, string, string|null}> $translations name, label and content by locale
     * @param list<ChannelInterface> $channels
     *
     * @return int the Terms' id
     */
    private static function insert2xTerms(string $code, array $forms, ?array $translations = null, bool $enabled = true, array $channels = []): int
    {
        $connection = self::getConnection();

        $connection->insert('setono_sylius_terms__terms', [
            'code' => $code,
            'enabled' => (int) $enabled,
            'position' => 0,
            'forms' => json_encode($forms, \JSON_THROW_ON_ERROR),
            'created_at' => '2024-01-01 00:00:00',
        ]);
        $id = (int) $connection->lastInsertId();

        foreach ($translations ?? ['en_US' => [ucfirst($code), sprintf('I accept the [link:%s]', $code), sprintf('The content of %s', $code)]] as $locale => [$name, $label, $content]) {
            $connection->insert('setono_sylius_terms__terms_translation', [
                'translatable_id' => $id,
                'locale' => $locale,
                'name' => $name,
                'slug' => str_replace('_', '-', $code),
                'label' => $label,
                'content' => $content,
            ]);
        }

        foreach ($channels as $channel) {
            $connection->insert('setono_sylius_terms__terms_channels', ['terms_id' => $id, 'channel_id' => $channel->getId()]);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return string the output, with the line breaks and padding of wrapped text and table cells collapsed into single spaces
     */
    private static function runCommand(array $input = []): string
    {
        // The booted kernel, whose entity manager the test shares. Symfony 6.4 and 7.4 type KernelTestCase::$kernel differently
        $kernel = self::getContainer()->get('kernel');
        self::assertInstanceOf(KernelInterface::class, $kernel);

        $tester = new CommandTester((new Application($kernel))->find('setono:sylius-terms:upgrade-from-2x'));
        $tester->execute($input);
        $tester->assertCommandIsSuccessful();

        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    /**
     * Read with DBAL, past the entity manager
     *
     * @return array<string, array{id: int, required: bool, position: int, forms: list<string>|null}> by code, in id order
     *
     * @phpstan-impure
     */
    private static function fetchTermsRows(): array
    {
        $rows = [];

        /** @var array{id: int|string, code: string, required: int|string, position: int|string, forms: string|null} $row */
        foreach (self::getConnection()->fetchAllAssociative('SELECT id, code, required, position, forms FROM setono_sylius_terms__terms ORDER BY id') as $row) {
            /** @var list<string>|null $forms */
            $forms = null === $row['forms'] ? null : json_decode($row['forms'], true, 512, \JSON_THROW_ON_ERROR);

            $rows[$row['code']] = [
                'id' => (int) $row['id'],
                'required' => (bool) $row['required'],
                'position' => (int) $row['position'],
                'forms' => $forms,
            ];
        }

        return $rows;
    }

    /**
     * Read with DBAL, past the entity manager
     *
     * @return array<string, list<array{int, string}>> each Terms' versions' numbers and in force from, by code
     *
     * @phpstan-impure
     */
    private static function fetchVersions(): array
    {
        $versions = [];

        /** @var array{code: string, number: int|string, in_force_from: string} $row */
        foreach (self::getConnection()->fetchAllAssociative('SELECT terms.code, version.number, version.in_force_from FROM setono_sylius_terms__terms_version version INNER JOIN setono_sylius_terms__terms terms ON terms.id = version.terms_id ORDER BY terms.code, version.number') as $row) {
            $versions[$row['code']][] = [(int) $row['number'], $row['in_force_from']];
        }

        return $versions;
    }

    /**
     * Read with DBAL, past the entity manager
     *
     * @return array<string, string|null> the Terms' Content by locale
     *
     * @phpstan-impure
     */
    private static function fetchContents(int $id): array
    {
        /** @var array<string, string|null> $contents */
        $contents = self::getConnection()->fetchAllKeyValue(
            'SELECT locale, content FROM setono_sylius_terms__terms_translation WHERE translatable_id = ? ORDER BY locale',
            [$id],
        );

        return $contents;
    }

    private static function getAdmin(): AdminUserInterface
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');

        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        return $admin;
    }

    /**
     * @return list<TermsVersionTranslationInterface>
     *
     * @phpstan-impure
     */
    private static function lookup(ChannelInterface $channel, ?\DateTimeImmutable $at = null): array
    {
        /** @var FormTermsLookupInterface $lookup */
        $lookup = self::getContainer()->get(FormTermsLookupInterface::class);

        return $lookup->lookup('checkout_complete', $channel, 'en_US', $at);
    }

    private static function reloadTermsById(int $id): TermsInterface
    {
        self::getEntityManager()->clear();

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $terms = $repository->find($id);
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    private static function getConnection(): Connection
    {
        return self::getEntityManager()->getConnection();
    }
}
