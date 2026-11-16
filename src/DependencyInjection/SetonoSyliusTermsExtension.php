<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use Setono\SyliusTermsPlugin\Sanitizer\HeadingIdAttributeSanitizer;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

final class SetonoSyliusTermsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{forms: array<string, array{type: class-string, label: string, hooks: array<string, int>, saved_event: string|null}>, routing: array{terms: string}, resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        // The enabled Forms by code, with every key set. See Configuration
        $container->setParameter('setono_sylius_terms.forms', $config['forms']);
        $container->setParameter('setono_sylius_terms.terms_path', $config['routing']['terms']);

        $loader->load('services.php');

        $this->registerResources(
            'setono_sylius_terms',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Every rendering of Content goes through this sanitizer (ADR 0005). Apps loosen or tighten it in their own config
        $container->prependExtensionConfig('framework', [
            'html_sanitizer' => [
                'enabled' => true,
                'sanitizers' => [
                    'setono_sylius_terms.content' => [
                        // The elements Markdown produces, tables included, and the other safe elements 2.x content
                        // may hold, such as <u> or <div>. Unsafe elements, event handlers, class and style are dropped
                        'allow_safe_elements' => true,
                        'allowed_link_schemes' => ['http', 'https', 'mailto', 'tel'],
                        'allow_relative_links' => true,
                        'allowed_media_schemes' => ['http', 'https'],
                        'allow_relative_medias' => true,
                        'with_attribute_sanitizers' => [HeadingIdAttributeSanitizer::class],
                        // The default of 20,000 characters would cut off long terms
                        'max_input_length' => -1,
                    ],
                ],
            ],
        ]);

        $container->prependExtensionConfig('sylius_grid', [
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
        ]);
    }
}
