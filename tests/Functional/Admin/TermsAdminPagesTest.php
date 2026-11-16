<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Setono\SyliusTermsPlugin\Tests\Functional\CreatesTermsTrait;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Sylius\Component\Core\Model\AdminUserInterface;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\TwigHooks\Hookable\AbstractHookable;
use Sylius\TwigHooks\Hookable\HookableComponent;
use Sylius\TwigHooks\Registry\HookablesRegistry;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class TermsAdminPagesTest extends WebTestCase
{
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
        self::assertSame(['terms_and_conditions', 'privacy_policy', 'german_only'], $rows->filter('td:nth-child(2) code')->each(static fn ($code): string => trim($code->text())));

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
        self::assertSame(['General', 'Placement', 'Translations'], $crawler->filter('form .card-title')->each(static fn ($title): string => trim($title->text())));
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
