<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Uploader;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, with the storage of Uploaded images redefined as an app would move it, for example behind a CDN
 */
final class RedefinedStorageKernel extends BaseKernel
{
    use MicroKernelTrait;

    public const DIRECTORY = __DIR__ . '/../../Application/var/redefined_uploaded_image_storage';

    public const PUBLIC_URL = 'https://cdn.example.com/terms';

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_redefined_storage', $this->getProjectDir(), $this->getEnvironment());
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->loadFromExtension('flysystem', [
            'storages' => [
                'setono_sylius_terms.uploaded_image_storage' => [
                    'local' => ['directory' => self::DIRECTORY],
                    'public_url' => self::PUBLIC_URL,
                ],
            ],
        ]);
    }
}
