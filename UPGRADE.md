# Upgrade guide

## Upgrading from 2.x to 3.0

3.0 runs on Sylius 2. The 2.x line stays the Sylius 1 version of the plugin.

### Requirements

- PHP `>= 8.2`, Symfony `6.4` or `7.4`, Sylius `^2.2`.
- Dropped: Sylius 1.x (and 2.0/2.1), PHP `< 8.2`, Symfony `< 6.4` and `7.0`–`7.3`.
- Not yet supported: Symfony 8, Symfony UX 3.
- The plugin requires `sylius/shop-bundle: ^2.2`. Apps that install `sylius/sylius` already have it.
- `league/commonmark` (`^2.10.2`) and `symfony/html-sanitizer` (`^6.4.50 || ^7.4.23`) are new requirements, and the plugin enables `framework.html_sanitizer`. The floors are the oldest releases without known security advisories.
- The plugin requires `sylius/grid-bundle: ^1.16`, and `symfony/ux-live-component` and `symfony/ux-twig-component` (`^2.25`). Apps that install `sylius/sylius` already have them.
- `twig/intl-extra` (`^3.26`, the oldest release without a known security advisory) is a new requirement, for the `format_date` filter on the Terms page and in the Terms PDF. Apps that install `sylius/sylius` already have it. The plugin doesn't register `Twig\Extra\Intl\IntlExtension`: Sylius' own templates need it too, so a Sylius app already registers it, as Sylius-Standard does (see the README's Installation section).
- The plugin no longer has to be registered before `SyliusGridBundle` in `config/bundles.php`.
- `setono/doctrine-orm-trait` (`^1.2`) is a new requirement. The plugin also requires `symfony/console` and `doctrine/persistence` (`^3.3`) now, which apps that install `sylius/sylius` already have.

### Upgrade steps

3.0 upgrades from 2.x only. A 1.x install (v1.1.0) has no `enabled` or `forms` columns and stores the label as `explanation`, so upgrade it to 2.x first.

Upgrade in this order:

1. Convert the `forms` config, see [`forms` is keyed by code and adds to the default Forms](#forms-is-keyed-by-code-and-adds-to-the-default-forms).
2. Generate and run the schema migration. The plugin ships no migrations:

    ```bash
    php bin/console doctrine:migrations:diff
    php bin/console doctrine:migrations:migrate
    ```

    Review the generated diff. It adds the `required` column with a database default of true, so every 2.x Terms stays required, the `position` column, and the Terms version tables, `setono_sylius_terms__terms_version` and `setono_sylius_terms__terms_version_translation`.
3. Run the upgrade command in the same deploy, before traffic:

    ```bash
    php bin/console setono:sylius-terms:upgrade-from-2x
    ```

Run the command with `--dry-run` after the schema migration and before the real run, on staging or in the deploy. It prints everything the command would do and writes nothing. Until the command runs, no Terms checkboxes show, and checkouts complete without them. Nothing in the admin or at runtime warns you that it hasn't run.

The command:

- Gives each Terms version 1, made from its name, label and content in every locale, as they are. Version 1 is in force from the moment the command runs, because 2.x never recorded when a Terms' text changed. Orders completed before then have no inferred Terms version, and resending their confirmation email attaches no Terms PDFs.
- Numbers the Terms' positions in id order, which is the order 2.x showed the checkboxes in.
- Replaces the form type classes stored on each Terms with Form codes, see below.
- Lists the Forms that aren't configured, and each Terms and locale whose Content may render differently, see [Content is Markdown that allows HTML](#content-is-markdown-that-allows-html-and-its-output-is-sanitised). It changes no Content.

All writes happen in one transaction. The command treats only a Terms without a version as 2.x data, so it is safe to run again, for example after configuring a Form for a leftover form type class.

Don't save Terms in the admin between the schema migration and the command. Saving a Terms gives it version 1 there and then, so the command leaves that Terms at the position the migration gave it, 0, and numbers the other Terms after it.

#### Form type classes become Form codes

The command replaces each form type class stored on a Terms with the code of the Form in your 3.0 `forms` config with that `type`. Failing that, a Sylius 1 form type becomes the code in this table. Anything else, such as your own form type, stays as it is, until you configure a Form with that type and run the command again. A class and its code on the same Terms become one code.

| 2.x stored class                                                       | Code                       | Configured by default | Sylius 2 form type                                               |
|------------------------------------------------------------------------|----------------------------|-----------------------|------------------------------------------------------------------|
| `Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType`             | `checkout_complete`        | yes                   |                                                                  |
| `Sylius\Bundle\CoreBundle\Form\Type\Customer\CustomerRegistrationType` | `registration`             | yes                   |                                                                  |
| `Sylius\Bundle\CoreBundle\Form\Type\ContactType`                       | `contact`                  | yes                   |                                                                  |
| `Sylius\Bundle\CoreBundle\Form\Type\Checkout\AddressType`              | `checkout_address`         | no                    | `Sylius\Bundle\ShopBundle\Form\Type\Checkout\AddressType`        |
| `Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectShippingType`       | `checkout_select_shipping` | no                    | `Sylius\Bundle\ShopBundle\Form\Type\Checkout\SelectShippingType` |
| `Sylius\Bundle\CoreBundle\Form\Type\Checkout\SelectPaymentType`        | `checkout_select_payment`  | no                    | `Sylius\Bundle\ShopBundle\Form\Type\Checkout\SelectPaymentType`  |
| `Sylius\Bundle\CoreBundle\Form\Type\Product\ProductReviewType`         | `product_review`           | no                    | `Sylius\Bundle\ShopBundle\Form\Type\Product\ProductReviewType`   |

A Terms on a Form that isn't configured shows on none of the shop's forms, and the admin shows that Form as "(not configured)", ticked, until an admin unticks it. For each of the four codes that aren't configured by default, either configure a Form with the code, its Sylius 2 form type and its hooks (see the README's "Adding a Form"), or untick it on the Terms in the admin.

### Default value and behaviour changes

#### `forms` is keyed by code and adds to the default Forms

In 2.x, `setono_sylius_terms.forms` was keyed by form type class, and setting it replaced the default (checkout complete only). In 3.0, each Form is keyed by a code, and the app's entries add to three default Forms: `checkout_complete`, `registration` and `contact`. A Terms stores the codes of its Forms instead of form type classes, and the upgrade command replaces the classes 2.x stored (see [Upgrade steps](#upgrade-steps)).

```yaml
# 2.x
setono_sylius_terms:
    forms:
        Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType: ~
        App\Form\Type\NewsletterType:
            label: app.form.newsletter

# 3.0: the CompleteType entry goes, since checkout_complete is a default
setono_sylius_terms:
    forms:
        newsletter:
            type: App\Form\Type\NewsletterType
            label: app.form.newsletter
            hooks: { app.newsletter.form: 50 } # or hooks: [] and render form.terms by hand
            saved_event: app.newsletter.post_subscribe # optional
```

- An entry with a default's code changes only the keys it sets. `checkout_complete: { hooks: { app.checkout.terms: 10 } }` replaces its hooks and keeps its type and saved event.
- `enabled: false` switches a default Form off, for example `contact: { enabled: false }`.
- Two enabled Forms can't have the same form type. 2.x keyed `forms` by class, so it couldn't list a class twice either, but a 2.x entry for `ContactType` or `CustomerRegistrationType` moved to a code of your own now clashes with the default `contact` or `registration` Form and fails the container build. Use the default's code, or switch the default off.
- A Form without a `label` gets the translation key `setono_sylius_terms.form.terms.term_form.<code>`. In 2.x the key was derived from the form type's short class name.
- The `setono_sylius_terms.forms` parameter is keyed by Form code and holds only the enabled Forms, each with `type`, `label`, `hooks` and `saved_event`.

#### Terms checkboxes

- The Label is escaped on every path, so HTML in a Label now shows as text. In 2.x, a Terms without content printed its Label unescaped. The Link placeholder (`[link:Link text]`) is the Label's only markup. Without content, nothing links, and a Link placeholder now shows just its link text instead of `[link:Link text]`.
- Each checkbox shows the Label of the Terms version in force, read through the Form lookup in the current channel and locale.
- Required terms are enforced in the `terms` field's own validation group, `setono_sylius_terms_checkboxes`, so the form's own validation groups no longer matter. In 2.x the field copied its parents' groups, which never enforced the terms on a form without array groups, such as the contact form.
- A required Terms' checkbox renders with the HTML `required` attribute and the shop theme's `required` class, and an optional Terms' checkbox renders without them. In 2.x every checkbox rendered without them.
- The `terms` field no longer renders the label "Accept all terms below to complete an order" above the checkboxes. To keep a heading, render it from your own hookable in the Form's hook.
- `$form->get('terms')->getData()` is the list of the ticked Terms' codes.

#### Content is Markdown that allows HTML, and its output is sanitised

2.x Content, which is HTML, carries over without conversion. Every rendering of it is sanitised: scripts, event handlers, unsafe URLs and `class` and `style` attributes are removed. Some 2.x Content renders differently:

- A line indented by four spaces after a blank line becomes a code block.
- Plain text that starts with `1.`, `-` or `#` becomes a list or a heading.
- Styling held in `class` and `style` attributes is dropped.

`setono:sylius-terms:upgrade-from-2x` lists each Terms and locale whose Content has one of these cases, and changes none of it. Check those Terms pages after the upgrade.

Markdown headings in rendered Content now carry `id` attributes, such as `id="content-payment"` for `## Payment`. See the README's Content section to loosen or tighten the sanitizer.

#### The `setono_terms` fixture

- `forms` takes Form codes, such as `[checkout_complete]`, instead of form type classes. A code that isn't configured fails the fixture load.
- Left out, `forms` now puts the Terms on no Form. In 2.x it put the Terms on checkout complete, so if your suite relies on that, set `forms: [checkout_complete]`.
- `required` (default true), `position` (default last) and `locales` (default every locale) are new options.
- Content is Markdown (see above), and the faked content is Markdown with two `##` sections. The faked label holds a Link placeholder.
- A locale in `translations` now inherits the Terms' `name`, `slug`, `label` and `content` for the keys it leaves out. In 2.x they were faked.
- Left out, `channels` now puts the Terms in every channel. In 2.x the fixture passed an empty list, so the Terms got no channels.

#### Terms PDFs for the order confirmation email are a new opt-in

`setono_sylius_terms.order_confirmation_email.pdf` is new and `false` by default, so nothing changes until you switch
it on. To use it, run `composer require sylius/pdf-generation-bundle dompdf/dompdf symfony/http-client` and register
`SyliusPdfGenerationBundle`. See the README's "Terms in the order confirmation email" section.

#### The Terms page and `terms_link()` show the Terms version in force

- The Terms page shows the name and Content of the Terms version in force, with its number and the date it has been in force since. It is not found when the Terms has no version in force or the version in force has no Content in the locale, as well as when the Terms is disabled, isn't in the channel or has no translation in the locale. `terms_link()` and the new `terms_path()` return nothing in exactly those cases, and the link text is the name of the version in force. Terms upgraded from 2.x have no version until `setono:sylius-terms:upgrade-from-2x` gives them version 1, so until then their pages are not found and `terms_link()` renders nothing.
- `terms_link()`'s second argument is now HTML attributes, such as `{class: 'nav-link'}`, not a template name, see "Removed classes, services, templates, files and translation keys" below.
- The Terms code `content` is reserved, because the page's hook for that code is the page's own `content` hook, so the page would render twice. A 2.x Terms coded `content` can't be saved in the admin until you change its code.

### Plugin file layout

The plugin follows the Sylius 2 plugin skeleton. `src/Resources/{config,translations,views,public}` moved to the repository root:

| 2.x                       | 3.0             |
|---------------------------|-----------------|
| `Resources/config/`       | `config/`       |
| `Resources/translations/` | `translations/` |
| `Resources/views/`        | `templates/`    |
| `Resources/public/`       | `public/`       |

Paths to plugin files drop `Resources/`. Template paths (`@SetonoSyliusTermsPlugin/...`) and overrides in `templates/bundles/SetonoSyliusTermsPlugin/` are unchanged.

The XML service files are replaced by PHP: `config/services.php` imports one file per concern from `config/services/`. The service ids changed too, see [Removed classes, services, templates, files and translation keys](#removed-classes-services-templates-files-and-translation-keys).

### Routing

- Route imports drop `Resources/`. Import `@SetonoSyliusTermsPlugin/config/routes.yaml`, or `@SetonoSyliusTermsPlugin/config/routes_no_locale.yaml` for non-localised stores:

    ```yaml
    # config/routes/setono_sylius_terms.yaml
    setono_sylius_terms:
        resource: "@SetonoSyliusTermsPlugin/config/routes.yaml" # was @SetonoSyliusTermsPlugin/Resources/config/routes.yaml
    ```

- The admin routes mount under `/%sylius_admin.path_name%` instead of a literal `/admin`, so they stay inside the admin firewall and access rule when the admin path is changed.
- The route `setono_sylius_terms_shop_show_terms` is renamed to `setono_sylius_terms_shop_terms_show`, with no alias. Prefer `terms_path(code)` over generating the URL by name.
- The admin routes `setono_sylius_terms_admin_terms_show` and `setono_sylius_terms_admin_terms_bulk_delete` are gone. The admin has no Terms show page, and it deletes one Terms at a time, past the warning that its versions are deleted too.
- The Terms page path and the `routing.terms` option are unchanged.

### Template paths

- The admin and shop templates are rebuilt around Twig hooks. Port template overrides to the matching hook.
- Admin hooks live under `setono_sylius_terms.admin.terms`, such as `setono_sylius_terms.admin.terms.update.content.form.translations` (see the README's Admin section). The Sylius 1 template events and the jQuery slug scripts are gone: the slug wand next to each slug is a Live Component action, and a slug left blank is made from the name on save.
- `templates/bundles` overrides of `admin/terms/_form.html.twig` or `admin/grid/field/channels.html.twig` no longer apply.
- Overridden templates that print content with `|raw` must switch to the `terms_content` filter, which renders and sanitises it: `{{ terms.content|terms_content }}`.
- The Terms page templates are rebuilt on Twig hooks. `shop/terms/show.html.twig` and `shop/terms/show/_breadcrumb.html.twig` are gone: the page is `shop/terms/page.html.twig`, which renders the hooks under `setono_sylius_terms.shop.terms.show`, with their templates under `shop/terms/show/`. Port overrides of the old templates to hookables (see the README's "Terms pages" section). An override of `shop/terms/show.html.twig` left in `templates/bundles` is ignored.
- The per-code template override `shop/terms/show/{code}.html.twig` is gone. Use the hook `setono_sylius_terms.shop.terms.show.<code>` instead: its hookables add to, override or, with `enabled: false`, hide the generic hookables of the same name on that Terms' page only.
- Terms checkboxes render through Twig hooks on each configured Form, so 2.x's manual override of the checkout complete form (`{{ form_row(form.terms) }}` in `templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig`) is no longer needed. Sylius 2 doesn't read that template, so delete it. See the README's "Terms checkboxes" section.

### Grid

The `setono_sylius_terms_terms` grid is now a PHP grid, `Setono\SyliusTermsPlugin\Grid\TermsGrid`, instead of grid config the plugin prepended. Apps that changed it with YAML must use an `#[AsGridMutator]` instead:

```php
#[AsGridMutator(grid: 'setono_sylius_terms_terms')]
final class TermsGridMutator implements GridMutatorInterface
{
    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        $gridBuilder->removeField('channels');
    }
}
```

On Sylius ≥ 2.3, a same-named YAML grid replaces the plugin's grid wholesale.

### Commands

`setono:sylius-terms:upgrade-from-2x` is new. It upgrades a 2.x database to 3.0 (see [Upgrade steps](#upgrade-steps)). It is shipped in 3.0 and removed in 4.0.

### `Sylius\Resource` names

The plugin uses `sylius/resource-bundle`'s `Sylius\Resource\…` names instead of the legacy `Sylius\Component\Resource\…` ones. Its model and repository interfaces extend the new interfaces, for example `TermsRepositoryInterface` extends `Sylius\Resource\Doctrine\Persistence\RepositoryInterface`, and the resources' default factory class is `Sylius\Resource\Factory\Factory`.

At runtime each legacy name is an alias of its new name, so code that implements, extends or type-hints the legacy names keeps working. Static analysers such as PHPStan read each legacy name as a subtype of its new name, though, so passing a plugin model or repository where a legacy name is expected may now be reported. Type against the `Sylius\Resource\…` names instead.

### Removed classes, services, templates, files and translation keys

| Removed | Replacement |
|---------|-------------|
| All XML service files (`src/Resources/config/services.xml` and `src/Resources/config/services/*.xml`) | PHP equivalents (`config/services.php` and `config/services/*.php`) loaded through `PhpFileLoader`, with the same arguments and tags. The service ids changed, see below. |
| `setono_sylius_terms.controller.action.show_terms` | `Setono\SyliusTermsPlugin\Controller\Action\ShowTermsAction` |
| `setono_sylius_terms.event_subscriber.add_menu` | `Setono\SyliusTermsPlugin\EventSubscriber\AddMenuSubscriber` |
| `setono_sylius_terms.fixture.example_factory.terms` | `Setono\SyliusTermsPlugin\Fixture\Factory\TermsExampleFactory` |
| `setono_sylius_terms.fixture.terms` | `Setono\SyliusTermsPlugin\Fixture\TermsFixture` |
| `setono_sylius_terms.form.type.terms` | `Setono\SyliusTermsPlugin\Form\Type\TermsType` |
| `setono_sylius_terms.form.type.terms_translation` | `Setono\SyliusTermsPlugin\Form\Type\TermsTranslationType` |
| `setono_sylius_terms.form.type.terms_checkbox_collection` | `Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType` |
| `setono_sylius_terms.provider.terms`, `Setono\SyliusTermsPlugin\Provider\TermsProvider` and `Setono\SyliusTermsPlugin\Provider\TermsProviderInterface` | The Form lookup, `Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface`, which takes the channel and locale and returns the Terms versions in force |
| `setono_sylius_terms.renderer.label` | `Setono\SyliusTermsPlugin\Renderer\LabelRenderer`, aliased as `Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface` |
| `setono_sylius_terms.twig.terms_extension` | `Setono\SyliusTermsPlugin\Twig\TermsExtension` |
| `setono_sylius_terms.twig.terms_runtime` | `Setono\SyliusTermsPlugin\Twig\TermsRuntime` |
| `src/Resources/public/slugger.js`, `slugify-terms-name.js` and `admin/terms/_javascripts.html.twig` | The wand button next to each slug, and a slug left blank is made from the name on save. |
| `admin/terms/_form.html.twig` | The hooks under `setono_sylius_terms.admin.terms.{create,update}.content.form` |
| `admin/grid/field/channels.html.twig` | Core's `@SyliusAdmin/shared/grid/field/channels.html.twig` |
| `TermsRepositoryInterface::findByFormAndChannelAndLocale()`, which took a form type class | `TermsRepositoryInterface::findByFormAndChannel()`, which takes a Form code, or the Form lookup, `Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface` |
| The translation key `setono_sylius_terms.form.terms.term_form.complete` | `setono_sylius_terms.form.terms.term_form.checkout_complete` |
| The translation key `setono_sylius_terms.form.terms_accept.label`, "Accept all terms below to complete an order" | Nothing. The `terms` field has no label, see [Terms checkboxes](#terms-checkboxes) |
| The translation key `setono_sylius_terms.form.terms_accept.terms_should_be_accepted`, which 2.x didn't use either | Nothing. The error next to an unticked required checkbox is `setono_sylius_terms.terms_checkbox.required` in the `validators` domain |
| `src/Resources/config/app/fixtures.yaml`, the demo suite with the EULA Terms, the `EU_WEB` and `UA_WEB` channels and UAH | Nothing. The plugin ships no fixture suite. If you imported it, copy what you need into your own suite (see the README's Fixtures section). |
| The validation-group collector, the `PRE_SET_DATA` listener in `TermsCheckboxCollectionType` that gave each checkbox an `IsTrue` constraint in its parents' groups | The `terms` field's own validation group, `setono_sylius_terms_checkboxes`, with the `RequiredTermsTicked` constraint |
| `TermsCheckboxCollectionType`'s `error_message` option | The `setono_sylius_terms.terms_checkbox.required` key in the `validators` translation domain, which `RequiredTermsTicked` uses |
| `TermsCheckboxCollectionType`'s `terms` option, a list of `TermsInterface` | The same option, a list of `TermsVersionTranslationInterface` as the Form lookup returns them, and the new required `form` option, the Form code |
| `terms_link(code, template)`, whose second argument was the name of the template to render | `terms_link(code, attributes = {})`, whose second argument is the `<a>`'s HTML attributes, escaped. To change the markup, override `@SetonoSyliusTermsPlugin/shop/terms/link.html.twig`, which gets `path`, `terms`, `translation` (the Terms version in force) and `attributes`. `terms_path(code)` is new and returns the Terms page's path, or `null` |
| `LabelRendererInterface::render(TermsInterface $terms)` | `LabelRendererInterface::render(TermsVersionTranslationInterface $translation)`, which renders the Label of a Terms version |

`TermsRepositoryInterface` has a new method, `createListQueryBuilder()`, which feeds the admin grid. A repository that implements the interface without extending `TermsRepository` must add it.

Every service the plugin registers now uses its FQCN as its id; fetch or decorate it by that id, or by its interface where it has one. The ids Sylius generates for the resource (`setono_sylius_terms.factory.terms`, `setono_sylius_terms.repository.terms`, `setono_sylius_terms.manager.terms`) are unchanged.
