<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\HookableComponent;
use Sylius\TwigHooks\Registry\HookablesRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class TermsAdminPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use CreatesTermsTrait;
    use InteractsWithLiveComponents;

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_admin_hooks_are_registered_under_the_plugins_hook_prefix(): void
    {
        self::bootKernel();

        /** @var HookablesRegistry $registry */
        $registry = self::getContainer()->get('sylius_twig_hooks.registry.hookables');

        foreach (['create', 'update'] as $page) {
            // The prefixes core's CRUD templates pass for a route with hook_prefix: setono_sylius_terms.admin.terms
            $prefixes = static fn (string $hook): array => [
                sprintf('setono_sylius_terms.admin.terms.%s.%s', $page, $hook),
                sprintf('sylius_admin.terms.%s.%s', $page, $hook),
                sprintf('sylius_admin.common.%s.%s', $page, $hook),
            ];

            $form = self::getHookable($registry->getEnabledFor($prefixes('content')), 'form');
            self::assertInstanceOf(HookableComponent::class, $form);
            self::assertSame('setono_sylius_terms:admin:terms:form', $form->component);

            self::assertSame(['general', 'placement', 'translations'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form'))));
            self::assertSame(['code', 'enabled', 'required', 'position'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form.general'))));
            self::assertSame(['forms', 'channels'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form.placement'))));
            self::assertSame(['name', 'slug', 'label', 'content'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form.translations'))));
        }

        $updatePrefixes = static fn (string $hook): array => [
            'setono_sylius_terms.admin.terms.update.' . $hook,
            'sylius_admin.terms.update.' . $hook,
            'sylius_admin.common.update.' . $hook,
        ];
        // The Versions card sits below the Live form and outside it
        self::assertSame(['flashes', 'header', 'form_error_alert', 'form', 'versions', 'footer'], self::getHookableNames($registry->getEnabledFor($updatePrefixes('content'))));
        self::assertSame(['view_in_store', 'show', 'cancel', 'update'], self::getHookableNames($registry->getEnabledFor($updatePrefixes('content.header.title_block.actions'))));

        // The prefixes core's show template passes for a route with hook_prefix: setono_sylius_terms.admin.terms_version
        $versionPrefixes = static fn (string $hook): array => [
            'setono_sylius_terms.admin.terms_version.show.' . $hook,
            'sylius_admin.terms_version.show.' . $hook,
            'sylius_admin.common.show.' . $hook,
        ];
        self::assertSame(['flashes', 'header', 'sections', 'footer'], self::getHookableNames($registry->getEnabledFor($versionPrefixes('content'))));
        self::assertSame(['translations'], self::getHookableNames($registry->getEnabledFor($versionPrefixes('content.sections'))));
        self::assertSame(['name', 'label', 'content'], self::getHookableNames($registry->getEnabledFor($versionPrefixes('content.sections.translations'))));
    }

    #[Test]
    public function the_update_page_lists_the_versions_newest_first(): void
    {
        self::mockTime('2026-11-16 12:00:00');
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', localeCodes: ['en_US', 'de_DE']);

        self::mockTime('2026-11-17 09:30:00');
        $terms->getTranslation('de_DE')->setContent('Der neue Inhalt');
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        $card = $crawler->filter('[data-test-terms-versions]');
        self::assertNotContains('form', $card->ancestors()->each(static fn (Crawler $ancestor): string => $ancestor->nodeName()), 'The Versions card is outside the form');
        self::assertSame('Versions', trim($card->filter('.card-title')->text()));

        $rows = $card->filter('tbody tr');
        self::assertSame(['2', '1'], $rows->each(static fn (Crawler $row): ?string => $row->attr('data-test-terms-version')));
        self::assertStringContainsString('Nov 17, 2026', $rows->eq(0)->text());
        self::assertStringContainsString('Nov 16, 2026', $rows->eq(1)->text());
        self::assertStringContainsString('English (United States), German (Germany)', $rows->eq(0)->text());
        self::assertSame(
            sprintf('/admin/terms/%d/versions/2', (int) $terms->getId()),
            $rows->eq(0)->filter('[data-test-show-terms-version]')->attr('href'),
        );
    }

    #[Test]
    public function the_version_page_shows_the_rendered_content_and_the_source_for_each_locale(): void
    {
        self::mockTime('2026-11-16 12:00:00');
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', localeCodes: ['de_DE', 'en_US']);
        $sources = [
            'en_US' => "## Cookies\n\nWe use **cookies**.<script>alert('en')</script>",
            'de_DE' => "## Cookies\n\nWir verwenden **Cookies**.<script>alert('de')</script>",
        ];

        self::mockTime('2026-11-17 09:30:00');
        foreach ($sources as $localeCode => $source) {
            $terms->getTranslation($localeCode)->setContent($source);
        }
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/versions/2', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'Version 2');
        self::assertSelectorTextContains('[data-test-terms-version-title]', 'In force since November 17, 2026');
        self::assertCount(1, $crawler->filter('link[data-test-terms-content-stylesheet]'));
        // The admin's locale first, then the others by name
        self::assertSame(['en_US', 'de_DE'], $crawler->filter('[data-test-terms-version-translations-accordion]')->each(static fn (Crawler $header): ?string => $header->attr('data-test-terms-version-translations-accordion')));
        self::assertSame(
            sprintf('/admin/terms/%d/edit', (int) $terms->getId()),
            $crawler->filter('.breadcrumb a')->reduce(static fn (Crawler $link): bool => 'cookie_policy (en_US)' === trim($link->text()))->attr('href'),
        );

        foreach ($sources as $localeCode => $source) {
            self::assertSame(sprintf('cookie_policy (%s)', $localeCode), trim($crawler->filter(sprintf('[data-test-name="%s"]', $localeCode))->text()));
            self::assertSame('I accept the [link:cookie_policy]', trim($crawler->filter(sprintf('[data-test-label="%s"]', $localeCode))->text()));

            $preview = $crawler->filter(sprintf('[data-test-content-preview="%s"]', $localeCode));
            self::assertSame('content-cookies', $preview->filter('h2')->attr('id'));
            self::assertStringStartsWith('Cookies', trim($preview->filter('h2')->text()));
            self::assertSame('cookies', strtolower(trim($preview->filter('strong')->text())));
            self::assertCount(0, $preview->filter('script'), 'The preview is sanitised');

            self::assertSame($source, $crawler->filter(sprintf('[data-test-content-source="%s"]', $localeCode))->text(normalizeWhitespace: false));
        }

        // A version shows its own text, not the Terms' current text
        $crawler = $client->request('GET', sprintf('/admin/terms/%d/versions/1', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'Version 1');
        self::assertSame('The content of cookie_policy', $crawler->filter('[data-test-content-source="en_US"]')->text());
    }

    #[Test]
    public function the_version_page_says_when_a_locale_has_no_content(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', hasContent: false);
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/versions/1', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        self::assertSame('No content', trim($crawler->filter('[data-test-no-content="en_US"]')->text()));
        self::assertCount(0, $crawler->filter('[data-test-content-preview]'));
    }

    #[Test]
    public function the_version_page_is_not_found_for_a_number_the_terms_does_not_have(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy');
        $other = self::findTerms('terms_and_conditions');

        $client->request('GET', sprintf('/admin/terms/%d/versions/2', (int) $terms->getId()));
        self::assertResponseStatusCodeSame(404);

        // Every Terms has a version 1, but this one is found by the Terms in the path
        $client->request('GET', sprintf('/admin/terms/%d/versions/1', (int) $other->getId()));
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.breadcrumb', 'Terms and Conditions');
    }

    #[Test]
    public function view_in_store_points_at_the_terms_page(): void
    {
        $client = self::createAdminClient();
        $terms = self::findTerms('terms_and_conditions');

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('a[data-test-view-in-store]');
        self::assertSame('View in store', trim($link->text()));
        // FASHION_WEB's hostname is localhost, its default locale en_US, and Sylius' URLs are unsecured by default
        $url = 'http://localhost/en_US/terms/terms-and-conditions';
        self::assertSame($url, $link->attr('href'));
        self::assertStringNotContainsString('disabled', (string) $link->attr('class'));

        // The link leads to the Terms page
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1[data-test-terms-name]', 'Terms and Conditions');
    }

    #[Test]
    public function view_in_store_has_an_entry_per_enabled_channel_the_terms_is_in(): void
    {
        $client = self::createAdminClient();
        $second = self::createChannel('SECOND_WEB');
        $second->setHostname('second.example.com');
        // The Terms has no de_DE translation, so it has no page in this channel's default locale
        $german = self::createChannel('GERMAN_WEB');
        $german->setDefaultLocale(self::getLocale('de_DE'));
        $disabled = self::createChannel('DISABLED_WEB');
        $disabled->setEnabled(false);
        $terms = self::createTerms('cookie_policy', channels: [self::getChannel('FASHION_WEB'), $second, $german, $disabled]);
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        $entries = $crawler->filter('[data-test-view-in-store] a[data-test-channel-code]');
        self::assertSame(
            ['FASHION_WEB', 'SECOND_WEB', 'GERMAN_WEB'],
            $entries->each(static fn (Crawler $entry): ?string => $entry->attr('data-test-channel-code')),
        );
        self::assertSame(
            ['http://localhost/en_US/terms/cookie-policy', 'http://second.example.com/en_US/terms/cookie-policy', '#'],
            $entries->each(static fn (Crawler $entry): ?string => $entry->attr('href')),
        );
        self::assertStringContainsString('disabled', (string) $entries->eq(2)->attr('class'));
    }

    /**
     * @param list<string> $channelCodes
     * @param list<string> $localeCodes
     */
    #[Test]
    #[DataProvider('provideTermsWithoutATermsPage')]
    public function view_in_store_is_disabled_without_a_terms_page_to_show(array $channelCodes, array $localeCodes, bool $enabled, bool $hasContent): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms(
            'cookie_policy',
            channels: array_map(self::getChannel(...), $channelCodes),
            localeCodes: $localeCodes,
            enabled: $enabled,
            hasContent: $hasContent,
        );
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        $link = $crawler->filter('a[data-test-view-in-store]');
        self::assertCount(1, $link);
        self::assertStringContainsString('disabled', (string) $link->attr('class'));
        self::assertSame('#', $link->attr('href'));
    }

    /**
     * The cases where the Terms page in FASHION_WEB's default locale, en_US, would be not found
     *
     * @return \Generator<string, array{channelCodes: list<string>, localeCodes: list<string>, enabled: bool, hasContent: bool}>
     */
    public static function provideTermsWithoutATermsPage(): \Generator
    {
        yield 'disabled' => ['channelCodes' => ['FASHION_WEB'], 'localeCodes' => ['en_US'], 'enabled' => false, 'hasContent' => true];
        yield 'in no channel' => ['channelCodes' => [], 'localeCodes' => ['en_US'], 'enabled' => true, 'hasContent' => true];
        yield "not translated into the channel's default locale" => ['channelCodes' => ['FASHION_WEB'], 'localeCodes' => ['de_DE'], 'enabled' => true, 'hasContent' => true];
        yield 'no Content in the default locale' => ['channelCodes' => ['FASHION_WEB'], 'localeCodes' => ['en_US'], 'enabled' => true, 'hasContent' => false];
    }

    #[Test]
    public function the_index_lists_the_terms_with_their_name_in_the_admins_locale(): void
    {
        $client = self::createAdminClient();
        self::createTerms('german_only', forms: ['checkout_complete', 'newsletter'], localeCodes: ['de_DE']);
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', '/admin/terms/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'Terms');

        $rows = $crawler->filter('table tbody tr');
        self::assertSame(['terms_and_conditions', 'privacy_policy', 'german_only'], $rows->filter('td:nth-child(2) code')->each(static fn (Crawler $code): string => trim($code->text())));

        self::assertStringContainsString('Terms and Conditions', $rows->eq(0)->text());
        self::assertStringContainsString('Checkout > Complete order', $rows->eq(0)->text());
        self::assertStringContainsString('Fashion Web Store', $rows->eq(0)->text());

        // The Terms has no en_US translation, and one of its Form codes isn't configured
        self::assertCount(1, $rows->eq(2)->filter('[data-test-missing-translation-paragraph]'));
        self::assertStringContainsString('newsletter (not configured)', $rows->eq(2)->text());
    }

    #[Test]
    public function the_create_page_shows_the_three_cards(): void
    {
        $client = self::createAdminClient();

        $crawler = $client->request('GET', '/admin/terms/new');

        self::assertResponseIsSuccessful();
        self::assertSame(['General', 'Placement', 'Translations'], $crawler->filter('form .card-title')->each(static fn (Crawler $title): string => trim($title->text())));
        self::assertSelectorTextContains('h1.page-title', 'New terms');

        self::assertCount(1, $crawler->filter('input[data-test-required][checked]'), 'Required is ticked for a new Terms');
        self::assertCount(3, $crawler->filter('input[data-test-form-code]'));
        self::assertCount(1, $crawler->filter('input[data-test-channel-code="FASHION_WEB"]'));

        $wand = $crawler->filter('button[data-test-generate-slug-button="en_US"]');
        self::assertCount(1, $wand);
        self::assertSame('live#action', $wand->attr('data-action'));
        self::assertSame('generateTermsSlug', $wand->attr('data-live-action-param'));
        self::assertSame('en_US', $wand->attr('data-live-locale-code-param'));
    }

    #[Test]
    public function creating_terms_fills_a_blank_slug_from_the_name_and_redirects_to_the_update_page(): void
    {
        $client = self::createAdminClient();
        $crawler = $client->request('GET', '/admin/terms/new');

        $form = $crawler->filter('form[name="setono_sylius_terms_terms"]')->form();
        $client->submit($form, [
            'setono_sylius_terms_terms[code]' => 'cookie_policy',
            'setono_sylius_terms_terms[position]' => '',
            'setono_sylius_terms_terms[translations][en_US][name]' => 'Cookie Policy',
            'setono_sylius_terms_terms[translations][en_US][slug]' => '',
            'setono_sylius_terms_terms[translations][en_US][label]' => 'I accept the [link:cookie policy]',
        ]);

        $terms = self::findTerms('cookie_policy');
        self::assertResponseRedirects(sprintf('/admin/terms/%d/edit', (int) $terms->getId()));
        self::assertSame('cookie-policy', $terms->getTranslation('en_US')->getSlug());
        self::assertTrue($terms->isRequired());
        self::assertSame(2, $terms->getPosition(), 'A blank position puts the Terms last');
    }

    #[Test]
    public function creating_terms_with_the_reserved_code_content_fails(): void
    {
        $client = self::createAdminClient();
        $crawler = $client->request('GET', '/admin/terms/new');

        $form = $crawler->filter('form[name="setono_sylius_terms_terms"]')->form();
        $client->submit($form, [
            'setono_sylius_terms_terms[code]' => 'content',
            'setono_sylius_terms_terms[translations][en_US][name]' => 'Content',
            'setono_sylius_terms_terms[translations][en_US][label]' => 'I accept the [link:content]',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'The code "content" is reserved for the hooks of the Terms page. Choose another code.');

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');
        self::assertNull($repository->findOneBy(['code' => 'content']));
    }

    #[Test]
    public function the_update_page_flags_a_form_code_that_is_not_configured(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('newsletter_terms', forms: ['checkout_complete', 'newsletter']);
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1.page-title', 'Edit terms');

        $stale = $crawler->filter('input[data-test-form-code="newsletter"]');
        self::assertSame('checked', $stale->attr('checked'));
        self::assertSame('newsletter (not configured)', trim($crawler->filter(sprintf('label[for="%s"]', (string) $stale->attr('id')))->text()));
    }

    #[Test]
    public function the_wand_fills_the_slug_from_the_name(): void
    {
        $client = self::createAdminClient();

        // The component is first rendered outside a request, and its form's CSRF token needs a session
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        $component = $this->createLiveComponent('setono_sylius_terms:admin:terms:form', [
            'template' => '@SetonoSyliusTermsPlugin/admin/terms/form.html.twig',
        ], $client);

        $component
            // The admin's Live Component route is /admin/{_locale}/_components/…
            ->setRouteLocale('en_US')
            ->set('setono_sylius_terms_terms.translations.en_US.name', 'Privacy & Cookies')
            ->call('generateTermsSlug', ['localeCode' => 'en_US'])
        ;

        $formComponent = $component->component();
        self::assertInstanceOf(TermsFormComponent::class, $formComponent);

        /** @var array{translations: array<string, array{slug: string}>} $formValues */
        $formValues = $formComponent->formValues;
        self::assertSame('privacy-and-cookies', $formValues['translations']['en_US']['slug']);
    }

    private static function createAdminClient(): KernelBrowser
    {
        $client = self::createClient();
        $client->loginUser(self::getAdmin(), 'admin');

        return $client;
    }

    private static function getAdmin(): AdminUserInterface
    {
        /** @var UserRepositoryInterface<AdminUserInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.admin_user');

        $admin = $repository->findOneBy(['username' => 'sylius']);
        self::assertInstanceOf(AdminUserInterface::class, $admin);

        return $admin;
    }

    private static function getLocale(string $code): LocaleInterface
    {
        /** @var RepositoryInterface<LocaleInterface> $repository */
        $repository = self::getContainer()->get('sylius.repository.locale');

        $locale = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(LocaleInterface::class, $locale);

        return $locale;
    }

    private static function findTerms(string $code): TermsInterface
    {
        self::getEntityManager()->clear();

        /** @var TermsRepositoryInterface $repository */
        $repository = self::getContainer()->get('setono_sylius_terms.repository.terms');

        $terms = $repository->findOneBy(['code' => $code]);
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    /**
     * @param array<AbstractHookable> $hookables
     */
    private static function getHookable(array $hookables, string $name): ?AbstractHookable
    {
        foreach ($hookables as $hookable) {
            if ($hookable->name === $name) {
                return $hookable;
            }
        }

        return null;
    }

    /**
     * @param array<AbstractHookable> $hookables
     *
     * @return list<string>
     */
    private static function getHookableNames(array $hookables): array
    {
        return array_values(array_map(static fn (AbstractHookable $hookable): string => $hookable->name, $hookables));
    }
}
