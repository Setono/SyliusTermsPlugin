<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsChecker;
use Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeChecker;
use Setono\SyliusTermsPlugin\Checker\VersionedTextChangeCheckerInterface;
use Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction;
use Setono\SyliusTermsPlugin\Converter\ContentConverter;
use Setono\SyliusTermsPlugin\DependencyInjection\SetonoSyliusTermsExtension;
use Setono\SyliusTermsPlugin\EventListener\CreateTermsVersionListener;
use Setono\SyliusTermsPlugin\EventListener\PreventTermsVersionEditListener;
use Setono\SyliusTermsPlugin\EventSubscriber\AddMenuSubscriber;
use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Fixture\TermsFixture;
use Setono\SyliusTermsPlugin\Form\Extension\FormTypeExtension;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Generator\SlugGenerator;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Grid\TermsGrid;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookup;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookup;
use Setono\SyliusTermsPlugin\Lookup\TermsPageLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Pdf\ImageInliner;
use Setono\SyliusTermsPlugin\Pdf\ImageInlinerInterface;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGenerator;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGeneratorInterface;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiser;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiserInterface;
use Setono\SyliusTermsPlugin\Renderer\ContentRenderer;
use Setono\SyliusTermsPlugin\Renderer\ContentRendererInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Sanitizer\HeadingIdAttributeSanitizer;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;
use Setono\SyliusTermsPlugin\Twig\Component\TermsFormComponent;
use Setono\SyliusTermsPlugin\Twig\ContentRuntime;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;
use Sylius\PdfGenerationBundle\Core\Renderer\HtmlToPdfRendererInterface;
use Sylius\PdfGenerationBundle\SyliusPdfGenerationBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\Reference;

final class SetonoSyliusTermsExtensionTest extends AbstractExtensionTestCase
{
    protected function getContainerExtensions(): array
    {
        return [
            new SetonoSyliusTermsExtension(),
        ];
    }

    #[Test]
    public function it_loads(): void
    {
        $this->expectNotToPerformAssertions();

        $this->load();
    }

    #[Test]
    public function after_loading_the_service_definitions_have_been_loaded(): void
    {
        $this->load();

        $resources = array_map(static fn (ResourceInterface $resource): string => (string) $resource, $this->container->getResources());

        self::assertContains(dirname(__DIR__, 3) . '/config/services.php', $resources);
    }

    /**
     * @param class-string $id
     */
    #[Test]
    #[DataProvider('provideServiceIds')]
    public function it_registers_services_by_their_fqcn(string $id): void
    {
        $this->load();

        $this->assertContainerBuilderHasService($id);
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function provideServiceIds(): \Generator
    {
        yield 'show terms action' => [ShowTermsAction::class];
        yield 'add menu subscriber' => [AddMenuSubscriber::class];
        yield 'terms example factory' => [TermsExampleFactory::class];
        yield 'terms fixture' => [TermsFixture::class];
        yield 'terms type' => [TermsType::class];
        yield 'terms translation type' => [TermsTranslationType::class];
        yield 'terms checkbox collection type' => [TermsCheckboxCollectionType::class];
        yield 'form type extension' => [FormTypeExtension::class];
        yield 'label renderer' => [LabelRenderer::class];
        yield 'terms extension' => [TermsExtension::class];
        yield 'terms runtime' => [TermsRuntime::class];
        yield 'versioned text change checker' => [VersionedTextChangeChecker::class];
        yield 'terms version snapshotter' => [TermsVersionSnapshotter::class];
        yield 'create terms version listener' => [CreateTermsVersionListener::class];
        yield 'prevent terms version edit listener' => [PreventTermsVersionEditListener::class];
        yield 'form terms lookup' => [FormTermsLookup::class];
        yield 'terms page lookup' => [TermsPageLookup::class];
        yield 'required terms checker' => [RequiredTermsChecker::class];
        yield 'required terms ticked validator' => [RequiredTermsTickedValidator::class];
        yield 'content converter' => [ContentConverter::class];
        yield 'content renderer' => [ContentRenderer::class];
        yield 'heading id attribute sanitizer' => [HeadingIdAttributeSanitizer::class];
        yield 'content runtime' => [ContentRuntime::class];
        yield 'slug generator' => [SlugGenerator::class];
        yield 'terms grid' => [TermsGrid::class];
        yield 'terms form component' => [TermsFormComponent::class];
    }

    #[Test]
    public function it_aliases_interfaces_to_their_implementations(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(LabelRendererInterface::class, LabelRenderer::class);
        $this->assertContainerBuilderHasAlias(VersionedTextChangeCheckerInterface::class, VersionedTextChangeChecker::class);
        $this->assertContainerBuilderHasAlias(TermsVersionSnapshotterInterface::class, TermsVersionSnapshotter::class);
        $this->assertContainerBuilderHasAlias(FormTermsLookupInterface::class, FormTermsLookup::class);
        $this->assertContainerBuilderHasAlias(TermsPageLookupInterface::class, TermsPageLookup::class);
        $this->assertContainerBuilderHasAlias(RequiredTermsCheckerInterface::class, RequiredTermsChecker::class);
        $this->assertContainerBuilderHasAlias(ContentRendererInterface::class, ContentRenderer::class);
        $this->assertContainerBuilderHasAlias(SlugGeneratorInterface::class, SlugGenerator::class);
    }

    #[Test]
    public function it_registers_the_required_terms_ticked_validator_as_a_constraint_validator(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(RequiredTermsTickedValidator::class, 'validator.constraint_validator');
    }

    #[Test]
    public function it_creates_terms_versions_and_guards_them_on_flush(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(CreateTermsVersionListener::class, 'doctrine.event_listener', ['event' => 'onFlush']);
        $this->assertContainerBuilderHasServiceDefinitionWithTag(PreventTermsVersionEditListener::class, 'doctrine.event_listener', ['event' => 'onFlush']);
    }

    #[Test]
    public function it_sets_the_enabled_forms_by_code(): void
    {
        $this->load(['forms' => [
            'contact' => ['enabled' => false],
            'newsletter' => ['type' => 'App\Form\Type\NewsletterType', 'label' => 'app.form.newsletter'],
        ]]);

        $this->assertContainerBuilderHasParameter('setono_sylius_terms.forms', [
            'checkout_complete' => [
                'type' => CompleteType::class,
                'label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete',
                'hooks' => ['sylius_shop.checkout.complete.content.form' => 50],
                'saved_event' => 'sylius.order.post_complete',
            ],
            'registration' => [
                'type' => CustomerRegistrationType::class,
                'label' => 'setono_sylius_terms.form.terms.term_form.registration',
                'hooks' => ['sylius_shop.account.register.content.form' => 50],
                'saved_event' => 'sylius.customer.post_register',
            ],
            'newsletter' => [
                'type' => 'App\Form\Type\NewsletterType',
                'label' => 'app.form.newsletter',
                'hooks' => [],
                'saved_event' => null,
            ],
        ]);
    }

    #[Test]
    public function it_sets_the_terms_path(): void
    {
        $this->load(['routing' => ['terms' => 'vilkaar']]);

        $this->assertContainerBuilderHasParameter('setono_sylius_terms.terms_path', 'vilkaar');
    }

    #[Test]
    public function it_registers_the_terms_resource(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_terms.model.terms.class', Terms::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_terms.model.terms_translation.class', TermsTranslation::class);
        $this->assertContainerBuilderHasService('setono_sylius_terms.repository.terms');
    }

    #[Test]
    public function it_registers_the_terms_version_resource(): void
    {
        $this->load();

        $this->assertContainerBuilderHasParameter('setono_sylius_terms.model.terms_version.class', TermsVersion::class);
        $this->assertContainerBuilderHasParameter('setono_sylius_terms.model.terms_version_translation.class', TermsVersionTranslation::class);
        $this->assertContainerBuilderHasService('setono_sylius_terms.repository.terms_version');
        $this->assertContainerBuilderHasService('setono_sylius_terms.factory.terms_version');
        $this->assertContainerBuilderHasService('setono_sylius_terms.factory.terms_version_translation');
    }

    #[Test]
    public function it_renders_content_with_the_plugins_converter_and_sanitizer(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentRenderer::class, 0, new Reference(ContentConverter::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ContentRenderer::class, 1, new Reference('html_sanitizer.sanitizer.setono_sylius_terms.content'));
        $this->assertContainerBuilderHasServiceDefinitionWithTag(ContentRuntime::class, 'twig.runtime');
    }

    #[Test]
    public function it_prepends_the_content_sanitizer(): void
    {
        $this->load();

        self::assertSame([[
            'html_sanitizer' => [
                'enabled' => true,
                'sanitizers' => [
                    'setono_sylius_terms.content' => [
                        'allow_safe_elements' => true,
                        'allowed_link_schemes' => ['http', 'https', 'mailto', 'tel'],
                        'allow_relative_links' => true,
                        'allowed_media_schemes' => ['http', 'https'],
                        'allow_relative_medias' => true,
                        'with_attribute_sanitizers' => [HeadingIdAttributeSanitizer::class],
                        'max_input_length' => -1,
                    ],
                ],
            ],
        ]], $this->container->getExtensionConfig('framework'));
    }

    #[Test]
    public function it_registers_the_terms_grid_as_an_invokable_grid(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(TermsGrid::class, 'sylius.invokable_grid');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsGrid::class, 0, '%setono_sylius_terms.forms%');
        self::assertSame([], $this->container->getExtensionConfig('sylius_grid'), 'The grid is no longer prepended as an array grid');
    }

    #[Test]
    public function it_registers_the_terms_form_as_an_admin_live_component(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(TermsFormComponent::class, 'sylius.live_component.admin', ['key' => 'setono_sylius_terms:admin:terms:form']);
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsFormComponent::class, 0, new Reference('setono_sylius_terms.repository.terms'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsFormComponent::class, 2, '%setono_sylius_terms.model.terms.class%');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsFormComponent::class, 3, '%setono_sylius_terms.form.terms.class%');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsFormComponent::class, 4, new Reference(SlugGeneratorInterface::class));
    }

    #[Test]
    public function it_generates_slugs_with_symfonys_slugger(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(SlugGenerator::class, 0, new Reference('slugger'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsTranslationType::class, 1, new Reference(SlugGeneratorInterface::class));
    }

    #[Test]
    public function the_terms_pdfs_register_nothing_when_switched_off(): void
    {
        $this->load();

        self::assertSame([], $this->container->getExtensionConfig('sylius_pdf_generation'));
        self::assertCount(1, $this->container->getExtensionConfig('framework'), 'Only the content sanitizer is prepended, no cache pool');
        $this->assertContainerBuilderNotHasService(TermsPdfGenerator::class);
        $this->assertContainerBuilderNotHasService(TermsPdfGeneratorInterface::class);
        $this->assertContainerBuilderNotHasService(ImageInliner::class);
        $this->assertContainerBuilderNotHasService(UrlAbsolutiser::class);
    }

    #[Test]
    public function switching_the_terms_pdfs_on_prepends_a_dompdf_context_and_the_cache_pool(): void
    {
        $this->loadWithPdf([FrameworkBundle::class, SyliusPdfGenerationBundle::class]);

        self::assertSame([[
            'contexts' => [
                'setono_sylius_terms' => ['adapter' => 'dompdf'],
            ],
        ]], $this->container->getExtensionConfig('sylius_pdf_generation'));

        self::assertContains([
            'cache' => [
                'pools' => [
                    'setono_sylius_terms.pdf' => ['adapter' => 'cache.app'],
                ],
            ],
        ], $this->container->getExtensionConfig('framework'));
    }

    #[Test]
    public function switching_the_terms_pdfs_on_registers_the_generator(): void
    {
        $this->loadWithPdf([SyliusPdfGenerationBundle::class]);

        $this->assertContainerBuilderHasAlias(TermsPdfGeneratorInterface::class, TermsPdfGenerator::class);
        $this->assertContainerBuilderHasAlias(UrlAbsolutiserInterface::class, UrlAbsolutiser::class);
        $this->assertContainerBuilderHasAlias(ImageInlinerInterface::class, ImageInliner::class);

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(UrlAbsolutiser::class, 0, '%sylius.unsecured_urls%');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(ImageInliner::class, 0, new Reference('http_client'));

        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 0, new Reference('twig'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 1, new Reference(UrlAbsolutiserInterface::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 2, new Reference(ImageInlinerInterface::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 3, new Reference(HtmlToPdfRendererInterface::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 4, new Reference('setono_sylius_terms.pdf'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 5, new Reference(SlugGeneratorInterface::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsPdfGenerator::class, 6, new Reference('logger'));
    }

    #[Test]
    public function switching_the_terms_pdfs_on_without_the_pdf_generation_bundle_fails_with_the_composer_require_hint(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Run "composer require sylius/pdf-generation-bundle dompdf/dompdf symfony/http-client" and register Sylius\PdfGenerationBundle\SyliusPdfGenerationBundle in config/bundles.php.');

        $this->loadWithPdf([FrameworkBundle::class]);
    }

    /**
     * prepend() reads the switch from the extension's config, so it is set there as well as passed to load()
     *
     * @param list<class-string> $bundles
     */
    private function loadWithPdf(array $bundles): void
    {
        $config = ['order_confirmation_email' => ['pdf' => true]];

        $this->setParameter('kernel.bundles', array_combine(
            array_map(static fn (string $bundle): string => substr($bundle, (int) strrpos($bundle, '\\') + 1), $bundles),
            $bundles,
        ));
        $this->container->loadFromExtension('setono_sylius_terms', $config);

        $this->load($config);
    }

    #[Test]
    public function it_prepends_the_admin_hooks(): void
    {
        $this->load();

        $config = $this->container->getExtensionConfig('sylius_twig_hooks');
        self::assertCount(1, $config);

        /** @var array<string, array<string, array<string, mixed>>> $hooks */
        $hooks = $config[0]['hooks'];

        self::assertSame(
            ['sylius_shop.checkout.complete.content.form', 'sylius_shop.account.register.content.form', 'sylius_shop.contact.contact_request.content.form'],
            array_keys($this->getFormHooks()),
            'Besides the admin and Terms page hooks, only the hooks of the default Forms are prepended',
        );

        self::assertSame([
            'template' => '@SyliusAdmin/shared/crud/common/content/header/title_block/title.html.twig',
            'configuration' => ['icon' => 'tabler:circle-check', 'subheader' => 'setono_sylius_terms.ui.manage_terms'],
            'priority' => 100,
        ], $hooks['setono_sylius_terms.admin.terms.index.content.header.title_block']['title']);

        foreach (['create' => 'new_terms', 'update' => 'edit_terms'] as $page => $header) {
            $prefix = 'setono_sylius_terms.admin.terms.' . $page;

            self::assertSame([
                'template' => sprintf('@SyliusAdmin/shared/crud/%s/content/header/title_block/title.html.twig', $page),
                'configuration' => ['header' => 'setono_sylius_terms.ui.' . $header],
                'priority' => 100,
            ], $hooks[$prefix . '.content.header.title_block']['title']);

            self::assertSame([
                'component' => 'setono_sylius_terms:admin:terms:form',
                'props' => [
                    'resource' => '@=_context.resource',
                    'form' => '@=_context.form',
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/form.html.twig',
                ],
                'configuration' => ['render_rest' => false],
                'priority' => 0,
            ], $hooks[$prefix . '.content']['form']);

            self::assertSame([
                'general' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general.html.twig', 'priority' => 200],
                'placement' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/placement.html.twig', 'priority' => 100],
                'translations' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations.html.twig', 'priority' => 0],
                'sections' => ['enabled' => false],
            ], $hooks[$prefix . '.content.form']);

            self::assertSame([
                'code' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general/code.html.twig', 'priority' => 300],
                'enabled' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general/enabled.html.twig', 'priority' => 200],
                'required' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general/required.html.twig', 'priority' => 100],
                'position' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general/position.html.twig', 'priority' => 0],
            ], $hooks[$prefix . '.content.form.general']);

            self::assertSame([
                'forms' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/placement/forms.html.twig', 'priority' => 100],
                'channels' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/placement/channels.html.twig', 'priority' => 0],
            ], $hooks[$prefix . '.content.form.placement']);

            self::assertSame([
                'name' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations/name.html.twig', 'priority' => 300],
                'slug' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations/slug.html.twig', 'priority' => 200],
                'label' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations/label.html.twig', 'priority' => 100],
                'content' => ['template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations/content.html.twig', 'priority' => 0],
            ], $hooks[$prefix . '.content.form.translations']);
        }
    }

    #[Test]
    public function it_prepends_the_terms_page_hooks(): void
    {
        $this->load();

        $config = $this->container->getExtensionConfig('sylius_twig_hooks');
        self::assertCount(1, $config);

        /** @var array<string, mixed> $hooks */
        $hooks = $config[0]['hooks'];

        $termsPageHooks = array_filter(
            $hooks,
            static fn (string $hook): bool => str_starts_with($hook, SetonoSyliusTermsExtension::TERMS_PAGE_HOOK),
            \ARRAY_FILTER_USE_KEY,
        );

        $template = static fn (string $path): string => '@SetonoSyliusTermsPlugin/shop/terms/show/' . $path . '.html.twig';

        self::assertSame([
            'setono_sylius_terms.shop.terms.show#stylesheets' => [
                'terms_page' => ['template' => $template('stylesheets'), 'priority' => -100],
            ],
            'setono_sylius_terms.shop.terms.show' => [
                'content' => ['template' => $template('content'), 'priority' => 0],
            ],
            'setono_sylius_terms.shop.terms.show.content' => [
                'header' => ['template' => $template('content/header'), 'priority' => 200],
                'sidebar' => ['template' => $template('content/sidebar'), 'priority' => 100],
                'main' => ['template' => $template('content/main'), 'priority' => 0],
            ],
            'setono_sylius_terms.shop.terms.show.content.header' => [
                'breadcrumbs' => ['template' => $template('content/header/breadcrumbs'), 'priority' => 0],
            ],
            'setono_sylius_terms.shop.terms.show.content.sidebar' => [
                'table_of_contents' => ['template' => $template('content/sidebar/table_of_contents'), 'priority' => 100],
                'version' => ['template' => $template('content/sidebar/version'), 'priority' => 0],
            ],
            'setono_sylius_terms.shop.terms.show.content.main' => [
                'name' => ['template' => $template('content/main/name'), 'priority' => 100],
                'content' => ['template' => $template('content/main/content'), 'priority' => 0],
            ],
        ], $termsPageHooks);

        foreach ($termsPageHooks as $hookables) {
            self::assertIsArray($hookables);

            foreach ($hookables as $hookable) {
                self::assertIsArray($hookable);
                self::assertIsString($hookable['template']);
                self::assertFileExists(str_replace('@SetonoSyliusTermsPlugin', dirname(__DIR__, 3) . '/templates', $hookable['template']));
            }
        }
    }

    #[Test]
    public function it_prepends_a_hookable_for_each_default_form_at_priority_50(): void
    {
        $this->load();

        self::assertSame([
            'sylius_shop.checkout.complete.content.form' => self::getFormHookable(50),
            'sylius_shop.account.register.content.form' => self::getFormHookable(50),
            'sylius_shop.contact.contact_request.content.form' => self::getFormHookable(50),
        ], $this->getFormHooks());
    }

    #[Test]
    public function it_prepends_one_hookable_per_hook_of_each_enabled_form_at_its_priority_all_rendering_the_shared_template(): void
    {
        $config = ['forms' => [
            'checkout_complete' => ['hooks' => ['app.checkout.complete.terms' => 10, 'app.checkout.complete.sidebar' => -5]],
            'contact' => ['enabled' => false],
            'newsletter' => ['type' => 'App\Form\Type\NewsletterType', 'hooks' => ['app.newsletter.form' => 50]],
            // Renders form.terms by hand
            'manual' => ['type' => 'App\Form\Type\ManualType', 'hooks' => []],
        ]];

        // prepend() reads the forms from the extension's config, so it is set there as well as passed to load()
        $this->container->loadFromExtension('setono_sylius_terms', $config);
        $this->load($config);

        self::assertSame([
            'app.checkout.complete.terms' => self::getFormHookable(10),
            'app.checkout.complete.sidebar' => self::getFormHookable(-5),
            'sylius_shop.account.register.content.form' => self::getFormHookable(50),
            'app.newsletter.form' => self::getFormHookable(50),
        ], $this->getFormHooks());
    }

    #[Test]
    public function the_form_type_extension_adds_the_terms_the_form_lookup_finds_in_the_current_channel_and_locale(): void
    {
        $this->load();

        $this->assertContainerBuilderHasServiceDefinitionWithTag(FormTypeExtension::class, 'form.type_extension');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(FormTypeExtension::class, 0, new Reference(FormTermsLookupInterface::class));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(FormTypeExtension::class, 1, new Reference('sylius.context.channel'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(FormTypeExtension::class, 2, new Reference('sylius.context.locale'));
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(FormTypeExtension::class, 3, '%setono_sylius_terms.forms%');

        $this->assertContainerBuilderHasServiceDefinitionWithTag(TermsCheckboxCollectionType::class, 'form.type');
        $this->assertContainerBuilderHasServiceDefinitionWithArgument(TermsCheckboxCollectionType::class, 0, new Reference(LabelRendererInterface::class));
    }

    /**
     * The prepended hooks outside the plugin's own pages
     *
     * @return array<string, mixed>
     */
    private function getFormHooks(): array
    {
        $config = $this->container->getExtensionConfig('sylius_twig_hooks');
        self::assertCount(1, $config);

        /** @var array<string, mixed> $hooks */
        $hooks = $config[0]['hooks'];

        return array_filter(
            $hooks,
            static fn (string $hook): bool => !str_starts_with($hook, 'setono_sylius_terms.'),
            \ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return array<string, array{template: string, priority: int}>
     */
    private static function getFormHookable(int $priority): array
    {
        return [
            'setono_sylius_terms_checkboxes' => [
                'template' => '@SetonoSyliusTermsPlugin/shop/form/terms.html.twig',
                'priority' => $priority,
            ],
        ];
    }
}
