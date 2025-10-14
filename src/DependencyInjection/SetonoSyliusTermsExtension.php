<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use ReflectionClass;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use function Symfony\Component\String\u;

final class SetonoSyliusTermsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        $loader->load('services.xml');
    }

    public function prepend(ContainerBuilder $container): void
    {
        /**
         * @psalm-suppress PossiblyNullArgument
         *
         * @var array{forms: array<class-string, array{label: string|null}>, routing: array{terms: string}, resources: array} $config
         */
        $config = $this->getCurrentConfiguration($container);

        foreach ($config['forms'] as $form => $formConfig) {
            $reflectionClass = new ReflectionClass($form);
            $label = $formConfig['label'] ?? sprintf('setono_sylius_terms.form.terms.term_form.%s', u($reflectionClass->getShortName())->snake()->trimSuffix('_type')->toString());
            $config['forms'][$form]['label'] = $label;
        }

        $container->setParameter('setono_sylius_terms.forms', $config['forms']);
        $container->setParameter('setono_sylius_terms.terms_path', $config['routing']['terms']);

        $this->registerResources('setono_sylius_terms', SyliusResourceBundle::DRIVER_DOCTRINE_ORM, $config['resources'], $container);

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
                                'template' => '@SyliusAdmin/shared/grid/field/channels.html.twig',
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

    /** @return array<array-key, mixed> */
    private function getCurrentConfiguration(ContainerBuilder $container): array
    {
        /** @var ConfigurationInterface $configuration */
        $configuration = $this->getConfiguration([], $container);
        $configs = $container->getExtensionConfig($this->getAlias());

        return $this->processConfiguration($configuration, $configs);
    }
}
