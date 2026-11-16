# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

`setono/sylius-terms-plugin` adds terms checkboxes to Sylius forms and a shop page per Terms. `3.x` is the Sylius 2 line (Sylius 2.2+, PHP 8.2+, Symfony 6.4/7.4); `2.x` is the Sylius 1 line. Pull requests target the branch of the line they change, so always pass `--base` to `gh pr create`.

The plugin follows the [Setono/SyliusPluginSkeleton](https://github.com/Setono/SyliusPluginSkeleton) `2.2.x` layout: `config/`, `templates/`, `translations/` and `public/` live at the repository root, and the bundle class overrides `getPath()` and `getConfigFilesPath()` to point there.

## Commands

```bash
composer analyse                             # PHPStan (level max)
composer check-style                         # ECS check (sylius-labs/coding-standard)
composer fix-style                           # ECS auto-fix
composer phpunit                             # unit and functional suites
composer phpunit:unit                        # unit suite only (no kernel, no database)
composer phpunit:functional                  # functional suite (boots tests/Application, needs MySQL)
vendor/bin/rector process --dry-run          # check code modernization
vendor/bin/composer-dependency-analyser      # check dependency usage
./tests/Application/bin/console <command>    # test application console, run from the repository root
```

## Working in this repository

- Always use relative paths in shell commands. Absolute paths inside the working directory trigger a Claude Code permission prompt.
- If you `cd` into `tests/Application/` for a step, `cd` back to the project root before subsequent commands. Don't compensate by prepending an absolute path.
- Run the test-app console from the project root via `./tests/Application/bin/console <cmd>` instead of `cd tests/Application && php bin/console <cmd>`.
- Before each commit, run the code-quality tools and fix what they flag: `composer fix-style` (or `composer check-style` if you only want a report), `composer analyse` (PHPStan at `level: max`), and `composer phpunit`. Don't commit on top of pre-existing failures — re-run the suite locally first so CI doesn't catch regressions you could've caught in seconds.

## Booting the test app locally

```bash
cd tests/Application
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:schema:create
php bin/console sylius:fixtures:load default --no-interaction  # admin login: sylius / sylius
php bin/console assets:install public
yarn install && yarn build
symfony server:start                                            # https://127.0.0.1:8000/admin
```

- The test app and the functional tests require MySQL. Point `DATABASE_URL` at it in `tests/Application/.env.local`.
- On PHP 8.4+, a plain `composer update` resolves some `symfony/*` packages to 8.x, and every Doctrine command then fails with "Symfony LazyGhost is not available" ([Setono/SyliusPluginSkeleton#112](https://github.com/Setono/SyliusPluginSkeleton/issues/112)). CI doesn't hit this because it pins Symfony through `SYMFONY_REQUIRE`. Until the skeleton lands a fix, pin them locally: `composer update $(for p in error-handler mime twig-bridge type-info var-dumper var-exporter web-link; do printf -- '--with symfony/%s:~7.4.0 ' $p; done)`.
- Before running `symfony server:start`, run `symfony server:status` first — the test app server may already be up from a previous step. Starting a second instance fails noisily.
- `symfony server:status` is **per-project**: it inspects the cwd to figure out which app to ask about. Always run `cd tests/Application && symfony server:status` before deciding whether to start a new server.
- `symfony server:start --dir=...` interprets the dir relative to its own internals and rejects relative paths; pass `$(pwd)/tests/Application` if you really need to start a fresh server.

## Code Quality

- PHP >=8.2, targeting Symfony 6.4/7.4
- Dev tooling (PHPStan, PHPUnit, Rector, ECS, Infection) comes from `setono/sylius-plugin`
- PHPStan at `level: max` with Sylius/Symfony/Doctrine/PHPUnit/strict-rules extensions
- ECS imports `sylius-labs/coding-standard`
- All tools skip `tests/Application/`
- `declare(strict_types=1);` required in all PHP files
- CI uses the `setono/sylius-plugin/*@v2` composite GitHub Actions

## Conventions

### Services

- Services are configured in PHP, never XML: `config/services.php` imports one file per concern from `config/services/`, loaded through `PhpFileLoader`.
- All new services must be registered using their FQCN as the service id (e.g. `Setono\SyliusTermsPlugin\Validator\Constraints\FooValidator`), not a snake-cased alias like `setono_sylius_terms.validator.foo`. This matches Symfony's autowiring conventions and lets consumers override or decorate by class name. The plugin still has older snake-cased ids — leave those as-is unless the surrounding work touches them, but don't add any new ones.
- When the plugin needs to configure another bundle (`sylius_grid`, `sylius_twig_hooks`, doctrine, etc.), do it from `SetonoSyliusTermsExtension::prepend()` via `$container->prependExtensionConfig(...)` with the config inlined as a PHP array. Don't ship YAML files that consumers have to import.
- For services that need a Doctrine `EntityManager`, don't inject `EntityManagerInterface` (or `setono_sylius_terms.manager.terms`) directly. Inject `Doctrine\Persistence\ManagerRegistry` plus the relevant `class-string` (e.g. `%setono_sylius_terms.model.terms.class%`) and `use Setono\Doctrine\ORMTrait;` so the manager is resolved lazily via `$this->getManager($class)`.

### Twig

- Twig extensions should split into an `Extension` (eagerly loaded, declares functions/filters) and a `Runtime` (lazily instantiated, holds dependencies and runs the logic). Wire functions via `[Runtime::class, 'method']` and tag the runtime service with `twig.runtime`.

### Translations

- When adding or updating translation keys, update every locale file in `translations/` (e.g. `messages.en.yaml`, `messages.da.yaml`, ...), not just English. Missing translations leak the raw key into the UI for non-English admins.
- Plugins should ship translations for these locales (source locale is English): Nordic — da, sv, no, fi; Large EU — de, fr, es, it, nl, pl; Other common Sylius locales — pt, cs, hu, ro, uk. Translation files live in `translations/` and follow Symfony's `<domain>.<locale>.<format>` naming.

### Tests

- Use Prophecy for all test doubles (`ProphecyTrait` and `$this->prophesize()`). Don't mix with PHPUnit's native `createMock()` / `createStub()`.
- **Constraint validators**: extend `Symfony\Component\Validator\Test\ConstraintValidatorTestCase`. Implement `createValidator(): ConstraintValidatorInterface`, then assert with `$this->buildViolation(...)->atPath(...)->setParameter(...)->assertRaised()` and `$this->assertNoViolation()`.
- Keep kernel-free logic under `tests/Unit/` and anything that boots the app (and therefore needs MySQL) under `tests/Functional/`.

### UI verification

- When you build or change a feature with a UI surface (admin form, grid, page), verify it via the Playwright MCP — boot the test app (see "Booting the test app locally"), navigate to the affected page, and confirm the rendered output before reporting the task as complete. Don't rely on PHPUnit/PHPStan/ECS alone for UI work.

### UPGRADE.md and README.md

- When you make a breaking change a plugin user would need to act on during a major-version upgrade (default-value flips, renamed/removed public classes or services, template-path moves, changed model APIs, removed config keys), document it in `UPGRADE.md` under the relevant "Upgrading from X to Y" section. Skip changes that consumers don't have to react to: dev tooling, listener-priority tweaks, translation-key additions, and other internal refactors.
- When you ship a change that affects what the plugin does for end users — new feature surfaces, new configuration keys, behavior shifts, removed UI — update `README.md` to describe the current state.

## Agent skills

- When grilling (`/mattpocock-skills:grilling`, `/mattpocock-skills:grill-me`, `/mattpocock-skills:grill-with-docs`, and grilling inside other skills), ask via the `AskUserQuestion` tool instead of as plain chat text. The skills ask in chat on purpose and leave this to the project's CLAUDE.md ([mattpocock/skills: native question tool](https://github.com/mattpocock/skills/blob/main/.out-of-scope/native-question-tool.md)).

### Issue tracker

Issues and specs live in GitHub Issues on `Setono/SyliusTermsPlugin`, managed with the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default triage labels are used as-is (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root, created only when a term or decision gets settled. See `docs/agents/domain.md`.
