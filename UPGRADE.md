# Upgrade guide

## Upgrading from 2.x to 3.0

3.0 runs on Sylius 2. The 2.x line stays the Sylius 1 version of the plugin.

### Requirements

- PHP `>= 8.2`, Symfony `6.4` or `7.4`, Sylius `^2.2`.
- Dropped: Sylius 1.x (and 2.0/2.1), PHP `< 8.2`, Symfony `< 6.4` and `7.0`–`7.3`.
- Not yet supported: Symfony 8, Symfony UX 3.
- The plugin requires `sylius/shop-bundle: ^2.2`. Apps that install `sylius/sylius` already have it.

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
- A Form without a `label` gets the translation key `setono_sylius_terms.form.terms.term_form.<code>`. In 2.x the key was derived from the form type's short class name.
- The `setono_sylius_terms.forms` parameter is keyed by Form code and holds only the enabled Forms, each with `type`, `label`, `hooks` and `saved_event`.

### Plugin file layout

The plugin follows the Sylius 2 plugin skeleton. `src/Resources/{config,translations,views,public}` moved to the repository root:

| 2.x                       | 3.0             |
|---------------------------|-----------------|
| `Resources/config/`       | `config/`       |
| `Resources/translations/` | `translations/` |
| `Resources/views/`        | `templates/`    |
| `Resources/public/`       | `public/`       |

Paths to plugin files drop `Resources/`. Template paths (`@SetonoSyliusTermsPlugin/...`) and overrides in `templates/bundles/SetonoSyliusTermsPlugin/` are unchanged.

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
| `setono_sylius_terms.provider.terms` | `Setono\SyliusTermsPlugin\Provider\TermsProvider`, aliased as `Setono\SyliusTermsPlugin\Provider\TermsProviderInterface` |
| `setono_sylius_terms.renderer.label` | `Setono\SyliusTermsPlugin\Renderer\LabelRenderer`, aliased as `Setono\SyliusTermsPlugin\Renderer\LabelRendererInterface` |
| `setono_sylius_terms.twig.terms_extension` | `Setono\SyliusTermsPlugin\Twig\TermsExtension` |
| `setono_sylius_terms.twig.terms_runtime` | `Setono\SyliusTermsPlugin\Twig\TermsRuntime` |
| `TermsRepositoryInterface::findByFormAndChannelAndLocale()`, which took a form type class | `TermsRepositoryInterface::findByFormAndChannel()`, which takes a Form code, or the Form lookup, `Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface` |
| The translation key `setono_sylius_terms.form.terms.term_form.complete` | `setono_sylius_terms.form.terms.term_form.checkout_complete` |

Every service the plugin registers now uses its FQCN as its id; fetch or decorate it by that id, or by its interface where it has one. The ids Sylius generates for the resource (`setono_sylius_terms.factory.terms`, `setono_sylius_terms.repository.terms`, `setono_sylius_terms.manager.terms`) are unchanged.
