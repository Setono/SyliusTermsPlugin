<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Upgrade;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * The test application, with a newsletter Form configured on top of the default Forms, as a 2.x app that placed Terms on
 * its own newsletter form type would configure it in 3.0
 */
final class NewsletterFormKernel extends BaseKernel
{
    use MicroKernelTrait;

    /** The class needn't exist: the Form lookup and the upgrade match form types by name */
    public const NEWSLETTER_TYPE = 'App\Form\Type\NewsletterType';

    public function getProjectDir(): string
    {
        return dirname(__DIR__, 2) . '/Application';
    }

    public function getCacheDir(): string
    {
        return sprintf('%s/var/cache/%s_newsletter_form', $this->getProjectDir(), $this->getEnvironment());
    }

    /**
     * Runs once the bundles' extensions are registered, before the application's own config is loaded
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->loadFromExtension('setono_sylius_terms', [
            'forms' => [
                'newsletter' => [
                    'type' => self::NEWSLETTER_TYPE,
                    'hooks' => [],
                ],
            ],
        ]);
    }
}
