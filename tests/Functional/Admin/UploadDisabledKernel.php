<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Admin;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, with uploading images into the Content turned off as the README says: by disabling the upload
 * hookable
 */
final class UploadDisabledKernel extends BaseKernel
{
    use MicroKernelTrait;

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_upload_disabled', $this->getProjectDir(), $this->getEnvironment());
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $hooks = [];

        foreach (['create', 'update'] as $page) {
            $hooks[sprintf('setono_sylius_terms.admin.terms.%s.content.form.translations.content#write', $page)] = ['upload' => ['enabled' => false]];
        }

        $container->loadFromExtension('sylius_twig_hooks', ['hooks' => $hooks]);
    }
}
