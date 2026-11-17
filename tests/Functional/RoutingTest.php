<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction;
use Symfony\Bundle\FrameworkBundle\Routing\Router;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class RoutingTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_terms_page_route_is_served_under_the_locale(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        self::assertSame('/en_US/terms/privacy-policy', $router->generate('setono_sylius_terms_shop_terms_show', [
            '_locale' => 'en_US',
            'slug' => 'privacy-policy',
        ]));
        self::assertNull($router->getRouteCollection()->get('setono_sylius_terms_shop_show_terms'), 'The 2.x route name has no alias');
    }

    #[Test]
    public function the_terms_page_route_is_handled_by_the_show_terms_action(): void
    {
        $router = self::getContainer()->get(RouterInterface::class);
        self::assertInstanceOf(RouterInterface::class, $router);

        self::assertSame(ShowTermsAction::class, $router->getRouteCollection()->get('setono_sylius_terms_shop_terms_show')?->getDefault('_controller'));
        self::assertTrue(self::getContainer()->has(ShowTermsAction::class));
    }

    #[Test]
    #[DataProvider('provideImportFiles')]
    public function the_import_file_mounts_the_terms_page_and_the_admin_routes_under_the_admin_path(string $resource, string $termsPagePath): void
    {
        $routes = self::loadRoutes($resource);

        self::assertSame($termsPagePath, $routes->get('setono_sylius_terms_shop_terms_show')?->getPath());

        $adminPaths = [];
        foreach ($routes as $name => $route) {
            if (str_starts_with($name, 'setono_sylius_terms_admin_terms_')) {
                $adminPaths[$name] = $route->getPath();
            }
        }

        $names = array_keys($adminPaths);
        sort($names);
        // Core's CRUD routes without show, which the admin doesn't have, and without bulk delete, which would delete
        // several Terms and their versions past no warning
        self::assertSame([
            'setono_sylius_terms_admin_terms_create',
            'setono_sylius_terms_admin_terms_delete',
            'setono_sylius_terms_admin_terms_index',
            'setono_sylius_terms_admin_terms_update',
        ], $names);
        foreach ($adminPaths as $name => $path) {
            self::assertStringStartsWith('/back-office/terms', $path, $name);
        }
    }

    /**
     * @return \Generator<string, array{resource: string, termsPagePath: string}>
     */
    public static function provideImportFiles(): \Generator
    {
        yield 'localised' => [
            'resource' => '@SetonoSyliusTermsPlugin/config/routes.yaml',
            'termsPagePath' => '/{_locale}/terms/{slug}',
        ];

        yield 'not localised' => [
            'resource' => '@SetonoSyliusTermsPlugin/config/routes_no_locale.yaml',
            'termsPagePath' => '/terms/{slug}',
        ];
    }

    /**
     * Loads a route file through the kernel's routing loader and resolves its parameters as the app's router does.
     * The admin path is set to back-office, so an import that hard-codes /admin fails
     */
    private static function loadRoutes(string $resource): RouteCollection
    {
        $container = self::getContainer();
        $parameters = new ParameterBag(['sylius_admin.path_name' => 'back-office'] + $container->getParameterBag()->all());

        return (new Router($container, $resource, parameters: new ContainerBag(new Container($parameters))))->getRouteCollection();
    }
}
