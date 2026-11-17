<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Grid;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Tests\Functional\Grid\Entity\OverriddenTerms;
use Sylius\Component\Grid\Provider\GridProviderInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TermsGridTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return OverriddenTermsModelKernel::class;
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_grid_is_an_invokable_grid_of_the_configured_terms_model(): void
    {
        $container = self::getContainer();
        self::assertSame(OverriddenTerms::class, $container->getParameter('setono_sylius_terms.model.terms.class'));

        /** @var array<string, mixed> $arrayGrids */
        $arrayGrids = $container->getParameter('sylius.grids_definitions');
        self::assertArrayNotHasKey('setono_sylius_terms_terms', $arrayGrids, 'The grid is not an array grid');

        /** @var GridProviderInterface $gridProvider */
        $gridProvider = $container->get('sylius.grid.provider');
        $grid = $gridProvider->get('setono_sylius_terms_terms');

        self::assertSame('doctrine/orm', $grid->getDriver());
        self::assertSame(OverriddenTerms::class, $grid->getDriverConfiguration()['class']);
        self::assertTrue($grid->hasField('position'));
    }
}
