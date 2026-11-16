# Upgrade guide

## Upgrading from 2.x to 3.0

3.0 runs on Sylius 2. The 2.x line stays the Sylius 1 version of the plugin.

### Requirements

- PHP `>= 8.2`, Symfony `6.4` or `7.4`, Sylius `^2.2`.
- Dropped: Sylius 1.x (and 2.0/2.1), PHP `< 8.2`, Symfony `< 6.4` and `7.0`–`7.3`.
- Not yet supported: Symfony 8, Symfony UX 3.

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

Every service the plugin registers now uses its FQCN as its id; fetch or decorate it by that id, or by its interface where it has one. The ids Sylius generates for the resource (`setono_sylius_terms.factory.terms`, `setono_sylius_terms.repository.terms`, `setono_sylius_terms.manager.terms`) are unchanged.
