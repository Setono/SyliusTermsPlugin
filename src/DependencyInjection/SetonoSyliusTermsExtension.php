<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\DependencyInjection;

use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcher;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGenerator;
use Setono\SyliusTermsPlugin\Sanitizer\HeadingIdAttributeSanitizer;
use Sylius\Bundle\ResourceBundle\DependencyInjection\Extension\AbstractResourceExtension;
use Sylius\Bundle\ResourceBundle\SyliusResourceBundle;
use Sylius\PdfGenerationBundle\SyliusPdfGenerationBundle;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\HttpClient\HttpClient;

final class SetonoSyliusTermsExtension extends AbstractResourceExtension implements PrependExtensionInterface
{
    /** The name of the hookable that renders the Terms checkboxes in each hook of a Form */
    public const FORM_HOOKABLE = 'setono_sylius_terms_checkboxes';

    /** The Terms page's hook. The page merges it with the hook of the Terms' code, <this hook>.<code>, whose hookables win */
    public const TERMS_PAGE_HOOK = 'setono_sylius_terms.shop.terms.show';

    /** The admin Terms routes set this as their hook_prefix, see config/routes/admin.yaml */
    private const ADMIN_HOOK_PREFIX = 'setono_sylius_terms.admin.terms';

    /** The admin Terms version route sets this as its hook_prefix, see config/routes/admin.yaml */
    private const ADMIN_TERMS_VERSION_HOOK_PREFIX = 'setono_sylius_terms.admin.terms_version';

    public function load(array $configs, ContainerBuilder $container): void
    {
        /**
         * @var array{forms: array<string, array{type: class-string, label: string, hooks: array<string, int>, saved_event: string|null}>, routing: array{terms: string}, order_confirmation_email: array{pdf: bool}, resources: array} $config
         */
        $config = $this->processConfiguration($this->getConfiguration([], $container), $configs);
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));

        // The enabled Forms by code, with every key set. See Configuration
        $container->setParameter('setono_sylius_terms.forms', $config['forms']);
        $container->setParameter('setono_sylius_terms.terms_path', $config['routing']['terms']);

        $loader->load('services.php');

        self::listenToSavedEvents($container, $config['forms']);

        if ($config['order_confirmation_email']['pdf']) {
            $loader->load('services/conditional/pdf.php');
        }

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

        $config = $this->getConfigForPrepend($container);

        $container->prependExtensionConfig('sylius_twig_hooks', [
            'hooks' => self::getAdminHooks() + self::getTermsPageHooks() + self::getFormHooks($config['forms']),
        ]);

        if ($config['order_confirmation_email']['pdf']) {
            self::prependPdf($container);
        }
    }

    /**
     * The TermsAnsweredEvent dispatcher holds the Answers of a Form with a saved_event until that event, so it listens
     * to the saved_event of each enabled Form, once per event however many Forms name it
     *
     * @param array<string, array{saved_event: string|null}> $forms
     */
    private static function listenToSavedEvents(ContainerBuilder $container, array $forms): void
    {
        $dispatcher = $container->getDefinition(TermsAnsweredDispatcher::class);

        $savedEvents = array_filter(array_column($forms, 'saved_event'), static fn (?string $savedEvent): bool => null !== $savedEvent);

        foreach (array_unique($savedEvents) as $savedEvent) {
            $dispatcher->addTag('kernel.event_listener', ['event' => $savedEvent, 'method' => 'onSaved']);
        }
    }

    /**
     * The plugin's own config, processed, as far as it is known when the extensions are prepended
     *
     * @return array{forms: array<string, array{hooks: array<string, int>}>, order_confirmation_email: array{pdf: bool}}
     */
    private function getConfigForPrepend(ContainerBuilder $container): array
    {
        /** @var list<array<string, mixed>> $configs */
        $configs = $container->getParameterBag()->resolveValue($container->getExtensionConfig($this->getAlias()));

        /** @var array{forms: array<string, array{hooks: array<string, int>}>, order_confirmation_email: array{pdf: bool}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        return $config;
    }

    /**
     * One hookable per hook of each enabled Form, at the hook's priority. They all render the same template, which
     * renders the Terms checkboxes of the form in the hook's context, if it has any. A hook that several Forms list
     * gets one hookable, at the priority of the last of them
     *
     * @param array<string, array{hooks: array<string, int>}> $forms
     *
     * @return array<string, array<string, array{template: string, priority: int}>>
     */
    private static function getFormHooks(array $forms): array
    {
        $hooks = [];

        foreach ($forms as $form) {
            foreach ($form['hooks'] as $hook => $priority) {
                $hooks[$hook] = [
                    self::FORM_HOOKABLE => [
                        'template' => '@SetonoSyliusTermsPlugin/shop/form/terms.html.twig',
                        'priority' => $priority,
                    ],
                ];
            }
        }

        return $hooks;
    }

    /**
     * Fails the container build unless the PDF tooling the plugin only suggests is installed, then renders the Terms
     * PDFs with dompdf and caches them in their own pool. Apps change the context's adapter and the pool's adapter in
     * their own config
     */
    private static function prependPdf(ContainerBuilder $container): void
    {
        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        if (!in_array(SyliusPdfGenerationBundle::class, $bundles, true) || !class_exists(HttpClient::class)) {
            throw new \LogicException(sprintf(
                'The Terms PDFs (setono_sylius_terms.order_confirmation_email.pdf) need sylius/pdf-generation-bundle, dompdf/dompdf and symfony/http-client. Run "composer require sylius/pdf-generation-bundle dompdf/dompdf symfony/http-client" and register %s in config/bundles.php.',
                SyliusPdfGenerationBundle::class,
            ));
        }

        $container->prependExtensionConfig('sylius_pdf_generation', [
            'contexts' => [
                TermsPdfGenerator::CONTEXT => ['adapter' => 'dompdf'],
            ],
        ]);

        $container->prependExtensionConfig('framework', [
            'cache' => [
                'pools' => [
                    TermsPdfGenerator::CACHE_POOL => ['adapter' => 'cache.app'],
                ],
            ],
        ]);
    }

    /**
     * The hookables of the admin Terms pages. The index, create and update pages are core's shared CRUD pages, so only
     * what differs from core's sylius_admin.common hooks is set here
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private static function getAdminHooks(): array
    {
        $prefix = self::ADMIN_HOOK_PREFIX;

        $hooks = [
            $prefix . '.index.content.header.title_block' => [
                'title' => [
                    'template' => '@SyliusAdmin/shared/crud/common/content/header/title_block/title.html.twig',
                    'configuration' => [
                        'icon' => 'tabler:circle-check',
                        'subheader' => 'setono_sylius_terms.ui.manage_terms',
                    ],
                    'priority' => 100,
                ],
            ],
        ];

        foreach (['create' => 'setono_sylius_terms.ui.new_terms', 'update' => 'setono_sylius_terms.ui.edit_terms'] as $page => $header) {
            $hooks[sprintf('%s.%s.content.header.title_block', $prefix, $page)] = [
                'title' => [
                    'template' => sprintf('@SyliusAdmin/shared/crud/%s/content/header/title_block/title.html.twig', $page),
                    'configuration' => [
                        'header' => $header,
                    ],
                    'priority' => 100,
                ],
            ];

            $hooks[sprintf('%s.%s.content', $prefix, $page)] = [
                'form' => [
                    'component' => 'setono_sylius_terms:admin:terms:form',
                    'props' => [
                        'resource' => '@=_context.resource',
                        'form' => '@=_context.form',
                        'template' => '@SetonoSyliusTermsPlugin/admin/terms/form.html.twig',
                    ],
                    'configuration' => [
                        'render_rest' => false,
                    ],
                    'priority' => 0,
                ],
            ];

            $hooks[sprintf('%s.%s.content.form', $prefix, $page)] = [
                'general' => [
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/form/general.html.twig',
                    'priority' => 200,
                ],
                'placement' => [
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/form/placement.html.twig',
                    'priority' => 100,
                ],
                'translations' => [
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/form/translations.html.twig',
                    'priority' => 0,
                ],
                // Core's generic form sections, which the three cards above replace
                'sections' => [
                    'enabled' => false,
                ],
            ];

            $hooks[sprintf('%s.%s.content.form.general', $prefix, $page)] = self::getHookables('@SetonoSyliusTermsPlugin/admin/terms/form/general', ['code', 'enabled', 'required', 'position']);
            $hooks[sprintf('%s.%s.content.form.placement', $prefix, $page)] = self::getHookables('@SetonoSyliusTermsPlugin/admin/terms/form/placement', ['forms', 'channels']);
            $hooks[sprintf('%s.%s.content.form.translations', $prefix, $page)] = self::getHookables('@SetonoSyliusTermsPlugin/admin/terms/form/translations', ['name', 'slug', 'label', 'content']);

            // The content editor's three parts, each its own hookable so apps can disable or replace it: the Markdown
            // toolbar above the textarea, the Write and Preview tabs, and the script that defines the toolbar's elements
            $hooks[sprintf('%s.%s.content.form.translations.content#write', $prefix, $page)] = self::getHookables('@SetonoSyliusTermsPlugin/admin/terms/form/translations/content', ['toolbar']);
            $hooks[sprintf('%s.%s.content.form.translations.content#preview', $prefix, $page)] = self::getHookables('@SetonoSyliusTermsPlugin/admin/terms/form/translations/content', ['preview']);
            $hooks[sprintf('%s.%s#javascripts', $prefix, $page)] = [
                'markdown_toolbar' => [
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/javascripts.html.twig',
                    'priority' => -100,
                ],
            ];

            // Styles the rendered Content in the Preview tabs
            $hooks[sprintf('%s.%s#stylesheets', $prefix, $page)] = [
                'terms_content' => [
                    'template' => '@SetonoSyliusTermsPlugin/admin/terms/stylesheets.html.twig',
                    'priority' => -100,
                ],
            ];
        }

        // Next to core's Cancel and Update buttons, where Sylius' product page has View in store
        $hooks[$prefix . '.update.content.header.title_block.actions'] = [
            'view_in_store' => [
                'template' => '@SetonoSyliusTermsPlugin/admin/terms/update/view_in_store.html.twig',
                'priority' => 300,
            ],
        ];

        // Below the Live form and outside it, above core's footer at -100
        $hooks[$prefix . '.update.content']['versions'] = [
            'template' => '@SetonoSyliusTermsPlugin/admin/terms/update/versions.html.twig',
            'priority' => -50,
        ];

        return $hooks + self::getAdminTermsVersionHooks();
    }

    /**
     * The hookables of the read-only Terms version page. It is core's shared CRUD show page, so only what differs from
     * core's sylius_admin.common.show hooks is set here
     *
     * @return array<string, array<string, array{template: string, priority: int}>>
     */
    private static function getAdminTermsVersionHooks(): array
    {
        $prefix = self::ADMIN_TERMS_VERSION_HOOK_PREFIX . '.show';
        $templates = '@SetonoSyliusTermsPlugin/admin/terms_version/show';

        return [
            // Styles the rendered Content in the Preview tabs
            $prefix . '#stylesheets' => [
                'terms_content' => [
                    'template' => $templates . '/stylesheets.html.twig',
                    'priority' => -100,
                ],
            ],
            $prefix . '.content.header' => [
                'breadcrumbs' => [
                    'template' => $templates . '/breadcrumbs.html.twig',
                    'priority' => 100,
                ],
            ],
            $prefix . '.content.header.title_block' => [
                'title' => [
                    'template' => $templates . '/title.html.twig',
                    'priority' => 0,
                ],
            ],
            $prefix . '.content' => [
                'sections' => [
                    'template' => $templates . '/sections.html.twig',
                    'priority' => 0,
                ],
            ],
            $prefix . '.content.sections' => [
                'translations' => [
                    'template' => $templates . '/sections/translations.html.twig',
                    'priority' => 0,
                ],
            ],
            $prefix . '.content.sections.translations' => self::getHookables($templates . '/sections/translations', ['name', 'label', 'content']),
        ];
    }

    /**
     * The hookables of the Terms page: content → header (breadcrumbs), sidebar (table of contents, version) and main
     * (name, content), plus its stylesheet, which the page's prefixes load into the head
     *
     * @return array<string, array<string, array{template: string, priority: int}>>
     */
    private static function getTermsPageHooks(): array
    {
        $hooks = [
            self::TERMS_PAGE_HOOK . '#stylesheets' => [
                'terms_page' => [
                    'template' => '@SetonoSyliusTermsPlugin/shop/terms/show/stylesheets.html.twig',
                    // After Sylius' own stylesheets, at 0
                    'priority' => -100,
                ],
            ],
        ];

        // The hookables of each hook below the page's hook, in order. Their templates mirror the hook names
        $tree = [
            '' => ['content'],
            '.content' => ['header', 'sidebar', 'main'],
            '.content.header' => ['breadcrumbs'],
            '.content.sidebar' => ['table_of_contents', 'version'],
            '.content.main' => ['name', 'content'],
        ];

        foreach ($tree as $suffix => $names) {
            $hooks[self::TERMS_PAGE_HOOK . $suffix] = self::getHookables(
                '@SetonoSyliusTermsPlugin/shop/terms/show' . str_replace('.', '/', $suffix),
                $names,
            );
        }

        return $hooks;
    }

    /**
     * One hookable per name, in the given order, spaced by 100 so apps can put their own hookables in between. Each
     * renders the template named after it in the directory
     *
     * @param list<string> $names
     *
     * @return array<string, array{template: string, priority: int}>
     */
    private static function getHookables(string $templateDirectory, array $names): array
    {
        $hookables = [];
        $priority = (count($names) - 1) * 100;

        foreach ($names as $name) {
            $hookables[$name] = [
                'template' => sprintf('%s/%s.html.twig', $templateDirectory, $name),
                'priority' => $priority,
            ];

            $priority -= 100;
        }

        return $hookables;
    }
}
