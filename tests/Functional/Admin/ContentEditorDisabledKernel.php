<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, with the content editor's three hookables disabled as the README's WYSIWYG recipe disables them
 */
final class ContentEditorDisabledKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_content_editor_disabled', $this->getProjectDir(), $this->getEnvironment());
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $hooks = [];

        foreach (['create', 'update'] as $page) {
            $prefix = 'setono_sylius_terms.admin.terms.' . $page;

            $hooks[$prefix . '#javascripts'] = ['markdown_toolbar' => ['enabled' => false]];
            $hooks[$prefix . '.content.form.translations.content#write'] = ['toolbar' => ['enabled' => false]];
            $hooks[$prefix . '.content.form.translations.content#preview'] = ['preview' => ['enabled' => false]];
        }

        $container->loadFromExtension('sylius_twig_hooks', ['hooks' => $hooks]);
    }
}
