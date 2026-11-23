<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\TwigHooks\Bag\DataBag;
use Sylius\TwigHooks\Bag\ScalarDataBag;
use Sylius\TwigHooks\Hook\Metadata\HookMetadata;
use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\HookableComponent;
use Sylius\TwigHooks\Hookable\Metadata\HookableMetadata;
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
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

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
            self::assertSame(['toolbar', 'upload'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form.translations.content#write'))));
            self::assertSame(['preview'], self::getHookableNames($registry->getEnabledFor($prefixes('content.form.translations.content#preview'))));

            // The page's own hooks, such as update#javascripts, have no dot after the page
            $pagePrefixes = static fn (string $hook): array => [
                sprintf('setono_sylius_terms.admin.terms.%s%s', $page, $hook),
                sprintf('sylius_admin.terms.%s%s', $page, $hook),
                sprintf('sylius_admin.common.%s%s', $page, $hook),
            ];
            self::assertSame(['markdown_toolbar'], self::getHookableNames($registry->getEnabledFor($pagePrefixes('#javascripts'))));
            self::assertSame(['terms_content'], self::getHookableNames($registry->getEnabledFor($pagePrefixes('#stylesheets'))));
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
    #[DataProvider('provideBaselineTermsCodes')]
    public function saving_a_baseline_terms_without_changing_it_adds_no_version(string $code): void
    {
        $client = self::createAdminClient();
        $terms = self::findTerms($code);
        $versionNumbers = self::getVersionNumbers($terms);
        $contents = self::getContents($terms);

        self::submitUnchangedUpdateForm($client, $terms);

        $terms = self::findTerms($code);
        self::assertSame($versionNumbers, self::getVersionNumbers($terms));
        self::assertSame($contents, self::getContents($terms));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBaselineTermsCodes(): iterable
    {
        yield 'terms_and_conditions' => ['terms_and_conditions'];
        yield 'privacy_policy' => ['privacy_policy'];
    }

    /**
     * The form doesn't trim the Content, which would turn the code block into a paragraph
     */
    #[Test]
    public function saving_a_terms_whose_content_opens_with_an_indented_code_block_without_changing_it_keeps_the_indentation_and_adds_no_version(): void
    {
        $client = self::createAdminClient();
        $content = "    composer require setono/sylius-terms-plugin\n\nWe use cookies.";
        $terms = self::createTerms('cookie_policy');
        $terms->getTranslation('en_US')->setContent($content);
        self::getEntityManager()->flush();
        self::assertSame([1, 2], self::getVersionNumbers($terms));

        self::submitUnchangedUpdateForm($client, $terms);

        $terms = self::findTerms('cookie_policy');
        self::assertSame([1, 2], self::getVersionNumbers($terms));
        self::assertSame($content, $terms->getTranslation('en_US')->getContent());
    }

    #[Test]
    public function the_wand_fills_the_slug_from_the_name(): void
    {
        $client = self::createAdminClient();

        $component = $this->createFormComponent($client)
            ->set('setono_sylius_terms_terms.translations.en_US.name', 'Privacy & Cookies')
            ->call('generateTermsSlug', ['localeCode' => 'en_US'])
        ;

        $formComponent = $component->component();
        self::assertInstanceOf(TermsFormComponent::class, $formComponent);

        /** @var array{translations: array<string, array{slug: string}>} $formValues */
        $formValues = $formComponent->formValues;
        self::assertSame('privacy-and-cookies', $formValues['translations']['en_US']['slug']);
    }

    #[Test]
    public function the_form_pages_load_the_editors_scripts_and_the_content_stylesheet(): void
    {
        $client = self::createAdminClient();
        $terms = self::findTerms('terms_and_conditions');
        $scripts = [
            'markdown-toolbar-script' => '/bundles/setonosyliustermsplugin/admin/markdown-toolbar-element-2.2.3/index.js',
            'file-attachment-script' => '/bundles/setonosyliustermsplugin/admin/file-attachment-element-3.2.0/index.js',
            'upload-images-script' => '/bundles/setonosyliustermsplugin/admin/upload-images.js',
        ];

        foreach (['/admin/terms/new', sprintf('/admin/terms/%d/edit', (int) $terms->getId())] as $path) {
            $crawler = $client->request('GET', $path);

            self::assertResponseIsSuccessful();

            foreach ($scripts as $name => $src) {
                $script = $crawler->filter(sprintf('script[data-test-%s]', $name));
                self::assertCount(1, $script, $path);
                self::assertSame('module', $script->attr('type'));
                self::assertSame($src, $script->attr('src'));
            }

            self::assertSame('/bundles/setonosyliustermsplugin/admin/terms-content.css', $crawler->filter('link[data-test-terms-content-stylesheet]')->attr('href'));
        }

        // The index has no editor
        $crawler = $client->request('GET', '/admin/terms/');

        self::assertResponseIsSuccessful();

        foreach (array_keys($scripts) as $name) {
            self::assertCount(0, $crawler->filter(sprintf('script[data-test-%s]', $name)));
        }
        self::assertCount(0, $crawler->filter('link[data-test-terms-content-stylesheet]'));
    }

    /**
     * The upload hookable renders the textarea inside a <file-attachment> element, with the hint and the picker below it
     */
    #[Test]
    public function each_locales_textarea_is_inside_its_drop_zone_for_uploading_images(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', localeCodes: ['en_US', 'de_DE']);
        self::getEntityManager()->clear();

        foreach (['/admin/terms/new', sprintf('/admin/terms/%d/edit', (int) $terms->getId())] as $path) {
            $crawler = $client->request('GET', $path);

            self::assertResponseIsSuccessful();

            foreach (['en_US', 'de_DE'] as $localeCode) {
                $dropZone = $crawler->filter(sprintf('file-attachment[data-test-content-upload="%s"]', $localeCode));
                self::assertCount(1, $dropZone, $path);

                $textarea = $dropZone->filter('textarea');
                self::assertCount(1, $textarea, 'The textarea is inside the drop zone');
                self::assertSame($localeCode, $textarea->attr('data-test-content'));
                self::assertCount(1, $crawler->filter(sprintf('textarea[data-test-content="%s"]', $localeCode)), 'The textarea is rendered once');
                $id = (string) $textarea->attr('id');
                self::assertSame($id . '-write', $dropZone->ancestors()->filter('.tab-pane')->attr('id'), 'The drop zone is in the Write pane');

                self::assertSame('/admin/terms/uploaded-images', $dropZone->attr('data-upload-url'));
                self::assertNotEmpty($dropZone->attr('data-csrf-token'));
                self::assertSame("%name% couldn't be uploaded.", $dropZone->attr('data-upload-failed'));

                $input = $dropZone->filter('input[type="file"]');
                self::assertSame($id . '-upload', $input->attr('id'));
                self::assertSame($input->attr('id'), $dropZone->attr('input'), 'The element takes files from this input only');
                self::assertSame('image/png,image/jpeg,image/gif,image/webp', $input->attr('accept'));
                self::assertNotNull($input->attr('multiple'));
                self::assertNull($input->attr('name'), 'The input is not submitted with the form, nor bound by the Live form');

                $hint = $dropZone->filter(sprintf('[data-test-content-upload-hint="%s"]', $localeCode));
                self::assertSame('Drop or paste images, or choose one', trim((string) preg_replace('/\s+/', ' ', $hint->text())));
                self::assertSame($id . '-upload', $hint->filter('label')->attr('for'), '"choose one" opens the picker');
                self::assertSame('choose one', $hint->filter('label')->text());

                self::assertCount(1, $dropZone->filter(sprintf('[data-test-content-upload-errors="%s"]', $localeCode)));
            }
        }
    }

    #[Test]
    public function each_locales_content_has_a_markdown_toolbar_and_write_and_preview_tabs(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', localeCodes: ['en_US', 'de_DE']);
        $terms->getTranslation('en_US')->setContent("## Cookies\n\nWe use **cookies**.");
        $terms->getTranslation('de_DE')->setContent(null);
        self::getEntityManager()->flush();
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', (int) $terms->getId()));

        self::assertResponseIsSuccessful();

        foreach (['en_US', 'de_DE'] as $localeCode) {
            $textarea = $crawler->filter(sprintf('textarea[data-test-content="%s"]', $localeCode));
            $id = (string) $textarea->attr('id');
            self::assertSame(sprintf('%s-write', $id), $textarea->ancestors()->filter('.tab-pane')->attr('id'), 'The textarea is in the Write pane');

            $toolbar = $crawler->filter(sprintf('markdown-toolbar[data-test-markdown-toolbar="%s"]', $localeCode));
            self::assertSame($id, $toolbar->attr('for'));
            self::assertSame(
                ['heading', 'bold', 'italic', 'quote', 'link', 'image', 'bulleted_list', 'numbered_list'],
                $toolbar->filter('[data-test-markdown-button]')->each(static fn (Crawler $button): ?string => $button->attr('data-test-markdown-button')),
            );
            self::assertSame(
                ['md-header', 'md-bold', 'md-italic', 'md-quote', 'md-link', 'md-image', 'md-unordered-list', 'md-ordered-list'],
                $toolbar->filter('[data-test-markdown-button]')->each(static fn (Crawler $button): string => $button->nodeName()),
            );
            // The Terms page's <h1> is the Terms' name, so the heading button inserts ##
            self::assertSame('2', $toolbar->filter('md-header')->attr('level'));
            self::assertSame('Bold', $toolbar->filter('md-bold')->attr('title'));
            self::assertCount(8, $toolbar->filter('svg.icon'), 'Each button has its Tabler icon');

            self::assertSame('#' . $id . '-write', $crawler->filter(sprintf('[data-test-content-write-tab="%s"]', $localeCode))->attr('href'));
            self::assertSame('#' . $id . '-preview', $crawler->filter(sprintf('[data-test-content-preview-tab="%s"]', $localeCode))->attr('href'));
        }

        // The preview of the saved Content is there before any edit
        self::assertSame(
            self::normalizeHtml(self::getContentRenderer()->render("## Cookies\n\nWe use **cookies**.")->html),
            self::normalizeHtml($crawler->filter('[data-test-content-preview="en_US"] .setono-terms-content')->html()),
        );
        self::assertSame('No content', trim($crawler->filter('[data-test-no-content="de_DE"]')->text()));
    }

    #[Test]
    public function a_live_rerender_with_changed_content_returns_the_rendered_preview(): void
    {
        $client = self::createAdminClient();
        $terms = self::createTerms('cookie_policy', localeCodes: ['en_US', 'de_DE']);
        $sources = [
            'en_US' => "## Cookies\n\nWe use **cookies**.\n\n![A cookie](https://example.com/cookie.png)<script>alert('en')</script>",
            'de_DE' => "## Kekse\n\n* Wir verwenden _Kekse_.\n* [Mehr](https://example.com)",
        ];

        $component = $this->createFormComponent($client, $terms);

        foreach ($sources as $localeCode => $source) {
            $component->set(sprintf('setono_sylius_terms_terms.translations.%s.content', $localeCode), $source);
        }

        $crawler = $component->render()->crawler();

        foreach ($sources as $localeCode => $source) {
            $preview = $crawler->filter(sprintf('[data-test-content-preview="%s"] .setono-terms-content', $localeCode));

            // Exactly what the Terms page shows for the same Content
            self::assertSame(self::normalizeHtml(self::getContentRenderer()->render($source)->html), self::normalizeHtml($preview->html()));
            self::assertCount(1, $preview->filter('h2'));
            self::assertCount(0, $preview->filter('script'), 'The preview is sanitised');
        }

        // Blanking the Content previews nothing
        $crawler = $component->set('setono_sylius_terms_terms.translations.en_US.content', '')->render()->crawler();
        self::assertSame('No content', trim($crawler->filter('[data-test-no-content="en_US"]')->text()));

        // The preview saves nothing
        self::assertSame('The content of cookie_policy', self::findTerms('cookie_policy')->getTranslation('en_US')->getContent());
    }

    /**
     * The admin Terms form as a Live Component, for the Terms on the update page or for a new one on the create page
     */
    private function createFormComponent(KernelBrowser $client, ?TermsInterface $terms = null): TestLiveComponent
    {
        // The component is first rendered outside a request, and its form's CSRF token needs a session
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get('request_stack');
        $requestStack->push($request);

        // The hook the page renders the form in, with the prefixes core's CRUD template passes, so the form's hooks
        // render as on the page
        $hook = sprintf('%s.content', null === $terms ? 'create' : 'update');
        $props = [
            'template' => '@SetonoSyliusTermsPlugin/admin/terms/form.html.twig',
            'hookableMetadata' => new HookableMetadata(
                new HookMetadata('setono_sylius_terms.admin.terms.' . $hook, new DataBag()),
                new DataBag(),
                new ScalarDataBag(['render_rest' => false]),
                ['setono_sylius_terms.admin.terms.' . $hook, 'sylius_admin.terms.' . $hook, 'sylius_admin.common.' . $hook],
            ),
        ];
        if (null !== $terms) {
            $props['resource'] = $terms;
        }

        return $this->createLiveComponent('setono_sylius_terms:admin:terms:form', $props, $client)
            // The admin's Live Component route is /admin/{_locale}/_components/…
            ->setRouteLocale('en_US')
        ;
    }

    private static function getContentRenderer(): ContentRendererInterface
    {
        /** @var ContentRendererInterface $renderer */
        $renderer = self::getContainer()->get(ContentRendererInterface::class);

        return $renderer;
    }

    /**
     * The HTML as the DOM serialises it, so HTML from the renderer compares with HTML from a page
     */
    private static function normalizeHtml(string $html): string
    {
        return trim((new Crawler('<div id="normalized">' . $html . '</div>'))->filter('#normalized')->html());
    }

    /**
     * Opens the Terms' update page and saves the form as the page fills it
     */
    private static function submitUnchangedUpdateForm(KernelBrowser $client, TermsInterface $terms): void
    {
        $id = (int) $terms->getId();
        self::getEntityManager()->clear();

        $crawler = $client->request('GET', sprintf('/admin/terms/%d/edit', $id));
        $client->submit($crawler->filter('form[name="setono_sylius_terms_terms"]')->form());

        self::assertResponseRedirects(sprintf('/admin/terms/%d/edit', $id));
    }

    /**
     * @return list<int|null> in number order
     */
    private static function getVersionNumbers(TermsInterface $terms): array
    {
        return array_values(array_map(
            static fn (TermsVersionInterface $version): ?int => $version->getNumber(),
            $terms->getVersions()->toArray(),
        ));
    }

    /**
     * @return array<string, string|null> by locale
     */
    private static function getContents(TermsInterface $terms): array
    {
        $contents = [];
        foreach ($terms->getTranslations()->getKeys() as $localeCode) {
            $contents[(string) $localeCode] = $terms->getTranslation((string) $localeCode)->getContent();
        }

        return $contents;
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
