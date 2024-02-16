<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use ReflectionClass;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use function Symfony\Component\String\u;

final class SetonoSyliusTermsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{forms: array<class-string, array{label: string|null}>, routing: array{terms: string}, resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));

        foreach ($config['forms'] as $form => $formConfig) {
            $reflectionClass = new ReflectionClass($form);
            $label = $formConfig['label'] ?? sprintf('setono_sylius_terms.form.terms.term_form.%s', u($reflectionClass->getShortName())->snake()->trimSuffix('_type')->toString());
            $config['forms'][$form]['label'] = $label;
        }

        $container->setParameter('setono_sylius_terms.forms', $config['forms']);
        $container->setParameter('setono_sylius_terms.terms_path', $config['routing']['terms']);

        $loader->load('services.xml');

        $this->registerResources(
            'setono_sylius_terms',
            SyliusResourceBundle::DRIVER_DOCTRINE_ORM,
            $config['resources'],
            $container,
        );
    }

    public function prepend(ContainerBuilder $container): void
    {
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

        $container->prependExtensionConfig('sylius_ui', [
            'events' => [
                'setono_sylius_terms.admin.terms.create.javascripts' => [
                    'blocks' => [
                        'javascripts' => [
                            'template' => '@SetonoSyliusTermsPlugin/admin/terms/_javascripts.html.twig',
                        ],
                    ],
                ],
            ],
        ]);
    }
}
