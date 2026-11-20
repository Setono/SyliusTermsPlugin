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
composer phpunit                             # all three suites: unit, functional and browser
composer phpunit:unit                        # unit suite only (no kernel, no database)
composer phpunit:functional                  # functional suite (boots tests/Application, needs MySQL)
composer phpunit:browser                     # browser suite (Chromium through playwright-symfony, needs MySQL)
PLAYWRIGHT_HEADLESS=false composer phpunit:browser  # watch the browser while the suite runs
vendor/bin/playwright-install chromium       # once per machine, before the first browser run
vendor/bin/infection --threads=max           # mutation testing against the unit suite; --filter=src/Some/File.php for one file
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

- The test app and the functional and browser tests require MySQL. Point `DATABASE_URL` at it in `tests/Application/.env.local`.
- The functional and browser suites run against the `test` environment's database, with the schema and the default fixtures loaded, as CI does. Set it up once, and again after mapping changes or changes to the test app's fixture suite:

    ```bash
    APP_ENV=test ./tests/Application/bin/console doctrine:database:create --if-not-exists
    APP_ENV=test ./tests/Application/bin/console doctrine:schema:create
    APP_ENV=test ./tests/Application/bin/console sylius:fixtures:load --no-interaction
    ```

- The browser suite also needs the built assets (`yarn build`) and the bundles' public files in `tests/Application/public/bundles`. Install them with `./tests/Application/bin/console assets:install tests/Application/public` and run it again after changing files in `public/`: it copies, and `--symlink` doesn't work, because playwright-symfony's asset bridge won't serve files that resolve outside the test app's `public/`.
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
- Code coverage counts the unit and functional suites, not the browser suite, and is 100% of `src/` lines. Keep it there: every change covers all the lines it adds or modifies (Codecov's patch target is 100%), and the project's coverage may never drop below its base commit (`target: auto`). Check locally with `vendor/bin/phpunit --testsuite unit,functional --coverage-text` (needs pcov or Xdebug)
- Mutation testing (Infection, the `mutation-tests` job) runs against the unit suite only, so code only the functional suite reaches gets no mutants. `minMsi` and `minCoveredMsi` in `infection.json5` are a floor that only goes up (90 for now): raise them when the score allows, never lower them, and don't add ignores to reach them. Kill an escaped mutant with a unit test, or remove the redundant code it points at. An equivalent mutant, which no test can tell apart from the code, stays escaped
- A failing browser test fails the build like any other job. A flaky one gets fixed or deleted, never retried or marked `continue-on-error`
- The `backwards-compatibility` job runs Roave's BC check against the pull request's base branch and fails on a break. A minor of a released line doesn't break BC: a break waits for the next major, with its UPGRADE.md entry

## Conventions

### Services

- Services are configured in PHP, never XML: `config/services.php` imports one file per concern from `config/services/`, loaded through `PhpFileLoader`.
- All services must be registered using their FQCN as the service id (e.g. `Setono\SyliusTermsPlugin\Validator\Constraints\FooValidator`), not a snake-cased alias like `setono_sylius_terms.validator.foo`, with an alias from their `*Interface` where they have one. This matches Symfony's autowiring conventions and lets consumers override or decorate by class name. Inject collaborators by their interface alias. The ids Sylius generates for resources (`setono_sylius_terms.factory.terms`, `.repository.terms`, `.manager.terms`) are the exception. Every id rename goes in UPGRADE.md's service-id table.
- When the plugin needs to configure another bundle (`sylius_grid`, `sylius_twig_hooks`, doctrine, etc.), do it from `SetonoSyliusTermsExtension::prepend()` via `$container->prependExtensionConfig(...)` with the config inlined as a PHP array. Don't ship YAML files that consumers have to import.
- For services that need a Doctrine `EntityManager`, don't inject `EntityManagerInterface` (or `setono_sylius_terms.manager.terms`) directly. Inject `Doctrine\Persistence\ManagerRegistry` plus the relevant `class-string` (e.g. `%setono_sylius_terms.model.terms.class%`) and `use Setono\Doctrine\ORMTrait;` so the manager is resolved lazily via `$this->getManager($class)`.

### Twig

- Twig extensions should split into an `Extension` (eagerly loaded, declares functions/filters) and a `Runtime` (lazily instantiated, holds dependencies and runs the logic). Wire functions via `[Runtime::class, 'method']` and tag the runtime service with `twig.runtime`.

### Translations

- When adding or updating translation keys, update every locale file in `translations/` (e.g. `messages.en.yaml`, `messages.da.yaml`, ...), not just English. Missing translations leak the raw key into the UI for non-English admins. `tests/Unit/TranslationsTest.php` fails when a locale file's keys, their order or a message's placeholders differ from English.
- Plugins should ship translations for these locales (source locale is English): Nordic — da, sv, no, fi; Large EU — de, fr, es, it, nl, pl; Other common Sylius locales — pt, cs, hu, ro, uk. Translation files live in `translations/` and follow Symfony's `<domain>.<locale>.<format>` naming.

### Tests

- Use Prophecy for all test doubles (`ProphecyTrait` and `$this->prophesize()`). Don't mix with PHPUnit's native `createMock()` / `createStub()`.
- **Constraint validators**: extend `Symfony\Component\Validator\Test\ConstraintValidatorTestCase`. Implement `createValidator(): ConstraintValidatorInterface`, then assert with `$this->buildViolation(...)->atPath(...)->setParameter(...)->assertRaised()` and `$this->assertNoViolation()`.
- Test-app code (such as fixtures only the test app uses) lives in `tests/Application/src/` under the `Setono\SyliusTermsPlugin\Tests\Application\` namespace, registered in `tests/Application/config/services.yaml`. PHPStan and ECS skip it.
- Three PHPUnit suites:
  - `unit` (`tests/Unit/`, mirrors `src/`): no kernel, no database.
  - `functional` (`tests/Functional/`): boots `tests/Application` against MySQL with `WebTestCase` / `KernelTestCase`, and UX's `InteractsWithLiveComponents` for Live re-renders. Call `restore_exception_handler()` in `tearDown()`.
  - `browser` (`tests/Browser/`): tests extend `Playwright\Symfony\Test\PlaywrightTestCase` and drive Chromium. Pages are handled by the test's own kernel, with no web server, so they see the data the test creates. Read `docs/agents/browser-tests.md` before writing or debugging one.
- `dama/doctrine-test-bundle` runs every test in a transaction that is rolled back afterwards. Find the baseline Terms (`terms_and_conditions`, `privacy_policy`, defined in `tests/Application/config/packages/sylius_fixtures.yaml`) by code, and create every other case inline with the plugin's factories. No Foundry.
- Elements a test needs get a hook through `{{ sylius_test_html_attribute('name', value) }}`, never a raw `data-test-*` attribute.
- Tests never touch the network. Time comes from Symfony Clock, and tests control it with `ClockSensitiveTrait` / `MockClock`.
- playwright-symfony breaks BC in minors, so its constraint pins the minor (`^0.12`). Read its CHANGELOG before moving it.

### UI verification

- When you build or change a feature with a UI surface (admin form, grid, page), cover it in the browser suite, and look at a screenshot of each page it changes before reporting the task as complete (see `docs/agents/browser-tests.md`). Twig hook wiring fails silently, so only the rendered page shows that it works.

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
