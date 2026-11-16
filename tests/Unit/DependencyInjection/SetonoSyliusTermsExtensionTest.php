<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractExtensionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction;
use Setono\SyliusTermsPlugin\DependencyInjection\SetonoSyliusTermsExtension;
use Setono\SyliusTermsPlugin\EventSubscriber\AddMenuSubscriber;
use Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory;
use Setono\SyliusTermsPlugin\Fixture\TermsFixture;
use Setono\SyliusTermsPlugin\Form\Extension\FormTypeExtension;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType;
use Setono\SyliusTermsPlugin\Form\Type\TermsType;
use Setono\SyliusTermsPlugin\Provider\TermsProvider;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Setono\SyliusTermsPlugin\Renderer\LabelRenderer;
use Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface;
use Setono\SyliusTermsPlugin\Twig\TermsExtension;
use Setono\SyliusTermsPlugin\Twig\TermsRuntime;
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
    }

    #[Test]
    public function it_aliases_interfaces_to_their_implementations(): void
    {
        $this->load();

        $this->assertContainerBuilderHasAlias(TermsProviderInterface::class, TermsProvider::class);
        $this->assertContainerBuilderHasAlias(LabelRendererInterface::class, LabelRenderer::class);
    }
}
