<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser\Admin;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, without the content editor's scripts, as for an app that disabled the markdown_toolbar
 * hookable, as the README's WYSIWYG recipe did before uploading images came along
 */
final class EditorScriptsDisabledKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_editor_scripts_disabled', $this->getProjectDir(), $this->getEnvironment());
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $hooks = [];

        foreach (['create', 'update'] as $page) {
            $hooks[sprintf('setono_sylius_terms.admin.terms.%s#javascripts', $page)] = ['markdown_toolbar' => ['enabled' => false]];
        }

        $container->loadFromExtension('sylius_twig_hooks', ['hooks' => $hooks]);
    }
}
