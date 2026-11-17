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
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
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
        yield 'terms provider' => [TermsProvider::class];
        yield 'label renderer' => [LabelRenderer::class];
        yield 'terms extension' => [TermsExtension::class];
        yield 'terms runtime' => [TermsRuntime::class];
        yield 'versioned text change checker' => [VersionedTextChangeChecker::class];
        yield 'terms version snapshotter' => [TermsVersionSnapshotter::class];
        yield 'create terms version listener' => [CreateTermsVersionListener::class];
        yield 'prevent terms version edit listener' => [PreventTermsVersionEditListener::class];
        yield 'form terms lookup' => [FormTermsLookup::class];
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

        $this->assertContainerBuilderHasAlias(TermsProviderInterface::class, TermsProvider::class);
        $this->assertContainerBuilderHasAlias(LabelRendererInterface::class, LabelRenderer::class);
        $this->assertContainerBuilderHasAlias(VersionedTextChangeCheckerInterface::class, VersionedTextChangeChecker::class);
        $this->assertContainerBuilderHasAlias(TermsVersionSnapshotterInterface::class, TermsVersionSnapshotter::class);
        $this->assertContainerBuilderHasAlias(FormTermsLookupInterface::class, FormTermsLookup::class);
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
    public function it_prepends_the_admin_hooks(): void
    {
        $this->load();

        $config = $this->container->getExtensionConfig('sylius_twig_hooks');
        self::assertCount(1, $config);

        /** @var array<string, array<string, array<string, mixed>>> $hooks */
        $hooks = $config[0]['hooks'];

        foreach (array_keys($hooks) as $hook) {
            self::assertStringStartsWith('setono_sylius_terms.admin.terms.', $hook);
        }

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
}
