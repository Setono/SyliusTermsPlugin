# Sylius 2 shop integration surface: terms checkboxes, the terms page and footer links

Research for [#88](https://github.com/Setono/SyliusTermsPlugin/issues/88) (map [#87](https://github.com/Setono/SyliusTermsPlugin/issues/87)). It records facts for the decision tickets [#93](https://github.com/Setono/SyliusTermsPlugin/issues/93) (checkboxes on forms) and [#96](https://github.com/Setono/SyliusTermsPlugin/issues/96) (terms page and footer). It makes no decisions.

**Sources** (all primary, read on 2026-11-16, nothing executed):

- Sylius `v2.3.2` (latest stable). Older tags `v2.0.0`, `v2.1.0`, `v2.2.0` and a few patch tags for the "lowest minor" checks.
- `Sylius/TwigHooks` `v0.14.3`, plus `v0.5.0`–`v0.13.0` for when features appeared.
- `symfony/ux` `v2.36.2` (`ComponentWithFormTrait`), `symfony/symfony` `7.4` (DI, Config, Form, Twig bridge).
- PRs [#82](https://github.com/Setono/SyliusTermsPlugin/pull/82) and [#84](https://github.com/Setono/SyliusTermsPlugin/pull/84).
- `Setono/SyliusPluginSkeleton` `2.2.x` and its `sylius-v1-to-v2-upgrade-playbook.md`.

Sylius paths below are relative to the Sylius repo root at `v2.3.2` unless a tag is given. `ShopBundle/` means `src/Sylius/Bundle/ShopBundle/`. Behaviour claims come from reading the code, not from running it.

## Answer in brief

- **The checkout complete form is not a Live Component.** It is `sylius.controller.order::updateAction` rendering `CompleteType`. Its fields render through the hook `sylius_shop.checkout.complete.content.form`, whose hookables are `summary` (200), `extra_notes` (100) and `navigation` (0). A plugin hookable at, say, priority 50 lands between the notes and the Place order button. It reads the form from `hookable_metadata.context.form`.
- **A field that no hook renders is silently dropped.** Every shop form template ends with `form_end(form, {'render_rest': false})`. Since 2.2.6 the shop form theme's `form_end` renders only unrendered CSRF tokens, whatever `render_rest` says. With today's `IsTrue` constraint, an unrendered terms checkbox fails validation, but the error is never shown, so the customer cannot place the order and sees no error.
- **Other forms.** Registration renders through `sylius_shop.account.register.content.form` (a Live Component) and contact through `sylius_shop.contact.contact_request.content.form` (not live). Both end with a submit hookable at priority 0, the same layout as checkout complete. Sylius core has no standalone newsletter form; newsletter is only the `subscribedToNewsletter` checkbox on registration and profile.
- **Some shop form classes changed.** Registration, product review and the checkout address, shipping and payment steps now use `Sylius\Bundle\ShopBundle\Form\Type\…` classes, which declare the old `CoreBundle` classes as their `getParent()`. `FormTypeExtension` matches the exact inner type class, so Sylius 1 config values and stored `Terms::$forms` JSON values for those forms stop matching. `CompleteType` and `ContactType` did not change.
- **One generic mechanism is not possible.** Hooks are named after templates, not form types, and nothing in Sylius maps a form type to the hook that renders it. One hookable template can serve every form whose hook context carries `form`. Each form still needs its own hookable entry (hook name and priority). A form theme cannot force the field out either.
- **Live Component forms are fine for an unmapped `PRE_SET_DATA` field.** The form is rebuilt from scratch on every live request, so the listener runs again. Values round-trip through `formValues`, errors show only for touched fields, and the real submit is a normal POST. The catch: after a live re-render, hookables see only what the component template passes, not page-level context.
- **Standalone page.** Extend `@SyliusShop/shared/layout/base.html.twig` and override `title` and `content`. Optionally `{% set prefixes = [...] %}` at the top level to get page-specific header and footer hook names. Render one page hook whose tree is `content` → `header` (breadcrumbs) and `main`. Use the Bootstrap 5 `breadcrumbs()` macro from `@SyliusShop/shared/breadcrumbs.html.twig`.
- **Footer (2.2.7+ and all of 2.3).** Links go in `sylius_shop.base.footer.content.menu.informational_links` (`about` 300, `terms_and_conditions` 200, `privacy_policy` 100, `contact_us` 0) or `…menu.help_links`. Each link is `<a class="nav-link" href="…">`. Sylius' own `terms_and_conditions` and `privacy_policy` links are `href="#"` placeholders. **In 2.0.x, 2.1.x and 2.2.0–2.2.6** the footer menu is one hard-coded template; the only open hook is `sylius_shop.base.footer.content` (`menu` 200, `payment_methods` 100, `copy` 0).
- **`prepend()` cannot override Sylius' own hookables.** Hook config prepended by a plugin is merged before the Sylius hook YAML that apps import. A plugin can add hookables but cannot change the template or priority of a Sylius hookable or disable it (for example the placeholder footer links). The attempt is silently lost.
- **Checkout pages have no shop footer.** The checkout layout overrides the whole `body` block.
- **Lowest version.** Everything above exists since 2.0.0, with the same hook names and priorities, except the footer link hooks, which need **2.2.7** (backported in a 2.2 patch; absent from every 2.0.x and 2.1.x release). The skeleton's floor is `^2.2` and its dev pin `~2.2.10`. TwigHooks' hookable `condition` needs twig-hooks ≥ 0.13, which in practice means Sylius 2.3 with twig-hooks 0.14. Sylius 2.3 requires PHP `^8.3`; 2.2 still allows `^8.2`.

---

## 1. Checkout complete form

### Hooks and templates

- Route `sylius_shop_checkout_complete`: `_controller: sylius.controller.order::updateAction`, `form.type: Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType`, template `@SyliusShop/checkout/complete.html.twig` ([`ShopBundle/Resources/config/routing/checkout.yml` L68-89][checkout-route]).
- `checkout/complete.html.twig` sets `prefixes = ['sylius_shop.checkout']` and calls `{% hook 'complete' with { _prefixes: prefixes, order, form, active_step } %}` ([L7-10][complete-twig]).
- The hook tree ([`ShopBundle/Resources/config/app/twig_hooks/checkout/complete.yaml`][complete-yaml]):
  - `sylius_shop.checkout.complete` → `content`
  - `sylius_shop.checkout.complete.content` → `steps` 300, `header` 200, `flashes` 100, `form` 0
  - `sylius_shop.checkout.complete.content.form` → `summary` 200, `extra_notes` 100, `navigation` 0 (L30-39)
- The `form` hookable template opens and closes the `<form>` and calls `{% hook 'form' %}` between them ([`ShopBundle/templates/checkout/complete/content/form.html.twig`][complete-form-twig]):

  ```twig
  {% set form = hookable_metadata.context.form %}
  {% form_theme form '@SyliusShop/form/theme.html.twig' %}
  {{ form_start(form, {'action': path('sylius_shop_checkout_complete'), 'attr': {'novalidate': 'novalidate'}}) }}
  {{ form_errors(form) }}
  <input type="hidden" name="_method" value="PUT" />
  {% hook 'form' %}
  {{ form_end(form, {'render_rest': false}) }}
  ```

- Sylius' own field hookables read the form from the hook context, e.g. `extra_notes.html.twig`: `{% set form = hookable_metadata.context.form %}{{ form_row(form.notes, …) }}`. This works because the TwigHooks runtime merges the caller's Twig variables and the parent hookable's context into each child's context ([TwigHooks `HooksRuntime::getContext()` L147-155][th-runtime]).

### Is it a Live Component?

No. The shop's Live Components are listed in `ShopBundle/Resources/config/services/twig/component/*.php`. Checkout has only `sylius_shop:checkout:address:form`, `…:address:address_book`, `…:shipping:form` and `…:payment:form` ([`checkout.php` L26-68][checkout-components]). The complete step is a plain `ResourceController` form, so the live re-binding questions do not apply to it. Section 2.3 covers them for the forms that are live.

### Are unrendered fields output by `form_rest`/`form_end`?

**No, they are dropped silently, in every 2.x version, for two reasons:**

1. Every shop form template passes `render_rest: false` to `form_end`. A few templates make it configurable through `hookable.configuration.render_rest|default(false)`; checkout complete hard-codes `false`. See `grep form_end ShopBundle/templates`: checkout address/shipping/payment/complete, register, contact, cart, account pages, product review.
2. Since **2.2.6** the shop form theme overrides `form_end` and ignores `render_rest` entirely. It renders only CSRF-token children that are still unrendered, then `</form>` ([`ShopBundle/templates/form/theme.html.twig` L77-84][theme]). The override is absent in v2.0.18, v2.1.17, v2.2.1 and v2.2.5, and present in v2.2.6, v2.2.7, v2.2.8, v2.3.0 and v2.3.2. The same patch removed the explicit `{{ form_row(form._token) }}` from the checkout complete template (present in v2.2.5, gone in v2.2.6), and the register and contact templates lost theirs by v2.3.2.

A plugin-registered global form theme cannot work around this. The templates apply `@SyliusShop/form/theme.html.twig` with `{% form_theme form … %}`, and Symfony looks up blocks in per-view themes before the global `twig.form_themes`. From 2.2.6 the shop theme's `form_end` therefore always wins.

**Consequence for an `IsTrue` checkbox nobody renders:** Symfony's `FormType` sets `error_bubbling` to `compound && !inherit_data` ([`FormType.php` L154, L170][sf-formtype]), so a `CheckboxType` keeps its own error. The page shows only root errors (`{{ form_errors(form) }}`), so the order cannot be placed and no message appears. This is the Sylius 2 equivalent of the Sylius 1 README step "override `_form.html.twig`": the hookable is mandatory, not cosmetic.

### Markup the terms row gets

- The shop theme `{% extends 'bootstrap_5_layout.html.twig' %}`. It overrides `form_row` (adds `field mb-3`, `required` and `error` classes), `form_errors` (root errors in `.alert.alert-danger`, field errors as `.invalid-feedback.d-block`) and `checkbox_row` (widget, then label, then errors) ([theme L1-56][theme]).
- Symfony's Bootstrap 5 `checkbox_widget` wraps the input in `<div class="form-check">` with `.form-check-input`. The label renders inside the widget through `checkbox_radio_label` (`.form-check-label`), which skips the second `form_label` call so there is no double label. The label text goes through `form_label_content`, so `label_html: true` (used today for the Link placeholder) still works ([`bootstrap_5_layout.html.twig` L211-233, L295-320][sf-bs5]).
- The `IsTrue` error renders under its checkbox, because `checkbox_row` calls `form_errors(form)`.

### Where to put it

Both prior PRs used `sylius_shop.checkout.complete.content.form` → `terms` with `priority: 50`, which renders after `extra_notes` (100) and before `navigation` (0). The context key is `form`. `form.terms` exists only when `FormTypeExtension` added it, i.e. when Terms exist for this form, channel and locale. The template must therefore guard with `{% if form.terms is defined %}`, or use a hookable `condition` (see the version notes in section 6).

---

## 2. Other forms a Terms may go on

### Inventory (v2.3.2)

| Form | Form type FQCN (Sylius 2) | Sylius 1.14 FQCN | Live Component? | Hook that renders fields | Sibling priorities | Form in context as |
|---|---|---|---|---|---|---|
| Checkout complete | `Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType` | same | no | `sylius_shop.checkout.complete.content.form` | summary 200, extra_notes 100, navigation 0 | `hookable_metadata.context.form` |
| Registration (and `/register-after-checkout/{tokenValue}`) | `Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType` | `Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType` | **yes**, `sylius_shop:account:register:form` (`ResourceFormComponent`) | `sylius_shop.account.register.content.form` | personal_information 200, credentials 100, buttons 0 | passed explicitly: `{% hook 'form' with { form } %}` |
| Contact | `Sylius\Bundle\CoreBundle\Form\Type\ContactType` | same | no | `sylius_shop.contact.contact_request.content.form` | email 200, message 100, submit 0 | `{% hook 'sylius_shop.contact.contact_request' with { form } %}` |
| Checkout address | `Sylius\Bundle\ShopBundle\Form\Type\Checkout\AddressType` | `…\CoreBundle\Form\Type\Checkout\AddressType` | yes | (not inventoried) | | |
| Checkout shipping / payment | `…\ShopBundle\Form\Type\Checkout\SelectShippingType` / `SelectPaymentType` | `…\CoreBundle\…` | yes | (not inventoried) | | |
| Product review | `…\ShopBundle\Form\Type\Product\ProductReviewType` | `…\CoreBundle\…` | yes | (not inventoried) | | |
| Newsletter | none. Sylius core has no standalone newsletter form, only `subscribedToNewsletter` on registration and profile | | | | | |

Sources:

- Registration: route `sylius_shop_register` ([`routing/security.yml` L29-40][security-route]), hooks ([`twig_hooks/account/register.yaml`][register-yaml]: the `form` hookable is `component: 'sylius_shop:account:register:form'` with props `resource`, `form`, `template`), template ([`account/register/content/form.html.twig`][register-form-twig]), and component service ([`services/twig/component/account.php` L29-38][account-components]).
- Contact: [`twig_hooks/contact/contact_request.yaml`][contact-yaml], [`contact/contact_request.html.twig`][contact-twig], [`contact/contact_request/content/form.html.twig`][contact-form-twig]. The controller builds `ContactType` with `null` data, so `PRE_SET_DATA` still fires ([`ShopBundle/Controller/ContactController.php` L46-47][contact-controller]).
- Shop types and their `getParent()`: `ShopBundle/Form/Type/CustomerRegistrationType.php` L40-43 returns `Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType`, and likewise for `Checkout/AddressType`, `SelectShippingType`, `SelectPaymentType` and `Product/ProductReviewType`. Sylius 1.14's routes used the CoreBundle classes directly (`v1.14.0` `routing/security.yml` L30, `routing/checkout.yml` L21/41/59).

### The form type class change matters

`FormTypeExtension` reads `$event->getForm()->getConfig()->getType()->getInnerType()::class` and compares it to the configured list with `in_array(…, true)`. For the shop registration form the inner type is the ShopBundle class; the CoreBundle class is only its parent and is never the inner type of any shop form. Consequences:

- A Sylius 1 `setono_sylius_terms.forms` entry for `…\CoreBundle\Form\Type\Customer\CustomerRegistrationType` silently matches nothing on Sylius 2. The same holds for the Core checkout address, shipping and payment types.
- Terms store the selected form classes as JSON strings (`Terms::$forms`, `Terms.orm.xml` `forms` type `json`, matched with `LIKE` in `TermsRepository::findByFormAndChannelAndLocale`). Existing rows that hold those Core class names need remapping during the data upgrade (map: "Not yet specified → Data upgrade").

### Can one generic mechanism render the terms field on any configured form?

**Not a fully generic one.** What is generic and what is not:

- **Generic:** one hookable template, e.g. `{% set form = hookable_metadata.context.form %}{% if form.terms is defined %}{{ form_row(form.terms) }}{% endif %}`, works for all three inventoried forms. In each, the hook context carries the form as `form`, and the submit or navigation hookable sits at priority 0, so priority 50 lands just before it.
- **Not generic:** hook names follow the template tree (`sylius_shop.checkout.complete.content.form`, `sylius_shop.account.register.content.form`, `sylius_shop.contact.contact_request.content.form`), not the form type. No Sylius API returns "the hook that renders form type X". Each form needs its own `sylius_twig_hooks.hooks.<hook>.<name>` entry. For forms the plugin does not know about (custom app forms), the app must supply the hook name, or render `form.terms` itself.
- **Form-theme tricks don't help.** A block named after the field's block prefix (`setono_sylius_terms_terms_checkbox_collection_row`) runs only when someone renders the field. Overriding `form_end` loses to the shop theme, as shown in section 1.

Hook config is ordinary bundle config, so a plugin can build hookable entries from its own configuration in `prepend()`. For example, it can map a configured form type to a hook name and priority, with built-in defaults for the Sylius forms above. That is a design option for #93, not a decision.

### Live Component forms and an unmapped `PRE_SET_DATA` field

How it works, from `symfony/ux` [`ComponentWithFormTrait`][ux-trait] (v2.36.2; Sylius 2.3 allows `^2.36 || ^3.0`) and Sylius' [`ResourceFormComponentTrait`][ui-trait]:

- Initial render: the hookable passes the controller's `FormView` in as the `form` prop. `initializeForm()` stores it and extracts `formValues` from it, so the terms checkboxes start at `null` ([L75-98][ux-trait]).
- Every live re-render or action: `submitFormOnRender()` → `submitForm()` → `getForm()` → `instantiateForm()`. That is `$this->formFactory->create($this->formClass, $this->resource)` ([`UiBundle/Twig/Component/ResourceFormComponentTrait.php` L80-83][ui-trait]), a fresh form each request, so **`PRE_SET_DATA` fires again and `FormTypeExtension` re-adds `terms`**. The form is submitted with the full `formValues` ([L150-191][ux-trait]).
- Validation display: unless the whole component has been validated, `clearErrorsForNonValidatedFields()` removes errors from every field not in `validatedFields`. The `IsTrue` error therefore appears only after the customer touches that checkbox, or once the whole form is validated ([L166-176, L311-320][ux-trait]).
- The real submit is still a normal POST to the page route (`form_start(form, {'action': path('sylius_shop_register')})`), handled by `ResourceController::createAction`, where the field is built the same way.
- **Precondition:** the Terms lookup must give the same answer in the live sub-request. Live routes are `/{_locale}/_components/{_live_component}/{_live_action}` ([`ShopBundle/Resources/config/routing.yml` L11-14][shop-routing], mounted under `/{_locale}` by the app). Channel (hostname) and locale therefore resolve as on the page itself, and today's `TermsProvider` (channel + locale + form class) is stable.
- **Pitfall: hook context after a re-render.** `HookableLiveComponentTrait` dehydrates `hookable_metadata` to `renderedBy`, `configuration` and `prefixes` only, and rehydrates it with an **empty context** `DataBag` ([TwigHooks L36-37][th-live]). After a re-render, hookables inside a Live Component form see only what the component template passes (`with { form }`) and the component's own template variables. Page-level context such as `customer` or `order` is gone. The terms row reads only `form`, so it is unaffected.

The playbook's §7a warning, "`PRE_SET_DATA` listener … silently omitted on freshly-added rows", is about `LiveCollectionType` entries that read a parent back-reference. It does not apply here: the terms field depends only on the form type and request context, not on entity state.

---

## 3. A standalone shop page (the Terms page)

### Base layout and blocks

`@SyliusShop/shared/layout/base.html.twig` ([source][base-twig]):

```twig
{% set generic_hook = 'sylius_shop.base' %}
{% set prefixes = prefixes|default([])|merge([generic_hook]) %}
…<title>{% block title %}{{ sylius.channel.name }}{% endblock %}</title>…
{% block header %}{% hook 'header' with { _prefixes: prefixes } %}{% endblock %}
{% block content %}{% endblock %}
{% block footer %}{% hook 'footer' with { _prefixes: prefixes } %}{% endblock %}
```

It also has `#metatags`, `#stylesheets`, `head`, `#body_classes`, `offcanvas` and `#javascripts` hooks.

- The page template extends it and overrides `title` (Sylius convention: `{{ 'sylius.ui.contact_us'|trans }} | {{ parent() }}`) and `content`.
- **Prefixes:** a page that sets `{% set prefixes = ['x.y'] %}` at the top level of the child template (Sylius does it both before and after `extends`) gets its header and footer rendered from both `x.y.header` and `sylius_shop.base.header`, and likewise for the other hooks. Hookables are merged by name and the first prefix wins ([TwigHooks `HookablesRegistry::mergeHookables()` L100-118][th-registry], [`HookRenderer`][th-renderer]). Sylius pages that do this: `homepage/index.html.twig` (`sylius_shop.homepage.index`), `product/show.html.twig` and `order/thank_you.html.twig`. Pages that don't (`contact/contact_request.html.twig`, `account/register.html.twig`) just call one absolute page hook in `content`.
- Relative hook names (`{% hook 'content' %}` inside a hookable) rely on `sylius_twig_hooks.enable_autoprefixing: true`. Only `AdminBundle/Resources/config/app/config.yml` L81 sets it, but every standard app imports that file.

### Hook conventions for a page

The Sylius pattern is `<page hook>` → `content` (container wrapper) → `header` (breadcrumbs, title) and `main`. Examples:

- Contact: `sylius_shop.contact.contact_request` → `content` (`<div class="container my-auto"> … <div class="row justify-content-center mt-4 mb-5"><div class="col-12 col-md-6">`) → `header` (`<div class="mb-5"><h1 class="h2">…</h1>…</div>`) and `form`.
- Product reviews: `sylius.shop.product_review.index` → `content` → `header.breadcrumbs`, `sidebar` and `main` ([`twig_hooks/product_review/index.yaml`][review-yaml]). Note that Sylius itself is inconsistent here (`sylius.shop.` vs `sylius_shop.`).
- PR #84 already sketched this for the Terms page (section 5).

### Breadcrumbs and Bootstrap 5

- Macro `breadcrumbs(items)` in `@SyliusShop/shared/breadcrumbs.html.twig` ([source][breadcrumbs]) renders `<ol class="breadcrumb" aria-label="breadcrumbs">` with `<li class="breadcrumb-item fw-normal[ active]">`. Items are `{ label, path?, active?, test_attribute? }`. Account pages wrap it in `<div class="col-12">`.
- Item labels were printed `item.label|raw` in early 2.x releases (seen at v2.0.0, v2.2.0, v2.2.1). Later patches on each line escape them (seen at v2.1.17, v2.2.5+, v2.3.x).
- Markup is Bootstrap 5 throughout (`container`, `row`, `col-*`, spacing utilities, `nav-link`). Icons in Sylius templates use `ux_icon('tabler:…')`.

### Per-code page variants (input for #96)

The current per-code override (`ShowTermsAction` tries `@SetonoSyliusTermsPlugin/shop/terms/show/{code}.html.twig` and falls back on `LoaderError`) still works mechanically in Sylius 2. PR #84 kept it. The hook-native alternative: `{% hook %}` accepts a list of hook names and merges their hookables, with the earlier name winning. The checkout layout uses exactly this: `{% hook hooks|default([])|merge(['header']) … %}` with `hooks = [active_step ~ '.header']` ([`checkout/common/layout.html.twig` L11-14][checkout-layout]). A Terms page could do the same with a per-code hook name in front of the generic one. TwigHooks does not normalize hook names by default (`hook_name_section_separator: false`), so codes pass through as-is.

---

## 4. Footer links

### Sylius 2.2.7 and later (all of 2.3)

These hooks were backported into a 2.2 patch: absent at v2.2.6, present at v2.2.7, and identical at v2.3.2. Hook tree ([`twig_hooks/base.yaml` L162-209][base-yaml]):

- `sylius_shop.base.footer` → `content`
- `sylius_shop.base.footer.content` → `menu` 200, `payment_methods` 100, `copy` 0
- `sylius_shop.base.footer.content.menu` → `informational_links` 100, `help_links` 0 (each a `<div class="col-12 col-sm-6"><nav aria-label="…" class="nav flex-column">{% hook … %}</nav></div>`)
- `sylius_shop.base.footer.content.menu.informational_links` → `about` 300, `terms_and_conditions` 200, `privacy_policy` 100, `contact_us` 0
- `sylius_shop.base.footer.content.menu.help_links` → `faq` 200, `delivery_and_shipping` 100, `returns_policy` 0

Each link hookable is a single `<a class="nav-link" href="…">…</a>`. `terms_and_conditions.html.twig`, `privacy_policy.html.twig`, `about.html.twig` and the help links are **placeholders with `href="#"` and untranslated text**; only `contact_us` is real ([`…/footer/content/menu/informational_links/terms_and_conditions.html.twig`][footer-tc]).

A plugin can add one hookable that loops over the Terms meant for the footer and prints `nav-link` anchors into `informational_links`, or add a whole column to `sylius_shop.base.footer.content.menu`.

### Sylius 2.0.x, 2.1.x and 2.2.0–2.2.6

`sylius_shop.base.footer.content.menu` and everything below it does not exist. That includes the latest 2.0 and 2.1 patches (v2.0.18, v2.1.17). `footer/content/menu.html.twig` is one template with the same links hard-coded, `About`, `Terms and Conditions` (`href="#"`) and so on (template read at v2.0.0 and v2.2.0). The only extension points are new hookables in `sylius_shop.base.footer.content`, i.e. a new footer column next to `menu`, `payment_methods` and `copy`, or an app-level override of the `menu` hookable's template.

### Pages without a footer

`checkout/common/layout.html.twig` extends the base layout but overrides `{% block body %}`. It renders only the checkout header, content and sidebar, with no base header or footer ([L5-37][checkout-layout]). The terms links customers see during checkout therefore come only from the checkbox labels (Link placeholder).

### Plugin `prepend()` cannot replace or disable Sylius' own hookables

This matters for replacing the placeholder `terms_and_conditions` and `privacy_policy` links:

1. Sylius' hook YAML is **app-level config**. The app's `config/packages/_sylius.yaml` imports `@SyliusShopBundle/Resources/config/app/config.yml`, which imports `twig_hooks/**/*.yaml` ([`ShopBundle/Resources/config/app/config.yml` L4-7][shop-app-config]; same imports in the skeleton's `tests/Application/config/packages/_sylius.yaml`). No Sylius bundle prepends `sylius_twig_hooks`.
2. `ContainerBuilder::prependExtensionConfig()` does `array_unshift`, while `loadFromExtension()` (app config files) appends ([Symfony 7.4 `ContainerBuilder.php` L487-497, L758-765][sf-cb]). `Processor::process()` normalizes each config in order and merges later over earlier ([`Processor.php` L28-37][sf-processor]). **Sylius' YAML therefore always overrides a plugin's prepended values for the same `hook.hookable` keys.**
3. In TwigHooks, each hookable's `type` (`template`/`component`/`disabled`) is computed per config array in `beforeNormalization` ([`Configuration.php` L70-90][th-config]). The extension registers the hookable class from `type` alone ([`SyliusTwigHooksExtension::registerHooks()` L50-72][th-ext]).
   - A plugin that prepends `terms_and_conditions: { enabled: false }` gets `type: disabled`. Sylius' later `{ template, priority }` sets `type: template`, so it is still registered as a template: **the disable is silently ignored**.
   - A prepended `template` is overwritten the same way.

What does work:

- **Adding** hookables under new names.
- **The app** disabling or overriding Sylius hookables in its own `config/packages/*.yaml`, which loads after `_sylius.yaml`.
- A **compiler pass** that edits or removes the hookable service. Hookables become services with deterministic ids, `sylius_twig_hooks.hook.<hook>.hookable.<name>` ([`SyliusTwigHooksExtension.php` L129, L153, L174][th-ext]).

This is a design input for #96. Shipping hook YAML that the app imports (as both PRs did) instead of `prepend()` would also let the plugin override Sylius hookables, but only if the app imports it after `_sylius.yaml`, and the skeleton convention forbids it.

---

## 5. What the prior PRs did, and the pitfalls they hit

### PR #82 "Sylius 2.x compatibility" (`pr-82-sylius-v2`)

- **Checkout:** hookable `terms` in `sylius_shop.checkout.complete.content.form`, `priority: 50`. Template `src/Resources/views/shop/checkout/terms.html.twig`:

  ```twig
  {% if hookable_metadata.context.form.terms is defined %}
      {{ form_row(hookable_metadata.context.form.terms) }}
  {% endif %}
  ```

  It removed README "Step 5: Override checkout complete form" and the test app's `Checkout/Complete/_form.html.twig` override.
- **Hook config** lives in `src/Resources/config/twig/twig_hooks.yaml` and is imported by `src/Resources/config/app/config.yaml`, which the app must import (the old Sylius 1 convention).
- **Terms page not ported:** `src/Resources/views/shop/terms/show.html.twig` still does `{% extends '@SyliusShop/layout.html.twig' %}`. That template does not exist in Sylius 2 (`ShopBundle/templates/` has no `layout.html.twig` and no `Layout/` dir), so the page would fail with a Twig loader error. Semantic UI markup was left in place.
- **Test app:** added `templates/bundles/SyliusShopBundle/Layout/Header/_header.html.twig` (Semantic UI). That is a Sylius 1 override path which Sylius 2 never loads.
- **Footer, other forms, live components:** not touched.

### PR #84 "Upgrade to 2.0" (`pr-84-upgrade-to-2.0`, by the Sylius team)

- **Checkout:** same hook and `priority: 50` (`config/twig_hooks/shop/checkout/complete.yaml`). The template is **unguarded**:

  ```twig
  {% set form = hookable_metadata.context.form %}
  {{ form_row(form.terms, sylius_test_form_attribute('terms')) }}
  ```

  When no Terms apply to the current channel and locale, `FormTypeExtension` adds no `terms` child. With `strict_variables` the template then throws the error the README already troubleshoots ("Neither the property "terms" nor one of the methods…", #13). PR #82's `is defined` guard is the correct form.
- **Terms page:** `templates/shop/terms/show.html.twig` extends `@SyliusShop/shared/layout/base.html.twig`, sets `title` to `{{ terms.name }} | {{ parent() }}`, and calls `{% hook 'setono_sylius_terms.shop.terms.show' %}`. `terms` reaches hookables through the Twig variables. The tree (`config/twig_hooks/shop/terms/show.yaml`):
  - `…show` → `content` (`<div class="container mt-4 mb-5">`)
  - `…show.content` → `header` 100, `main` 0
  - `…show.content.header` → `breadcrumbs` (Sylius `breadcrumbs()` macro: Home, then `terms.name` active)
  - `…show.content.main` (`<div class="row mt-3">`) → `name` 100 (`<h1>`), `content` 0 (`{{ terms.content|raw }}`)

  It kept the per-code override in `ShowTermsAction`. The test app's `templates/bundles/SetonoSyliusTermsPlugin/shop/terms/show/eula_us.html.twig` re-implements the whole page without hooks.
- **Hook config shipped as YAML the app must import** (`config/config.yaml` imports `twig_hooks/**/*.yaml`). Only the test app imports it (`tests/TestApplication/config/config.yaml`). **The README never tells users to**, so the checkout field and the Terms page hooks would be missing in real installs. The grid moved to `prepend()`.
- **Constraint pitfall:** `composer.json` requires `sylius/twig-hooks: ^0.8` and `sylius/twig-extra: ^0.8`, i.e. 0.8.x only. Sylius 2.0.x requires `^0.6`, 2.1.x `^0.8`, 2.2.x `^0.9` and 2.3.x `^0.12 || ^0.14`, so PR #84 installs **only on Sylius 2.1.x** even though it declares `sylius/core-bundle: ^2.0`.
- **Footer, other forms, live components:** not touched.

### Common to both

Both chose `priority: 50` in `sylius_shop.checkout.complete.content.form`, and neither handled any form other than checkout complete. Neither added footer links.

---

## 6. Lowest Sylius 2 minor for each thing we'd rely on

Checked by diffing the files at `v2.0.0`, `v2.1.0` and `v2.2.0` (plus patch tags where noted) against `v2.3.2`.

| Surface | 2.0 | 2.1 | 2.2 | 2.3 | Notes |
|---|---|---|---|---|---|
| `sylius_shop.checkout.complete.content.form` (summary 200 / extra_notes 100 / navigation 0), `hookable_metadata.context.form` | ✓ | ✓ | ✓ | ✓ | `complete.yaml` and `complete.html.twig` identical since 2.0.0 |
| Checkout complete not a Live Component | ✓ | ✓ | ✓ | ✓ | |
| `CompleteType` FQCN / block prefix `sylius_checkout_complete` | ✓ | ✓ | ✓ | ✓ | file identical. Route passed `validation_groups: 'sylius_checkout_complete'` in 2.0/2.1. Since 2.2.0 the type's default `['sylius', 'sylius_checkout_complete']` applies (`CoreBundle/Resources/config/services/checkout.php` L33) |
| Unrendered fields dropped | ✓ | ✓ | ✓ | ✓ | `render_rest: false` everywhere. Shop theme `form_end` override from 2.2.6 |
| `sylius_shop.account.register.content.form` + `sylius_shop:account:register:form` component | ✓ | ✓ | ✓ | ✓ | `register.yaml` identical since 2.0.0 |
| `Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType` | ✓ | ✓ | ✓ | ✓ | identical since 2.0.0 |
| `sylius_shop.contact.contact_request.content.form` | ✓ | ✓ | ✓ | ✓ | `contact_request.yaml` identical since 2.0.0 |
| `@SyliusShop/shared/layout/base.html.twig`, `prefixes`, `sylius_shop.base.*` header/footer hooks | ✓ | ✓ | ✓ | ✓ | `#metatags` hook point since 2.0.0; Sylius' own `sylius_shop.base#metatags` hookable added in a 2.0 patch (absent v2.0.1, present v2.0.5) |
| `@SyliusShop/shared/breadcrumbs.html.twig` `breadcrumbs()` | ✓ | ✓ | ✓ | ✓ | labels `\|raw` in early releases; escaped in later patches (v2.1.17, v2.2.5+) |
| `sylius_shop.base.footer.content` (column level) | ✓ | ✓ | ✓ | ✓ | |
| **Footer link hooks** `…footer.content.menu`, `…menu.informational_links`, `…menu.help_links` | ✗ | ✗ | **✓ from 2.2.7** | ✓ | absent v2.0.18, v2.1.17, v2.2.0–v2.2.6; present v2.2.7–v2.2.12 and v2.3.x |
| TwigHooks hookable `condition: '@=…'` | ✗ | ✗ | ✗ | only with twig-hooks 0.14 | added in TwigHooks 0.13.0. Sylius twig-hooks constraints: 2.0.0 `^0.5`, 2.0.18 `^0.6`, 2.1 `^0.8`, 2.2 `^0.9`, 2.3 `^0.12 \|\| ^0.14`. A `{% if … is defined %}` guard works everywhere |
| TwigHooks prefix merging, `enabled: false`, `configuration`, `#` hooks | ✓ | ✓ | ✓ | ✓ | `HookablesRegistry::mergeHookables` and `enable_autoprefixing` present since TwigHooks 0.5.0 |
| PHP | ^8.2 | ^8.2 | ^8.2 | **^8.3** | from Sylius `composer.json`. Requiring Sylius ^2.3 makes PHP 8.3 the effective floor |
| `symfony/ux-live-component` | ^2.20 | ^2.20 | ^2.25 | ^2.36 \|\| ^3.0 | `ComponentWithFormTrait` behaviour above read at v2.36.2 |

For the skeleton's current `sylius/core-bundle: ^2.2` floor: everything in sections 1–3 is available. The footer link hooks need **2.2.7**. A `^2.2.7` floor (the skeleton's dev pin is already `~2.2.10`) would cover everything in this document while keeping PHP 8.2. Supporting 2.2.0–2.2.6 (or 2.0/2.1) would mean either falling back to a new column in `sylius_shop.base.footer.content` or no footer links on those versions.

---

## 7. Playbook errata candidates

| # | Playbook claim | Section | Evidence | What is actually true |
|---|---|---|---|---|
| E1 | "Form extensions (`AbstractTypeExtension`) — keep them … the Twig hook only positions the rendered widget in the UI" (also §22: "the hook only positions the rendered field") | §7, §22 | `ShopBundle/templates/form/theme.html.twig` L77-84 (v2.2.6+); every `form_end(form, {'render_rest': false})` in `ShopBundle/templates/**`; Symfony `FormType.php` L154/L170 (`error_bubbling`) | In the Sylius 2 shop a field added by a form extension is **not output at all** unless a hookable renders it; there is no `form_rest` fallback. If the field carries a constraint, the form fails validation with an error the user never sees. The hook is mandatory, not just positioning. |
| E2 | "When the plugin needs to configure another bundle (`sylius_grid`, `sylius_twig_hooks`, …), do it from `prepend()` … Don't ship YAML files that consumers have to import" | §5 (and skeleton `CLAUDE.md` "Configuring other bundles", "Templates") | Symfony 7.4 `ContainerBuilder::prependExtensionConfig()` L758-765 (`array_unshift`) vs `loadFromExtension()` L487-497; `Config/Definition/Processor::process()` L28-37; TwigHooks v0.14.3 `Configuration.php` L70-90 (`type` per config) and `SyliusTwigHooksExtension::registerHooks()` L50-72; Sylius hook YAML is app-level via `ShopBundle/Resources/config/app/config.yml` L4-7 | Missing caveat. Prepended `sylius_twig_hooks` config is merged **before** Sylius' own hook YAML (imported by the app's `_sylius.yaml`). A plugin can add hookables but **cannot override the template or priority of, or disable (`enabled: false`), any hookable Sylius defines**. The override is silently lost. Overriding needs app config or a compiler pass on `sylius_twig_hooks.hook.<hook>.hookable.<name>`. |
| E3 | "Add `Sylius\TwigHooks\SyliusTwigHooksBundle` … If your test app doesn't, hook templates silently fail to render." | §7 "Required bundle" | TwigHooks `src/Twig/HooksExtension.php` L37-40 registers `HookTokenParser` (tag `hook`); `ShopBundle/templates/shared/layout/base.html.twig` uses `{% hook %}` | Without the bundle, every Sylius 2 shop and admin template fails to compile ("Unknown "hook" tag"), and plugin `sylius_twig_hooks` config has no extension to load it. The failure is loud. The "silent" failure mode is a hookable registered under a hook name nobody renders (§16 is right about that). Low priority, and only tangentially shop-related. |

Not errata, but related: the playbook says almost nothing about the shop. It does not mention `render_rest: false`, Live Component re-render context loss (section 2), the footer link hooks that exist only from 2.2.7, or the ShopBundle form-class renames. Those could be additions rather than corrections.

---

## 8. Observations outside this ticket's scope (for deduplication)

- **Playbook vs skeleton on admin menus.** Playbook §7 says "Menu builders / `AdminMenuListener` — gone in 2.x. Migrate to a Twig hook". The skeleton `CLAUDE.md` says "Admin menu entries come from a listener on `sylius.menu.admin.main`". Sylius v2.3.2 still has `AdminBundle/Menu/MainMenuBuilder::EVENT_NAME = 'sylius.menu.admin.main'` (L28), so the skeleton is right and the playbook claim looks wrong. The admin research ticket should confirm.
- **Skeleton test app ships dead Sylius 1 shop overrides:** `tests/Application/templates/bundles/SyliusShopBundle/{_scripts,_styles}.html.twig`, `Homepage/_banner.html.twig`, `Layout/Footer/Grid/_plus.html.twig` and `Layout/Header/_logo.html.twig`, all Semantic UI. None of these paths exist in Sylius 2.3.2 `ShopBundle/templates/`, so they are never loaded. This is a skeleton issue, not a playbook one.
- **Validation group collector (#80).** `TermsCheckboxCollectionType` on `2.x` still has the `continue` without advancing `$parent`. Not re-examined here; #93 folds in the fix.

[checkout-route]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/routing/checkout.yml#L68-L89
[complete-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/checkout/complete.html.twig
[complete-yaml]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/checkout/complete.yaml
[complete-form-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/checkout/complete/content/form.html.twig
[checkout-components]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/services/twig/component/checkout.php
[account-components]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/services/twig/component/account.php
[theme]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/form/theme.html.twig
[security-route]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/routing/security.yml#L29-L40
[register-yaml]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/account/register.yaml
[register-form-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/account/register/content/form.html.twig
[contact-yaml]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/contact/contact_request.yaml
[contact-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/contact/contact_request.html.twig
[contact-form-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/contact/contact_request/content/form.html.twig
[contact-controller]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Controller/ContactController.php#L46-L47
[shop-routing]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/routing.yml#L11-L14
[shop-app-config]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/config.yml#L4-L7
[base-twig]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/shared/layout/base.html.twig
[base-yaml]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/base.yaml#L162-L209
[footer-tc]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/shared/layout/base/footer/content/menu/informational_links/terms_and_conditions.html.twig
[breadcrumbs]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/shared/breadcrumbs.html.twig
[checkout-layout]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/checkout/common/layout.html.twig
[review-yaml]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/Resources/config/app/twig_hooks/product_review/index.yaml
[ui-trait]: https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/UiBundle/Twig/Component/ResourceFormComponentTrait.php#L80-L83
[ux-trait]: https://github.com/symfony/ux/blob/v2.36.2/src/LiveComponent/src/ComponentWithFormTrait.php
[th-runtime]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/Twig/Runtime/HooksRuntime.php#L147-L155
[th-registry]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/Registry/HookablesRegistry.php#L100-L118
[th-renderer]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/Hook/Renderer/HookRenderer.php
[th-config]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/DependencyInjection/Configuration.php#L70-L90
[th-ext]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/DependencyInjection/SyliusTwigHooksExtension.php
[th-live]: https://github.com/Sylius/TwigHooks/blob/v0.14.3/src/LiveComponent/HookableLiveComponentTrait.php#L36-L37
[sf-formtype]: https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/Form/Extension/Core/Type/FormType.php
[sf-bs5]: https://github.com/symfony/symfony/blob/7.4/src/Symfony/Bridge/Twig/Resources/views/Form/bootstrap_5_layout.html.twig
[sf-cb]: https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/DependencyInjection/ContainerBuilder.php
[sf-processor]: https://github.com/symfony/symfony/blob/7.4/src/Symfony/Component/Config/Definition/Processor.php#L28-L37
