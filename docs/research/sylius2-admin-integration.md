# Sylius 2 admin integration surface for a translatable CRUD resource

Research for [#89](https://github.com/Setono/SyliusTermsPlugin/issues/89) (child of the 3.0 map [#87](https://github.com/Setono/SyliusTermsPlugin/issues/87)). This file holds facts for the decision tickets (mainly #94). It makes no decisions.

**Question:** how does a Sylius 2 plugin put a translatable CRUD resource (Terms) into the admin? That covers the menu, resource routes and templates, hook names, form sections, the grid, translations and slugs, admin JS, what PRs #82/#84 did, the lowest 2.x minor for each API, and playbook errata.

**Sources**, all primary and all read-only:

- Sylius **v2.3.2** source (latest stable). Paths below are relative to the repo root; links go to `https://github.com/Sylius/Sylius/blob/v2.3.2/<path>`.
- Older Sylius tags v2.0.0 to v2.2.0, fetched with `gh api repos/Sylius/Sylius/contents/<path>?ref=<tag>`, for the lowest-minor checks.
- `sylius/twig-hooks` v0.14.3, `sylius/grid-bundle` v1.13.0 to v1.16.0, `sylius/resource-bundle` v1.14.2. These are the versions Sylius 2.3.2's `composer.json` allows.
- `Sylius/Sylius-Standard` v2.0.4 to v2.3.0 (the reference app wiring) and `Sylius/PluginSkeleton` branch 2.2.
- `Setono/SyliusPluginSkeleton` 2.2.x, including `sylius-v1-to-v2-upgrade-playbook.md`.
- PR #82 (`pr-82-sylius-v2`) and PR #84 (`pr-84-upgrade-to-2.0`), compared against `2.x`.

The docs.sylius.com pages are behind a GitBook login and could not be fetched, so this file rests on source code only.

---

## TL;DR

- **Menu:** the KnpMenu event `sylius.menu.admin.main` with `Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent` exists in **every** 2.x release, so today's `AddMenuSubscriber` keeps working. Sylius 2.3 adds a second option: a `MenuProviderInterface` service tagged `sylius_admin.main_menu_provider`. 2.3 deprecates only the `MainMenuBuilder` *class*, not the event. Icons are `tabler:<name>` strings rendered with `ux_icon()`, and the sidebar template draws them **only for top-level items**. Playbook §7 is wrong when it says menu builders are "gone".
- **Routes and templates:** use `sylius.resource` with `templates: "@SyliusAdmin\\shared\\crud"`, plus `vars.all.hook_prefix: <own prefix>` (available since **2.0.4**). Without a prefix, the hooks fall back to `sylius_admin.terms.*` (from the resource name `terms`) and then `sylius_admin.common.*`. The common CRUD form is a **plain form, not a Live Component**: it renders `form_widget(form)` in a single card.
- **Form sections:** core's translatable CRUD resources hook a Live Component at `<prefix>.{create,update}.content` → `form`, using the generic `Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent` tagged `sylius.live_component.admin`. They then add `general` and `translations` cards under `<prefix>.{create,update}.content.form` and disable the common `sections`. The reference is `product_association_type`.
- **Translations:** shown as a per-locale **accordion**, not tabs, via the `with_hook` macro in `@SyliusAdmin/shared/helper/translations.html.twig`. Each locale renders the hook `<…>.form.translations` with `form` (the translation entry) and `locale` in context.
- **Slugs:** core has no generic slug widget. Product and taxon each use their **own Live Component** with a `#[LiveAction]` such as `generateProductSlug`. A wand button next to the slug field triggers it, defined as a form-theme block `_<block_prefix>_translations_entry_slug_row`. So **yes, the form must be a Live Component** to copy that pattern. A client-side Stimulus `slug` controller is registered in admin `controllers.json` but **no core template uses it**.
- **Grid:** Sylius 2.0–2.2 require grid-bundle `^1.13`; 2.3 requires `^1.16`. Admin field templates live in `@SyliusAdmin/shared/grid/field/`, and a ready-made `channels.html.twig` exists there since 2.0.0. Prepended array grids work on every 2.x. In 2.3, Sylius says PHP grids (`#[AsGrid]`, grid-bundle ≥ 1.14) are "the direction of Sylius 3.0".
- **Assets:** the Live Component route needs **no plugin JS at all**, because the `live` Stimulus controller is already in the admin build. Shipping Stimulus or Encore JS needs the consuming app to wire it, via `assets/admin/entrypoint.js` and `controllers.json` (Sylius-Standard ≥ 2.0.4). The Sylius 1 `sylius_ui.events` config node is gone in 2.x, so prepending it throws.
- **Lowest minor:** everything the Live Component pattern needs exists in **2.0.0**, except `hook_prefix` (**2.0.4**). Only conveniences are **2.3-only**: menu providers, automatic CSRF `_token` rendering in `form_end`, grid mutators, and core PHP grids. Sylius 2.3 requires **PHP ^8.3**.

---

## 1. Menu

### What exists

- **Sylius 2.0.0 to 2.2.x:** `Sylius\Bundle\AdminBundle\Menu\MainMenuBuilder` builds the menu and then dispatches `new MenuBuilderEvent($factory, $menu)` under `EVENT_NAME = 'sylius.menu.admin.main'` ([v2.0.0 `MainMenuBuilder.php`](https://github.com/Sylius/Sylius/blob/v2.0.0/src/Sylius/Bundle/AdminBundle/Menu/MainMenuBuilder.php)). Sylius 2.0 renamed the service to `sylius_admin.menu_builder.main` and the KnpMenu alias to `sylius_admin.main` (`UPGRADE-2.0.md` L1679, L2261–2271).
- **Sylius 2.3:**
  - `MainMenuBuilder` is `@deprecated since Sylius 2.3 and will be removed in Sylius 3.0. Use CompositeMenuBuilder instead.` (`src/Sylius/Bundle/AdminBundle/Menu/MainMenuBuilder.php` L24).
  - `CompositeMenuBuilder` loops over services tagged `sylius_admin.main_menu_provider` (`src/Sylius/Bundle/AdminBundle/Menu/CompositeMenuBuilder.php`; `Resources/config/services/menu.php`).
  - Each core section is a provider with a priority: dashboard −100, catalog −200, sales −300, customers −400, marketing −500, **configuration −600**, official_support −700, administration −800.
  - `EventMenuProvider` runs **last (priority −9999)** and dispatches the same `sylius.menu.admin.main` event with the same payload (`Menu/Provider/Main/EventMenuProvider.php` L24–34; `services/menu.php` L88–89).
  - `UPGRADE-2.3.md` L756–757: "listeners that only hook into this event require **no changes**." Neither the event nor `EventMenuProvider` is marked deprecated.
  - New in 2.3: `Sylius\Bundle\AdminBundle\Menu\Provider\MenuProviderInterface` (`__invoke(ItemInterface $menu): void`). A plugin can register one with the tag `sylius_admin.main_menu_provider`. To append under `configuration`, it must run after the configuration provider, so its priority must be **< −600**. Core does not autoconfigure this interface, so the plugin must add the tag explicitly. No `registerForAutoconfiguration` call exists in `AdminBundle/DependencyInjection`.
- **What Sylius 2.0 did remove:** the *page-specific* menu builders, i.e. `CustomerShowMenuBuilder`, `OrderShowMenuBuilder`, `ProductFormMenuBuilder`, `ProductUpdateMenuBuilder`, `ProductVariantFormMenuBuilder` and `PromotionUpdateMenuBuilder` (`UPGRADE-2.0.md` L1337–1342). Twig hooks replaced those, for example the product form's `side_navigation`. The **main** sidebar menu is still KnpMenu.

### How it renders and how icons are named

- The sidebar is the hook `sylius_admin.common.component.sidebar` → `menu` → (`search`, `items`) (`Resources/config/app/twig_hooks/common/component/sidebar.yaml`).
- `items` is `{{ knp_menu_render('sylius_admin.main', {'template': '@SyliusAdmin/shared/crud/common/sidebar/menu/menu.html.twig', …}) }}` (`templates/shared/crud/common/sidebar/menu/items.html.twig`). No hook sits *inside* the menu tree, so a Twig hook cannot put an item under "Configuration".
- Icons are Symfony UX Icons names in `prefix:name` form, e.g. core uses `->setLabelAttribute('icon', 'tabler:adjustments')`. The template renders them with `{{ ux_icon(icon_name) }}` (`menu.html.twig` L102–105). Sylius used `tabler:*` names from v2.0.0 on.
- The `child_item` block (`menu.html.twig` L81–100) renders **no icon**. An icon on a second-level item, such as Terms under Configuration, is never shown. Core still sets icons on child items anyway.
- Active-state matching on create and update pages uses `'extras' => ['routes' => [['route' => '…_create'], ['route' => '…_update']]]`, which core uses on every item.
- Icon resolution in 2.3.2: `SyliusUiExtension::prepend()` registers a local `tabler` icon set (82 SVGs in `UiBundle/Resources/assets/icons/tabler`, including `circle-check.svg`), enables Iconify on-demand fetching and sets `ignore_not_found: true` (`src/Sylius/Bundle/UiBundle/DependencyInjection/SyliusUiExtension.php` L37–61). In 2.0.0, UiBundle set only the default icon class, so icons relied on the ux-icons defaults (Iconify on demand).

### Takeaway for #94

Keeping an `EventSubscriber` on `sylius.menu.admin.main` works on all of 2.0–2.3 and is still supported in 2.3. Things that would need to change in today's subscriber:

- the Semantic UI icon string `check circle outline`, which is not a UX Icons name;
- optionally, adding `extras.routes` so the item stays active on create and update pages.

A `MenuProviderInterface` provider is the forward-looking 2.3+ option. It would force a 2.3 floor, and with it PHP 8.3.

## 2. Resource routes, CRUD templates and hook names

### Routing

Core's translatable CRUD resources (`Resources/config/routing/product_association_type.yml`) look like this:

```yaml
sylius_admin_product_association_type:
    resource: |
        alias: sylius.product_association_type
        section: admin
        permission: true
        templates: "@SyliusAdmin\\shared\\crud"
        except: ['show']
        redirect: update
        grid: sylius_admin_product_association_type
        form:
            type: Sylius\Bundle\AdminBundle\Form\Type\ProductAssociationTypeType
    type: sylius.resource
```

- `@SyliusAdmin\\Crud` (Sylius 1) no longer exists. The 2.x CRUD templates are `templates/shared/crud/{index,create,update,show}.html.twig`.
- `except: ['show']` matters. If a show route exists, the update page header renders a "Show" button (`templates/shared/crud/update/content/header/title_block/actions/show.html.twig`). The plugin's current routing does **not** exclude `show`.
- Sylius 1 route `vars` such as `subheader`, `templates.form` and `index.icon` are not read by the 2.x CRUD templates. The title, icon and subheader now come from hook configuration (`templates/shared/crud/common/content/header/title_block/title.html.twig` reads `hookable_metadata.configuration.{title,icon,subheader}`).
- The Live Component AJAX route is core's `sylius_admin_live_component` (`/{_locale}/_components/{_live_component}/{_live_action}`, `Resources/config/routing.yml` L16–19). Components tagged `sylius.live_component.admin` get it automatically (`Resources/config/app/config.yml` L52–56). The plugin defines no route for this.

### Hook prefixes

Each CRUD template computes its prefixes like this (`templates/shared/crud/update.html.twig` L3–7, and the same in `index` and `create`):

```twig
{% set prefixes = configuration.vars.hook_prefix is defined ? [configuration.vars.hook_prefix] %}
{% set prefixes = prefixes|default({})|merge([
    'sylius_admin.%resource_name%'|replace({'%resource_name%': resource_name|default(metadata.name)}),
    'sylius_admin.common'
]) %}
```

- `metadata.name` is the part of the alias after the dot, so `setono_sylius_terms.terms` gives `terms` (resource-bundle `Metadata::fromAliasAndConfiguration`). **Without** `hook_prefix`, the resource's own hooks are `sylius_admin.terms.*`. That is a generic name in core's namespace.
- `vars.all.hook_prefix: <prefix>` puts the plugin's own prefix first. It is **absent in v2.0.0–v2.0.3 and present from v2.0.4**, as verified on each tag.
- How the prefixes merge (`sylius/twig-hooks` `Registry/HookablesRegistry::mergeHookables()`, v0.14.3): hookables are merged **by hookable name**, and the **first** prefix wins. A plugin hookable named like a `sylius_admin.common.*` one replaces it. Any other common hookable still renders unless it is disabled with `enabled: false`.
- Twig-hooks config deep-merges across config sources, because the Configuration does not call `performNoDeepMerging`. Prepending `sylius_twig_hooks` from the extension (playbook §5) therefore combines safely with core's definitions.

### The hook tree a custom resource gets (`<p>` = the first prefix)

All of these come from `Resources/config/app/twig_hooks/common/{index,create,update}.yaml` and are **unchanged since v2.0.0**.

| Hook | Hookables (priority) |
|---|---|
| `<p>.index` | sidebar (200), navbar (100), content (0) |
| `<p>.index.content` | flashes (300), header (200), grid (100), footer (−100) |
| `<p>.index.content.header` | breadcrumbs (100), title_block (0) → `.title_block`: title (100), actions (0) |
| `<p>.index.content.grid` | filters (200), data_table (100), no_data_block (0) |
| `<p>.create` / `<p>.update` | sidebar, navbar, content |
| `<p>.create.content` / `<p>.update.content` | flashes (400), header (300), form_error_alert (200), **form** (100, `render_rest: true`), footer (−100) |
| `<p>.create.content.header.title_block.actions` | cancel (100), create (0) |
| `<p>.update.content.header.title_block.actions` | show (200, only if a show route exists), cancel (100), update (0) |
| `<p>.{create,update}.content.form` | sections (0) |
| `<p>.{create,update}.content.form.sections` | general (0) |
| `<p>.{create,update}.content.form.sections.general` | default (0) = `{{ form_widget(hookable_metadata.context.form) }}` |
| `<p>.{index,create,update}#stylesheets` / `#javascripts` | (empty; page-level asset hooks) |

The default `form` hookable is `templates/shared/crud/common/content/form.html.twig`. It is a **plain Symfony form**: `form_start` with `id: form.vars.id`, a hidden `_method=PUT` on update, `{% hook 'form' %}` and `form_end`. With no hook config, a resource therefore gets a working but unstructured form. Translations then render through the default widget, because the admin form theme has no `sylius_translations` block.

Header translation keys:

- Index title and breadcrumb: `<applicationName>.ui.<pluralName>`.
- Create and update titles: `sylius.ui.new|edit` followed by `<applicationName>.ui.<name>`.

Doctrine Inflector's English plural rule `s$ → s` keeps `terms` as `terms`, so both resolve to `setono_sylius_terms.ui.terms`, which already exists in `messages.*.yaml`.

Pitfalls for custom form templates:

- **Keep `'id': form.vars.id` on `form_start`.** The header Save button submits by `form="<id>"` (`templates/shared/crud/common/content/header/title_block/actions/update.html.twig`).
- **CSRF token.** In **2.0–2.2**, the shared form template rendered `{{ form_widget(form._token) }}` itself. In **2.3**, that line is gone and the admin form theme's `form_end` renders any unrendered `csrf_token` child (`templates/shared/form_theme.html.twig` L91–98). A custom template that supports < 2.3 with `render_rest: false` must render `form._token` explicitly. Doing so is harmless on 2.3.
- **A Live Component template must print `{{ attributes }}`** on its root element.

## 3. Form sections and the translatable form

### Core's pattern for a translatable CRUD resource

The pattern below is `product_association_type`, which has the fields code plus translations.name. Sources: `Resources/config/app/twig_hooks/product_association_type/{create,update}.yaml` and `templates/product_association_type/form/*`.

```yaml
'sylius_admin.product_association_type.update.content':
    form:
        component: 'sylius_admin:product_association_type:form'
        props:
            resource: '@=_context.resource'
            form: '@=_context.form'
            template: '@SyliusAdmin/shared/crud/common/content/form.html.twig'
        configuration:
            render_rest: false
'sylius_admin.product_association_type.update.content.form':
    general:      { template: '@SyliusAdmin/product_association_type/form/general.html.twig', priority: 100 }
    translations: { template: '@SyliusAdmin/product_association_type/form/translations.html.twig', priority: 0 }
    sections:     { enabled: false }
'sylius_admin.product_association_type.update.content.form.general':
    code: { template: '…/form/general/code.html.twig' }
'sylius_admin.product_association_type.update.content.form.translations':
    name: { template: '…/form/translations/name.html.twig' }
```

- The component is the **generic** `Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent`. Core registers it with the arguments repository, `form.factory`, model class and form class, plus the tag `sylius.live_component.admin` with a `key` (`Resources/config/services/twig/component.php` L164–171). It ships no component class of its own.
- `ResourceFormComponentTrait` gives `ComponentWithFormTrait`, `DefaultActionTrait`, `HookableLiveComponentTrait`, and a `resource` LiveProp hydrated by id. A new resource is created with `new $resourceClass()`. `TemplatePropTrait` adds the `template` prop.
- `general.html.twig` is a Bootstrap card wrapping `{% hook 'general' %}`. Each field partial is `form_row(hookable_metadata.context.form.<field>, sylius_test_form_attribute('<field>'))` inside a Bootstrap column.
- Core gives the admin its own form type: `AdminBundle\Form\Type\ProductAssociationTypeType`, whose `getParent()` returns the base type and whose `getBlockPrefix()` returns `sylius_admin_product_association_type`. Admin-only form-theme blocks can then target `_sylius_admin_…` names. For the plugin, `TermsType::getBlockPrefix()` is `setono_sylius_terms_terms`, so the slug row block would be `_setono_sylius_terms_terms_translations_entry_slug_row`. That name works because `ResourceTranslationsType` (block prefix `sylius_translations`) builds a `FixedCollectionType` whose entries are named by locale code with `block_name: 'entry'` (resource-bundle v1.14.2).
- Bigger forms use a **side navigation plus tab panes** layout. The product form uses `<p>.content.form.side_navigation` and `<p>.content.form.sections`, and each pane is shown or hidden through `hookable_metadata.configuration.active`. Playbook §7's product hook names are correct (`Resources/config/app/twig_hooks/product/update.yaml`).

### Translations UI

`templates/shared/helper/translations.html.twig` offers three macros:

- `with_hook(translation_fields, prefixes, hook_name = null, options)` renders a **Bootstrap accordion**. It has one item per locale and opens the first one. In each body it runs `{% hook hook_name|default('translations') with { form: translation_form, locale, _prefixes: prefixes } %}` (L26–42).
- `default(...)` renders `form_row` per field.
- `single_field(...)`.

Every core translatable form uses `with_hook`: product, product_option, product_attribute, shipping_method, payment_method, promotion, catalog_promotion, taxon and product_association_type. None uses tabs per locale. The macro exists in v2.0.0; only the accordion header markup changed (2.0.0 had a flag `<span>`, 2.3.2 has a chevron icon).

## 4. Slug generation, and whether the form must be a Live Component

- **Core mechanism (product, taxon):**
  - The resource's Live Component has a `#[LiveAction]`, e.g. `generateProductSlug(#[LiveArg] string $localeCode)` sets `formValues['translations'][$locale]['slug']` from `…['name']` (`Twig/Component/Product/FormComponent.php` L150–154). Taxon has `generateTaxonSlug`, which uses `TaxonSlugGeneratorInterface` and the parent.
  - A form-theme block puts a button beside the slug input (`templates/product/form_theme.html.twig` L13–31, `templates/taxon/form_theme.html.twig`):
    ```twig
    <button type="button" class="btn btn-outline-secondary"
            data-action="live#action"
            data-live-action-param="debounce(300)|generateProductSlug"
            data-live-locale-code-param="{{ locale }}">{{ ux_icon('tabler:wand', {'class': 'icon mx-0'}) }}</button>
    ```
  - The theme is applied in the component template with `{% form_theme form '@SyliusAdmin/product/form_theme.html.twig' %}`.
  - Slugs are generated **on demand when the button is clicked**, never automatically on name change.
- `data-action="live#action"` only works inside a Live Component root, so **this pattern needs the form rendered as a Live Component**. Playbook §7a says the same about Live actions in general (§7a "Constraint").
- **Generator service:** `sylius.generator.slug` / `Sylius\Component\Product\Generator\SlugGeneratorInterface` lives in `sylius/product`, not in a generic package. Its behaviour changed in 2.3:
  - 2.0–2.2: `Behat\Transliterator\Transliterator::transliterate()`.
  - 2.3: it wraps Symfony's `slugger` (`->slug($name)->lower()`), and constructing it without a slugger is deprecated (`src/Sylius/Component/Product/Generator/SlugGenerator.php`; `ProductBundle/Resources/config/services.php` L74–77).
  - A plugin could inject `Symfony\Component\String\Slugger\SluggerInterface` directly instead.
- **Client-side alternative that exists but is unused:**
  - `@sylius/admin-bundle` registers a Stimulus `slug` controller (`Resources/assets/controllers/SlugController.js`, identifier `sylius--admin-bundle--slug`, targets `sluggable`/`slug`, value `locale`, uses the `slugify` npm lib). It is enabled in AdminBundle's `controllers.json` (L25–30) from v2.0.0, and in Sylius-Standard's `assets/admin/controllers.json` from v2.1.0.
  - **No core template references it**: a grep for `--slug`, `slug-target` and `sluggable` finds nothing outside the JS.
  - It would work on a plain, non-Live form, but it is an unused, undocumented core controller, and its client-side `slugify` output can differ from the server generator.
- The plugin's current jQuery slugger (`src/Resources/public/slugger.js`, `slugify-terms-name.js`) is loaded through a `sylius_ui` *events* block. That config node **no longer exists**: `sylius_ui` accepts only `twig_ux` in 2.x (`UiBundle/DependencyInjection/Configuration.php`; template events removed in `UPGRADE-2.0.md` L1383–1527). Leaving the prepend in place would fail config validation.

## 5. Grid

- **Versions** (each tag's `composer.json`):

  | Sylius | `sylius/grid-bundle` |
  |---|---|
  | 2.0.0, 2.1.0, 2.2.0 | `^1.13` |
  | 2.3.0 | `^1.16` |

  The Setono skeleton has `sylius/grid-bundle: ^1.15` in require-dev.
- **Admin field templates** live in `@SyliusAdmin/shared/grid/field/`: `boolean`, `channel`, `channels`, `code`, `customer`, `date`, `label`, `name`, `order_number`, `payment_state`, `shipment_state`.
  - `channels.html.twig` renders each channel as a coloured status badge through `channel.html.twig`. It has been byte-identical since v2.0.0, and core's catalog-promotion grid uses it (`Resources/config/grids/catalog_promotion.yml` L32–38). The plugin's own `<ul>` template can be dropped for it; PR #84 did exactly that.
  - `name.html.twig` shows a "missing translation" marker when the value is null. Core's translatable grids pair it with a `createListQueryBuilder` repository method that joins the current locale's translation, e.g. `grids/product_association_type.yml`.
  - Column CSS comes from `options.vars.th_class` / `td_class`, which `@SyliusAdmin/shared/helper/table.html.twig` reads.
- **Actions:** AdminBundle maps grid action types to Bootstrap templates (`Resources/config/app/config.yml`, `sylius_grid.templates.action`): `create`, `update`, `delete`, `show`, `default`, `links`, `list`, `archive`, `apply_transition`, plus bulk `delete`.
  - `update` and `create` resolve routes from the resource's request configuration.
  - `delete` renders the `sylius_admin:delete_modal` component.
  - Icons are `tabler:*` names (the `icon:` option).
  - The plugin's current `create`/`update`/`delete` actions need no change.
- **Two ways to define a grid:**
  - Array config (prepend `sylius_grid`). Works on all 2.x, because the array provider is part of grid-bundle's chain provider.
  - PHP: `#[AsGrid(resourceClass: …, name: …)]` on an invokable class taking `GridBuilderInterface`, built with `TwigField::create()`, `Filter::create()`, `CreateAction::create()` and so on (e.g. `AdminBundle/Grid/CountryGrid.php`). `AbstractGrid` exists in grid-bundle ≥ 1.13, `#[AsGrid]` in ≥ **1.14** and grid mutators in ≥ **1.16**, all verified on grid-bundle tags.
- **Signals for Sylius 3:**
  - 2.3 introduced core PHP grids and `sylius_core.grid.use_legacy_config` (default `true`). Both are absent in 2.2.0.
  - `UPGRADE-2.3.md` L389–452: "PHP grid definitions are the recommended approach going forward and represent the direction of Sylius 3.0."
  - `CoreBundle/Grid/Provider/OverrideGridProvider.php` L21: "From 3.0 forward Sylius does not support the chain provider and only use Service grids."
  - This matters only beyond Sylius 2, but a 3.0 plugin written now could choose PHP grids. That needs grid-bundle ≥ 1.14 in the plugin's own `require` if Sylius < 2.3 is supported.

## 6. Admin assets (JS)

- **Core admin build:** `@SyliusAdmin/shared/layout/base/javascripts.html.twig` loads `encore_entry_script_tags('admin-entry', null, 'admin')`. That build includes `@symfony/ux-live-component`'s `live` controller and the `@sylius/admin-bundle` controllers (`Resources/assets/controllers.json` L1–23; `Resources/npm/index.js`).
- **App and plugin build (Sylius-Standard):**
  - Since Standard **v2.0.4**, a second `app.admin` Encore build sits next to core's admin build. Its entry is `assets/admin/entrypoint.js`, it has an `@vendor` alias for importing plugin entrypoints (e.g. `import '@vendor/sylius/refund-plugin/assets/entrypoint'` in v2.3.0), and its Stimulus controllers come from `assets/admin/controllers.json`. Core's own admin build still used `SyliusAdmin.getWebpackConfig()` in v2.0.4.
  - Since Standard **v2.1.0**, core's build uses `getBaseWebpackConfig()`, which has no Stimulus bridge. All admin Stimulus controllers then load in `app.admin` from a merge of `assets/controllers.json` and `assets/admin/controllers.json`. The latter lists the `@sylius/admin-bundle` controllers, including `slug`. The docblock of `getWebpackConfig()` says it is meant for sylius/sylius tests.
  - The app's `config/packages/_sylius.yaml` hooks `admin/javascripts.html.twig` (`app-admin-entry`) into `sylius_admin.base#javascripts` (v2.3.0).
  - **A plugin that ships JS therefore depends on the consumer** editing `assets/admin/entrypoint.js` and/or `controllers.json` and rebuilding.
- **First-party plugin convention:** `Sylius/PluginSkeleton` (branch 2.2) and `Sylius/RefundPlugin` ship `assets/admin/{entrypoint.js,controllers.json}`. In the PluginSkeleton both files are empty.
- **Per-page JS without a build** is possible through `<p>.{create,update}#javascripts` hooks, or app-wide through `sylius_admin.base#javascripts`. Script files go in a bundle `public/` dir and reach the app via `assets:install`. This is the direct successor of the plugin's old `sylius_ui` event. Nothing in core uses it for plugin JS.
- **Live Component route:** a `#[LiveAction]` such as slug generation needs **no plugin JS and no consumer build step**, because the `live` controller is already enabled in core's admin build and in Sylius-Standard's `assets/controllers.json`.
- **Setono skeleton test app (2.2.x):**
  - It builds admin with `SyliusAdmin.getWebpackConfig()` from `@sylius-ui/admin: file:../../vendor/sylius/sylius/src/Sylius/Bundle/AdminBundle`. That install path is deprecated since 2.3 (`AdminBundle/index.js`: use `AdminBundle/Resources/npm/`).
  - It still carries dead Sylius 1 files that 2.x never loads: `tests/Application/assets/admin/entry.js`, which imports `sylius/bundle/AdminBundle/Resources/private/entry`, a path that no longer exists, and `templates/bundles/SyliusAdminBundle/_scripts.html.twig`.
  - A plugin-owned Stimulus controller would **not** load in that test app without changing its webpack config. Live actions would.

## 7. What PR #82 and PR #84 did in the admin

Both require `sylius/sylius ^2.0` and PHP ≥ 8.2.

### PR #82 (`pr-82-sylius-v2`)

- Routing: `templates: "@SyliusAdmin\\shared\\crud"`, `except: [show]`, and the Sylius 1 `vars` removed. **No `hook_prefix`.**
- Hooks: in `src/Resources/config/twig/twig_hooks.yaml`, which consumers import via `src/Resources/config/app/config.yaml`. They use core's namespace `sylius_admin.terms.{create,update}.content` → a `form` component plus custom `form#left` / `form#right` hooks.
- Component: its own `Twig\Component\TermsComponent`, copied from the product component. It includes `LiveCollectionTrait` and `ComponentToolsTrait` it does not need, and the constructor parameter is still named `$productRepository`. It is tagged `sylius.live_component.admin` with key `setono_sylius_terms:terms:form` and injects `sylius.generator.slug`.
- Slug: `TermsTranslationType` gives the `name` field `data-action="live#action"` and `data-live-action-param="on(change)|generateTermsSlug"`, with the locale taken from `$builder->getPropertyPath()?->getElement(0)`.
  - Every name change **overwrites the slug**, including on update.
  - The form type now carries Live Component markup, which ties it to the admin component.
- Translations: rendered with a plain `form_row(form.translations)`, with **no accordion**.
- Other: the `sylius_ui` events prepend is commented out rather than deleted. The grid still points at the plugin's `<ul>` channels template. `AddMenuSubscriber` is untouched, with the Semantic UI icon name.

### PR #84 (`pr-84-upgrade-to-2.0`)

PR #84 follows the official `Sylius/PluginSkeleton` layout, not the Setono skeleton: XML services, `config/config.yaml` importing `config/twig_hooks/**`, empty `assets/{admin,shop}/{controllers.json,entrypoint.js}`, and `tests/TestApplication` on `sylius/test-application`.

- Routing: `templates: "@SyliusAdmin\\shared\\crud"`, `except: [show]`, `vars.all.hook_prefix: 'setono_sylius_terms.admin.terms'`. The `hook_prefix` silently requires **≥ 2.0.4** while composer says `^2.0`.
- Hooks (`config/twig_hooks/admin/terms/{create,update}.yaml`):
  - `<p>.{create,update}.content` → `form` is the component `setono_sylius_terms:admin:terms:form` with a custom template, `render_rest: false`, and `method: PUT` on update.
  - `<p>.…content.form.sections` → `general` (100) and `translations` (0).
  - `…sections.general` → `default` disabled, then `code`, `enabled`, `channels`, `forms`.
  - `…sections.translations` → `name`, `slug`, `label`, `content`.
- Component: `Twig\Component\Terms\FormComponent` (`ResourceFormComponentTrait` + `TemplatePropTrait`) with `#[LiveAction] generateTermsSlug(#[LiveArg] string $localeCode)` using `sylius.generator.slug`.
- Slug: a form-theme block `_setono_sylius_terms_terms_translations_entry_slug_row` with a wand button. This is a faithful copy of core's product pattern.
- Form template: a copy of core's product `form.html.twig`. It carries along the Semantic UI leftover class `ui loadable form` and the `hookable.configuration.render_rest` typo, which is undefined and falls back to `false`; core's product template has the same two quirks. It renders `form_widget(form._token)` explicitly, which is correct for < 2.3.
- Translations: the `translations.with_hook` accordion.
- Grid: switched to `@SyliusAdmin/shared/grid/field/channels.html.twig`.
- Menu: **kept `AddMenuSubscriber` on `sylius.menu.admin.main`**, added `extras.routes` for create/update, and changed the icon to `tabler:circle-check`. The icon is not displayed, because the item is a child of Configuration.
- Extension: moved `registerResources()` and the parameter setup from `load()` into `prepend()`, reading config with `getExtensionConfig()`. Core bundles call `registerResources()` from `load()`, e.g. `SyliusProductExtension.php` L45. `load()` now only loads services.

### Pitfalls seen across the PRs

1. **Hook config shipped as a YAML file the consumer must import, without saying so.** The PR #84 README never tells users to import `@SetonoSyliusTermsPlugin/config/config.yaml`; only its TestApplication does. Without the import, the pages fall back to `sylius_admin.common.*`: a generic non-Live form with no accordion and no slug button. Prepending `sylius_twig_hooks` from the extension (playbook §5) avoids this.
2. Missing `hook_prefix` (PR #82) puts plugin hooks into `sylius_admin.terms.*`.
3. `hook_prefix` combined with a `^2.0` constraint (PR #84) breaks on 2.0.0–2.0.3.
4. Automatic slug overwrite on name change (PR #82).
5. Copying core product templates also copies their quirks (PR #84).
6. A Sylius 1 `sylius_ui` events prepend left in place, or a Semantic UI icon string left in the menu.

## 8. Lowest Sylius 2 minor per API

"✓ 2.0.0" means the file was verified to exist on v2.0.0, and where noted its content was compared with v2.3.2.

| API / file the plugin would rely on | Lowest | Evidence |
|---|---|---|
| `sylius.menu.admin.main` + `UiBundle\Menu\Event\MenuBuilderEvent` | 2.0.0 | v2.0.0 `MainMenuBuilder.php` dispatches it; 2.3 `EventMenuProvider` |
| `tabler:*` icon names via `ux_icon()` in the sidebar | 2.0.0 | v2.0.0 `MainMenuBuilder.php`, `menu.html.twig` |
| `MenuProviderInterface` + tag `sylius_admin.main_menu_provider` | **2.3.0** | files absent in v2.0.0, v2.1.0, v2.2.0 |
| `@SyliusAdmin\\shared\\crud` templates; `sylius_admin.common.*` hooks | 2.0.0 | common `{index,create,update}.yaml` identical on v2.0.0 and v2.3.2 |
| `vars.hook_prefix` in CRUD templates | **2.0.4** | absent v2.0.0–v2.0.3, present v2.0.4 and later |
| `ResourceFormComponent` / `ResourceFormComponentTrait` / `TemplatePropTrait`; `sylius.live_component.admin` tag; `sylius_admin_live_component` route | 2.0.0 | trait and class identical on v2.0.0 and v2.3.2; v2.0.0 `config.yml` `live_component_tags` |
| Component-hookable pattern (`component:` + `props`) for CRUD forms | 2.0.0 | v2.0.0 `twig_hooks/product_association_type/update.yaml` |
| `translations.with_hook` accordion macro | 2.0.0 | only the header markup differs |
| Live-action slug button pattern (form-theme block + `#[LiveAction]`) | 2.0.0 | v2.0.0 `product/form_theme.html.twig` identical |
| `sylius.generator.slug` / `SlugGeneratorInterface` | 2.0.0 | behaviour switched to Symfony slugger in 2.3 |
| Stimulus `@sylius/admin-bundle/slug` controller | 2.0.0 | unused by core |
| `@SyliusAdmin/shared/grid/field/{channels,channel,name,code,boolean}.html.twig` | 2.0.0 | `channels` identical on v2.0.0 and v2.3.2 |
| Automatic CSRF `_token` in admin `form_end` | **2.3.0** | v2.0.0–v2.2.0: the shared form template renders `form._token`; v2.3.0: `form_theme.html.twig` `form_end` |
| `#[AsGrid]` PHP grids | grid-bundle 1.14 (Sylius 2.0 allows `^1.13`) | grid-bundle tags |
| Grid mutators; core PHP grids; `use_legacy_config` | **2.3.0** (grid-bundle 1.16) | absent in Sylius v2.2.0 |

Related version facts:

- Sylius **2.3.0** requires `php: ^8.3` and allows Symfony `^6.4.1 || ^7.4 || ^8.0`. 2.0–2.2 require `php: ^8.2`. 2.2.0 allows Symfony `^6.4.1 || ^7.4`.
- Sylius 2.3 also allows Symfony UX 3 (`^2.36 || ^3.0`). Per `UPGRADE-2.3.md` L583–594, UX 3 needs PHP ≥ 8.4 and Symfony `^7.4 || ^8.0`, so a plugin's Live Component must work on both UX 2.36+ and UX 3.

**Net:** the full Live Component pattern works on **2.0.4+**. Nothing in the admin surface forces 2.3, and therefore nothing here forces PHP 8.3. A **2.3 floor** would buy:

- the menu provider API;
- not having to render `_token` by hand;
- grid mutators.

It would cost the map's PHP 8.2 floor.

## 9. Playbook errata candidates (`Setono/SyliusPluginSkeleton` 2.2.x `sylius-v1-to-v2-upgrade-playbook.md`)

1. **§7 L309, echoed in §21 L1026: "Menu builders / `AdminMenuListener` — gone in 2.x. Migrate to a Twig hook on the admin menu hookpoint."**
   - **This is wrong for the main admin menu.** It is still KnpMenu in every 2.x. Listeners on `sylius.menu.admin.main` (`MenuBuilderEvent`) work in 2.0–2.3, and 2.3 keeps the event explicitly for BC: "listeners that only hook into this event require no changes".
   - 2.3 adds `MenuProviderInterface` with the tag `sylius_admin.main_menu_provider`. No hook exists inside the menu tree: `sylius_admin.common.component.sidebar.menu` holds only `search` and `items`, and `items` calls `knp_menu_render('sylius_admin.main')`.
   - What 2.0 actually removed were the page-specific builders (product form, order/customer show, promotion update, variant form).
   - Evidence: `src/Sylius/Bundle/AdminBundle/Menu/MainMenuBuilder.php` (v2.0.0 and v2.3.2), `Menu/Provider/Main/EventMenuProvider.php`, `Resources/config/services/menu.php`, `templates/shared/crud/common/sidebar/menu/items.html.twig`, `Resources/config/app/twig_hooks/common/component/sidebar.yaml`, `UPGRADE-2.3.md` L684–762, `UPGRADE-2.0.md` L1337–1342.
   - What is true: keep the event subscriber, or on 2.3+ add a tagged menu provider. Icons are `tabler:*` names, and child items render no icon.
2. **§6 L280–290: "Either works", meaning a literal `prefix: /admin` and `/%sylius_admin.path_name%` are presented as equivalent.**
   - `sylius_admin.path_name` comes from the env var `SYLIUS_ADMIN_ROUTING_PATH_NAME`. The admin firewall and the `ROLE_ADMINISTRATION_ACCESS` access_control both key off `sylius.security.admin_regex: "^/%sylius_admin.path_name%"`, and the shop firewall matches everything that is *not* under that path (`sylius.security.shop_regex: "^/(?!%sylius_admin.path_name%|api/.*|api$|media/.*)[^/]++"`).
   - If an app changes the admin path, plugin admin routes mounted at a literal `/admin` fall outside the admin firewall and outside the admin access rule. They then match the shop firewall.
   - Evidence: `src/Sylius/Bundle/AdminBundle/Resources/config/app/config.yml` L8–10, `src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml` L13, Sylius-Standard v2.3.0 `config/packages/security.yaml` L17–19, L73–74, L114–120.
   - What is true: only the `%sylius_admin.path_name%` prefix is safe in every app. The literal prefix works only while the app keeps the default `admin`. The full runtime behaviour was not exercised; this conclusion comes from reading the regexes.
3. **Gap, lower priority: §7/§7a never explain how a plugin's *own* CRUD resource becomes a Live Component.**
   - The generic CRUD form (`templates/shared/crud/common/content/form.html.twig`) is a plain form.
   - Getting Live actions (§7a) or the core slug button requires registering `Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent`, or a subclass using `ResourceFormComponentTrait`, with the tag `sylius.live_component.admin` and a `key`. That component is then hooked at `<prefix>.{create,update}.content` → `form` with `props: {resource, form, template}`.
   - Evidence: `Resources/config/services/twig/component.php` L164–171, `Resources/config/app/twig_hooks/product_association_type/update.yaml`.
4. **Minor, not admin-specific: §1 L52 pins `sylius/sylius: "~2.2.5"`, while the skeleton's `composer.json` has `~2.2.10` (and `sylius/core-bundle: ^2.2`).** §1 also states PHP `>=8.2` and Symfony `^6.4 || ^7.4` as the floor without noting that Sylius 2.3 requires PHP `^8.3`. Evidence: skeleton `composer.json`, and the Sylius v2.3.0 and v2.3.2 `composer.json` (`"php": "^8.3"`).

Correct in the playbook: the §7 product hook names and paths, `templates/product/form/sections.html.twig` and `Resources/config/app/twig_hooks/product/update.yaml`, which exist at those paths in 2.3.2. Also correct is §7a's statement that admin extends `bootstrap_5_layout.html.twig` and ships no generic collection Stimulus controller.

## 10. Facts that shape other tickets

- **#94 (admin decisions):** every option it lists is available on 2.0.4+:
  - menu via the existing subscriber;
  - accordion translations;
  - Live-action slug button, which replaces `slugger.js` and `slugify-terms-name.js` with no plugin JS;
  - core's `channels` grid field;
  - array or PHP grid.

  A Live Component form is also the base for later admin features that need server round-trips, e.g. `LiveCollectionType`.
- **Version floor (map "Not yet specified"):** the admin surface needs only 2.0.4. Requiring 2.3 for convenience would raise PHP to 8.3, against the map's "PHP ≥ 8.2".
- **#90 / #95 (content authoring):** Sylius 2 admin ships **no WYSIWYG or rich-text editor**. The npm deps in `Resources/npm/package.json` include none. A rich-text content field therefore means shipping JS, which needs consumer build wiring, or a CDN or `public/` script hooked into `<p>.{create,update}#javascripts`. That breaks the "no plugin JS" property of the Live-action route.
- **Test app:** the Setono skeleton's test-app admin build (`getWebpackConfig()`) would not load a plugin-owned Stimulus controller, and it carries dead Sylius 1 asset files. This only matters if 3.0 ships admin JS.
