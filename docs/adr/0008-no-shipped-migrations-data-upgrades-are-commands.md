# The plugin ships no Doctrine migrations; data upgrades are rerunnable console commands

Apps generate the plugin's schema changes with `doctrine:migrations:diff`, as they did in 1.x and 2.x. When a major version changes the data, the plugin ships an idempotent console command for it: `setono:sylius-terms:upgrade-from-2x` in 3.0. The official Sylius plugins ship migrations instead. We didn't, for these reasons:

- Every existing install's tables were created by the app's own migrations, so a shipped migration would first have to detect them.
- A migration can't read the app's config. 3.0 needs that config to map custom form classes to Form codes.
- Shipped migrations need a variant per database platform, plus a CI setup with an old schema to migrate from.

A command runs through the plugin's own services on any platform Doctrine supports. It is tested in the functional suite, it can show what it would do with `--dry-run`, and it is safe to run again after the app's config changes.

## Consequences

- The upgrade is an extra deploy step. Until it runs, the upgraded data is incomplete. In 3.0 that means the shop shows no Terms checkboxes and checkouts complete without them. UPGRADE.md documents the step, and nothing guards against skipping it at runtime.
- The generated diff fills new columns on existing rows. A column whose implicit value would be wrong declares a database default in the mapping. In 3.0, `required` defaults to true; without that, MySQL would fill 0 and every 2.x Terms would turn optional.
