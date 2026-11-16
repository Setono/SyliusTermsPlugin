<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Grid;

use Setono\SyliusTermsPlugin\Tests\Functional\Grid\Entity\OverriddenTerms;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, with the Terms model overridden as an app would override it
 */
final class OverriddenTermsModelKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_overridden_terms_model', $this->getProjectDir(), $this->environment);
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->loadFromExtension('setono_sylius_terms', [
            'resources' => ['terms' => ['classes' => ['model' => OverriddenTerms::class]]],
        ]);

        $container->loadFromExtension('doctrine', [
            'orm' => ['mappings' => ['OverriddenTerms' => [
                'type' => 'attribute',
                'dir' => __DIR__ . '/Entity',
                'prefix' => 'Setono\SyliusTermsPlugin\Tests\Functional\Grid\Entity',
                'is_bundle' => false,
            ]]],
        ]);
    }
}
