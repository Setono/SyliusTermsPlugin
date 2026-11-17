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
- The plugin no longer has to be registered before `SyliusGridBundle` in `config/bundles.php`.

### Default value and behaviour changes

#### `forms` is keyed by code and adds to the default Forms

In 2.x, `setono_sylius_terms.forms` was keyed by form type class, and setting it replaced the default (checkout complete only). In 3.0, each Form is keyed by a code, and the app's entries add to three default Forms: `checkout_complete`, `registration` and `contact`. A Terms stores the codes of its Forms instead of form type classes.

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

Markdown headings in rendered Content now carry `id` attributes, such as `id="content-payment"` for `## Payment`. See the README's Content section to loosen or tighten the sanitizer.

#### The `setono_terms` fixture

- `forms` takes Form codes, such as `[checkout_complete]`, instead of form type classes. A code that isn't configured fails the fixture load.
- `required` (default true), `position` (default last) and `locales` (default every locale) are new options.
- Content is Markdown (see above), and the faked content is Markdown with two `##` sections. The faked label holds a Link placeholder.
- A locale in `translations` now inherits the Terms' `name`, `slug`, `label` and `content` for the keys it leaves out. In 2.x they were faked.
- Left out, `channels` now defaults to three random channels, as the example factory always did. In 2.x the fixture passed an empty list, so the Terms got no channels.

#### Terms PDFs for the order confirmation email are a new opt-in

`setono_sylius_terms.order_confirmation_email.pdf` is new and `false` by default, so nothing changes until you switch
it on. To use it, run `composer require sylius/pdf-generation-bundle dompdf/dompdf symfony/http-client` and register
`SyliusPdfGenerationBundle`. See the README's "Terms in the order confirmation email" section.

#### The Terms page and `terms_link()` show the Terms version in force

- The Terms page shows the name and Content of the Terms version in force, with its number and the date it has been in force since. It is not found when the Terms has no version in force, as well as when the Terms is disabled, isn't in the channel or has no translation in the locale. `terms_link()` and the new `terms_path()` return nothing in exactly those cases, and the link text is the name of the version in force. Terms upgraded from 2.x have no version until `setono:sylius-terms:upgrade-from-2x` gives them version 1, so until then their pages are not found and `terms_link()` renders nothing.
- `terms_link()`'s second argument is now HTML attributes, such as `{class: 'nav-link'}`, not a template name, see "Removed classes, services, templates, files and translation keys" below.

### Plugin file layout

The plugin follows the Sylius 2 plugin skeleton. `src/Resources/{config,translations,views,public}` moved to the repository root:

| 2.x                       | 3.0             |
|---------------------------|-----------------|
| `Resources/config/`       | `config/`       |
| `Resources/translations/` | `translations/` |
| `Resources/views/`        | `templates/`    |
| `Resources/public/`       | `public/`       |

Paths to plugin files drop `Resources/`. Template paths (`@SetonoSyliusTermsPlugin/...`) and overrides in `templates/bundles/SetonoSyliusTermsPlugin/` are unchanged.

### Routing

- Route imports drop `Resources/`. Import `@SetonoSyliusTermsPlugin/config/routes.yaml`, or `@SetonoSyliusTermsPlugin/config/routes_no_locale.yaml` for non-localised stores:

    ```yaml
    # config/routes/setono_sylius_terms.yaml
    setono_sylius_terms:
        resource: "@SetonoSyliusTermsPlugin/config/routes.yaml" # was @SetonoSyliusTermsPlugin/Resources/config/routes.yaml
    ```

- The admin routes mount under `/%sylius_admin.path_name%` instead of a literal `/admin`, so they stay inside the admin firewall and access rule when the admin path is changed.
- The route `setono_sylius_terms_shop_show_terms` is renamed to `setono_sylius_terms_shop_terms_show`, with no alias. Prefer `terms_path(code)` over generating the URL by name.
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
| `src/Resources/config/app/fixtures.yaml`, the demo suite with the EULA Terms, the `EU_WEB` and `UA_WEB` channels and UAH | Nothing. The plugin ships no fixture suite. If you imported it, copy what you need into your own suite (see the README's Fixtures section). |
| The validation-group collector, the `PRE_SET_DATA` listener in `TermsCheckboxCollectionType` that gave each checkbox an `IsTrue` constraint in its parents' groups | The `terms` field's own validation group, `setono_sylius_terms_checkboxes`, with the `RequiredTermsTicked` constraint |
| `TermsCheckboxCollectionType`'s `error_message` option | The `setono_sylius_terms.terms_checkbox.required` key in the `validators` translation domain, which `RequiredTermsTicked` uses |
| `TermsCheckboxCollectionType`'s `terms` option, a list of `TermsInterface` | The same option, a list of `TermsVersionTranslationInterface` as the Form lookup returns them, and the new required `form` option, the Form code |
| `terms_link(code, template)`, whose second argument was the name of the template to render | `terms_link(code, attributes = {})`, whose second argument is the `<a>`'s HTML attributes, escaped. To change the markup, override `@SetonoSyliusTermsPlugin/shop/terms/link.html.twig`, which gets `path`, `terms`, `translation` (the Terms version in force) and `attributes`. `terms_path(code)` is new and returns the Terms page's path, or `null` |
| `LabelRendererInterface::render(TermsInterface $terms)` | `LabelRendererInterface::render(TermsVersionTranslationInterface $translation)`, which renders the Label of a Terms version |

`TermsRepositoryInterface` has a new method, `createListQueryBuilder()`, which feeds the admin grid. A repository that implements the interface without extending `TermsRepository` must add it.

Every service the plugin registers now uses its FQCN as its id; fetch or decorate it by that id, or by its interface where it has one. The ids Sylius generates for the resource (`setono_sylius_terms.factory.terms`, `setono_sylius_terms.repository.terms`, `setono_sylius_terms.manager.terms`) are unchanged.
