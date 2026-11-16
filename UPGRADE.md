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
| All XML service files (`src/Resources/config/services.xml` and `src/Resources/config/services/*.xml`) | PHP equivalents (`config/services.php` and `config/services/*.php`) loaded through `PhpFileLoader`, with the same service ids, arguments and tags. |
