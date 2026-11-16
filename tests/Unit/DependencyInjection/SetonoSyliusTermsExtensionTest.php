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
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookup;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Model\Terms;
use Setono\SyliusTermsPlugin\Model\TermsTranslation;
use Setono\SyliusTermsPlugin\Model\TermsVersion;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslation;
use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotter;
use Setono\SyliusTermsPlugin\Snapshotter\TermsVersionSnapshotterInterface;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTickedValidator;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;
use Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType;
use Symfony\Component\Config\Resource\ResourceInterface;

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
    public function it_prepends_the_terms_grid(): void
    {
        $this->load();

        self::assertSame([[
            'grids' => [
                'setono_sylius_terms_terms' => [
                    'driver' => [
                        'name' => 'doctrine/orm',
                        'options' => [
                            'class' => '%setono_sylius_terms.model.terms.class%',
                        ],
                    ],
                    'fields' => [
                        'code' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_terms.ui.code',
                        ],
                        'name' => [
                            'type' => 'string',
                            'label' => 'setono_sylius_terms.ui.name',
                        ],
                        'channels' => [
                            'type' => 'twig',
                            'label' => 'setono_sylius_terms.ui.channels',
                            'options' => [
                                'template' => '@SetonoSyliusTermsPlugin/admin/grid/field/channels.html.twig',
                            ],
                        ],
                    ],
                    'actions' => [
                        'main' => [
                            'create' => [
                                'type' => 'create',
                            ],
                        ],
                        'item' => [
                            'update' => [
                                'type' => 'update',
                            ],
                            'delete' => [
                                'type' => 'delete',
                            ],
                        ],
                    ],
                ],
            ],
        ]], $this->container->getExtensionConfig('sylius_grid'));
    }
}
