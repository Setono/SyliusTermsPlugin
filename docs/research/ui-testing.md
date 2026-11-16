# Browser-driven UI testing options for a Sylius 2 plugin

Research for [Browser-driven UI testing options for a Sylius 2 plugin](https://github.com/Setono/SyliusTermsPlugin/issues/99), on the map [Wayfinder: Sylius 2 upgrade (3.0)](https://github.com/Setono/SyliusTermsPlugin/issues/87). It records facts for the decision ticket [How does 3.0 test at the unit, functional and UI levels?](https://github.com/Setono/SyliusTermsPlugin/issues/100). It makes no decision.

> **Maintainer input given during this research (2026-11-16):** "I want to use playwright-symfony library for ui testing" and "Not behat, mink, panther". That input belongs to the decision ticket. The facts below cover every option the ticket asked about, so the decision can cite them.

**Sources** (all primary, read on 2026-11-16; nothing was installed or run, but CI job timings were read from GitHub Actions logs):

- Sylius `v2.3.2` (`3e99f4b`): `composer.json`, `behat.dist.php`, `.github/workflows/*`, `features/`, `src/Sylius/Behat/`, shop templates.
- `SyliusLabs/BuildTestAppAction` `v4` (= `v4.3.1`, `c3b108b`), the action Sylius and its plugins use to build the test app in CI.
- Sylius' official plugins at their current Sylius 2 branches: `Sylius/PluginSkeleton` `2.2`, `CmsPlugin` `2.0`, `InvoicingPlugin` `2.3`, `RefundPlugin` `2.2`, `PayPalPlugin` `2.2`, `MolliePlugin` `3.5`, `StripePlugin` `2.1`.
- Setono: `SyliusPluginSkeleton` `2.2.x` (`ecdeaf2`) and its `sylius-v1-to-v2-upgrade-playbook.md`; `setono/sylius-plugin` `v2` (`745c428`); Sylius 2 plugins `SyliusPartnerAdsPlugin` `3.x`, `SyliusAbandonedCartPlugin` `3.x`, `SyliusPickupPointPlugin` `2.x`, `SyliusCouponUrlApplicationPlugin` `3.x`, `SyliusRedirectPlugin` `3.x`; plus `SyliusCMSPlugin` `2.x` (`2588cb6`, a Sylius 1.14 plugin that already uses playwright-symfony).
- MonsieurBiz Sylius 2 branches: `SyliusRichEditorPlugin` `3.x`, `SyliusCmsPagePlugin` `2.x`, `SyliusMenuPlugin` `2.x`, `SyliusSettingsPlugin` `2.x`.
- Tools: `playwright-php/playwright-symfony` `v0.12.0` (README, `docs/`, `CHANGELOG.md`, `src/`), `playwright-php/playwright` `v1.6.0`, `zenstruck/browser` `v1.14.0`, `symfony/panther` `v2.4.0` with the Symfony docs page `testing/end_to_end.rst` (branch `7.4`, `ad12e7e`), Playwright `v1.65.1` docs, Infection docs (`infection/site` `e95e69f`). Package versions and release dates come from Packagist.

## Answer in brief

- **Sylius 2 core and every official Sylius plugin use Behat + Mink.** Non-JS scenarios run in-process through the BrowserKit driver. JS scenarios run against a real Chrome, through `dmore/chrome-mink-driver` (`@mink:chromedriver`, the bulk) and Panther (`@javascript`). CI builds the app with `SyliusLabs/BuildTestAppAction`, starts headless Chrome and `symfony server` on port 8080, and builds the Encore assets only for JS jobs.
- **Setono's Sylius 2 plugins and MonsieurBiz's Sylius 2 plugins have no browser tests.** The Setono ones have PHPUnit `unit` + `functional` only; the MonsieurBiz ones have PHPUnit and PHPSpec only.
- **One Setono plugin already uses playwright-symfony.** `SyliusCMSPlugin` `2.x`, on Sylius 1.14, has a third PHPUnit suite `ui` (`tests/UI`, 23 tests) on `PlaywrightTestCase`, with DAMA transactions shared between test and app. Its CI job takes 1.5–3 minutes. No Sylius 2 plugin using it was found.
- **The skeleton's CLAUDE.md already names playwright-symfony** as the tool for browser tests ("Add when needed": `tests/Browser/`, a `browser` suite). It also says the `setono/sylius-plugin` actions don't install Playwright browsers. Neither the playbook nor `phpunit.xml.dist` has it yet.
- **Live Component forms submit without JavaScript.** Sylius runs its registration scenarios (a Live Component form) in the non-JS session: the form is a plain `POST` to `sylius_shop_register`. A real browser is needed only for live behaviour: re-render on change, `#[LiveAction]` buttons such as slug generation, and anything that depends on what the component re-renders.
- **Test-app needs differ by option.** Every option needs MySQL and fixtures. Every real-browser option needs the Encore build and a browser binary. Behat-JS, Panther and Playwright JS also need a web server. playwright-symfony and zenstruck's browsers do not: requests go to the test's own kernel.
- **Fit with the PHPUnit split.** playwright-symfony, Panther and zenstruck/browser are PHPUnit test cases, so each can be a third suite. Behat and Playwright JS are separate runners. In the skeleton as it is, a third suite would also run in the `code-coverage` job (all suites by default) and under Infection, and neither installs a browser.
- **Flakiness signals.** Sylius core reruns every failing Behat-JS job up to twice, adding a 5-second step delay on reruns, and keeps 5 scenarios tagged `@failing` out of the main CI. RefundPlugin reruns up to four times. The CMS plugin's playwright-symfony `ui` job failed 6 of 111 times, always in all three PHP cells of the same run on a feature branch.

## Comparison table

| Option | JS / Live Components | Used by (Sylius 2 core and maintained plugins) | Test-app needs | CI cost / setup (GitHub Actions) | Fit with the PHPUnit split and `setono/sylius-plugin` | Maintenance / flakiness |
| --- | --- | --- | --- | --- | --- | --- |
| **Behat + Mink, BrowserKit driver** (`symfony` session) | No JS. Live Component forms still submit as a normal `POST`; live re-renders are not exercised. | Sylius core (`default_session: symfony`), Sylius/PluginSkeleton and every Sylius plugin surveyed. No Setono or MonsieurBiz plugin. | MySQL and fixtures. Sylius stubs empty Encore `manifest.json` files for non-JS jobs. No browser, no server for this session. | Core non-JS UI job: 9–12 min per cell for ~1,670 non-JS `@ui` scenarios (2,106 `@ui` scenario tag lines, 437 of them JS-tagged). Setup via `BuildTestAppAction` (`e2e: yes`). | Separate runner (`vendor/bin/behat`) with Gherkin, contexts and page objects; not a PHPUnit suite. `setono/sylius-plugin` has no Behat action. Plugins can import Sylius' own suites and pages from `vendor/sylius/sylius/src/Sylius/Behat`. | Mature, widely used in the Sylius ecosystem. Core constraints already allow `behat/behat 4.0.0-alpha1` and `mink-extension 3.0.0-alpha1`. |
| **Behat + Mink + ChromeDriver** (`dmore/chrome-mink-driver`, `sylius-labs/behat-chrome-extension`) | Yes, real Chrome over the DevTools protocol. Live Components need explicit waits (`DriverHelper::waitForLiveComponentUpdate`). | Core's main JS suite (397 `@mink:chromedriver` tags). Sylius/PluginSkeleton, Cms, Invoicing, Refund, PayPal and Mollie plugins. | Chrome started with `--remote-debugging-port=9222`, `symfony server` on 8080, `yarn install && yarn build`, MySQL and fixtures. | Core splits it into 5 groups × 3 Symfony versions: 2–16 min per job (one run). Invoicing plugin: 2.5–3 min per cell; Cms plugin: 5–8 min per cell (whole job: build, PHPUnit and Behat). | Behat runner, as above. | Core reruns failures up to twice, adding `BEHAT_STEP_DELAY=5000`; 5 scenarios are tagged `@failing` and kept out of the main CI. Refund plugin reruns up to 4 times. |
| **Behat + Mink + Panther** (`sylius-labs/behat-panther-extension` → `robertfausk/mink-panther-driver` → `symfony/panther`) | Yes, real Chrome over WebDriver. | Core (`javascript_session: panther`, 57 `@javascript` tags) and the Stripe plugin. The Cms plugin requires the extension but configures `javascript_session: chrome`. | ChromeDriver (e.g. `vendor/bin/bdi`), Chrome, a web server, built assets, MySQL. | Core: 3 Panther jobs of ~6 min. Stripe plugin installs the driver with `bdi` in CI. | Behat runner. | Same rerun pattern as ChromeDriver in core. `robertfausk/mink-panther-driver` last released 2025-04-03 (v1.1.2). |
| **Behat + Mink + Selenium2** | Yes (WebDriver). | Required in core (`behat/mink-selenium2-driver`) and treated as JS by `DriverHelper`, but no core session or CI job uses it. No plugin surveyed uses it. | A Selenium server, plus everything the JS rows need. | No Sylius CI reference. | Behat runner. | — |
| **Symfony Panther under PHPUnit** (`PantherTestCase`) | Yes, real Chrome or Firefox over WebDriver, with `waitFor*` helpers. | None of the projects surveyed (core uses Panther only through Behat). | ChromeDriver/geckodriver via `bdi`, a browser, built assets, MySQL. Panther starts PHP's built-in server **in its own process** (default port 9080). | Per the Symfony docs: a GitHub Actions job installs Composer deps and runs PHPUnit, with no extra setup steps. Driver install and assets come on top. | A PHPUnit test case, so it can be a third suite. Needs `ServerExtension` in `phpunit.xml.dist`. App code runs in another process, so test data must be committed and can't live in a DAMA transaction (inference from the separate process). | `symfony/panther` v2.4.0 (2026-01-08). zenstruck/browser deprecated its Panther wrapper in favour of Playwright. |
| **zenstruck/browser** | `KernelBrowser`: no JS, in-process. `PlaywrightBrowser`: real browser, experimental, built on playwright-symfony. `PantherBrowser`: deprecated since 1.11. | None of the projects surveyed. | `KernelBrowser`: as BrowserKit. `PlaywrightBrowser`: as playwright-symfony. | As the underlying browser. | PHPUnit trait `HasBrowser` plus an optional PHPUnit extension. **Requires `behat/mink` as a runtime dependency.** | v1.14.0 (2026-11-06), active. `PlaywrightBrowser` is marked "experimental and may be subject to BC Breaks". |
| **playwright-symfony** (`PlaywrightTestCase`) | Yes, real Chromium, Firefox or WebKit through Playwright, with auto-waiting. Browser requests are routed into the test's kernel. Its own suite tests a Live Component; a Live Component rendering bug was fixed in July 2026 (playwright-symfony issue #5). | Setono CMSPlugin `2.x` (Sylius 1.14), named in the skeleton CLAUDE.md. No Sylius 2 plugin found. | Node 20+, `vendor/bin/playwright-install` (npm installs `playwright ^1.62` and downloads browsers), built assets (served from `public/` by its asset bridge; the default prefixes are `/build`, `/assets` and `/_framework/ux`, and the CMS plugin adds `/bundles` and `/media`), MySQL and fixtures. **No web server.** | CMS plugin: 1.5–3 min per UI job (browser install 22 s, 23 tests in 77 s). `setono/sylius-plugin` actions install no browser. | PHPUnit (`PlaywrightTestCase extends WebTestCase`), so it can be a third suite (the CMS plugin's `ui`, the skeleton's `browser`). Shares the DAMA transaction with the app. | Pre-1.0: "breaking changes are released in minor versions" (0.10 and 0.12 both had BC breaks). 42 GitHub stars. CMS plugin: 6 of 111 UI jobs failed, always all three PHP cells of the same run. |
| **Playwright (JS test runner)** | Yes, real browsers with auto-waiting. | None of the projects surveyed. The skeleton CLAUDE.md steers away from "a JavaScript Playwright suite". | Node, `npx playwright install --with-deps`, a running app server (Playwright's `webServer` option, e.g. `symfony serve`), built assets, MySQL and fixtures. | Official GitHub Actions recipe: setup-node, `npm ci`, `npx playwright install --with-deps`, `npx playwright test`. | Outside PHPUnit: TS/JS tests, a separate job, no access to the PHP container or a test transaction. Data setup goes through fixtures, the console or HTTP. | Microsoft-maintained, v1.65.1 (2026-11-13). Retries and traces are built in. |

---

## 1. What others use

### Sylius 2 core (v2.3.2)

- **Dependencies** ([`composer.json` L188–226](https://github.com/Sylius/Sylius/blob/v2.3.2/composer.json#L188-L226)): `behat/behat` (L189), `behat/mink-selenium2-driver` (L190), `dbrekelmans/bdi` (L191), `dmore/chrome-mink-driver` (L192), `behat/mink` (L195), `friends-of-behat/mink-browserkit-driver`, `mink-extension`, `page-object-extension`, `symfony-extension` (L196–201), `sylius-labs/behat-chrome-extension` and `sylius-labs/behat-panther-extension` (L216–217), `symfony/browser-kit` (L220). Panther itself arrives through `sylius-labs/behat-panther-extension` v1.3.0, which requires `robertfausk/mink-panther-driver`, and that requires `symfony/panther` (Packagist).
- **Sessions** ([`behat.dist.php` L38–103](https://github.com/Sylius/Sylius/blob/v2.3.2/behat.dist.php#L38-L103)): `default_session: symfony` (BrowserKit, in-process), `javascript_session: panther`, plus `chromedriver` sessions on `http://127.0.0.1:9222`. `base_url` is `http://127.0.0.1:8080/`.
- **Scenario counts** (`features/`, 855 files): 397 `@mink:chromedriver` tags, 57 `@javascript` tags, 5 `@failing` tags.
- **CI** ([`ci__minimal.yaml` L3–13, L41–44](https://github.com/Sylius/Sylius/blob/v2.3.2/.github/workflows/ci__minimal.yaml#L3-L44) runs on every PR and push):
  - Non-JS UI ([`ci_e2e-mysql.yaml` L197–200](https://github.com/Sylius/Sylius/blob/v2.3.2/.github/workflows/ci_e2e-mysql.yaml#L197-L200)): `--tags="~@javascript&&~@mink:chromedriver…"`.
  - JS ([`ci_js.yaml` L52–180](https://github.com/Sylius/Sylius/blob/v2.3.2/.github/workflows/ci_js.yaml#L52-L180)): ChromeDriver jobs from the `js` matrix (5 groups × Symfony 6.4/7.4/8.1, [`matrix.json` L80](https://github.com/Sylius/Sylius/blob/v2.3.2/.github/workflows/matrix.json#L80)), and Panther jobs from `js-panther` (L189). On failure Behat reruns twice, with `BEHAT_STEP_DELAY=5000` on the reruns (L105–109).
  - `@failing` scenarios are excluded everywhere (`~@failing` 90 times in `matrix.json`). They run only in the nightly unstable workflow, with `continue-on-error: true` ([`ci_e2e-unstable.yaml` L162–167](https://github.com/Sylius/Sylius/blob/v2.3.2/.github/workflows/ci_e2e-unstable.yaml#L162-L167)).
  - One `2.3` run on 2026-11-16 ([run 43834962019](https://github.com/Sylius/Sylius/actions/runs/43834962019)): ChromeDriver jobs took 2m15s–16m26s, Panther jobs ~6m, and non-JS UI jobs 8m54s–12m24s, all on the first attempt.
- **Test-app build in CI** ([`BuildTestAppAction` v4.3.1 `action.yaml`](https://github.com/SyliusLabs/BuildTestAppAction/blob/v4.3.1/action.yaml#L388-L448)):
  - JS jobs (`e2e_js: yes`): replace the runner's Chrome via `browser-actions/setup-chrome@v2` with ChromeDriver (L388–404), start headless Chrome with `--remote-debugging-port=9222` (L406–412), then `yarn install` and `yarn build` (L432–448).
  - Every e2e job runs `symfony server:start --port=8080 --daemon --no-tls` (L414–416).
  - Non-JS jobs write empty `public/build/{admin,shop}/manifest.json` instead of building assets (L419–426).
- **How Live Components are driven** ([`src/Sylius/Behat/Service/DriverHelper.php` L24–82](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Behat/Service/DriverHelper.php#L24-L82)): JS drivers are Selenium2, ChromeDriver and Panther. `waitForLiveComponentUpdate()` waits for `[data-controller~="live"][busy]` / `[data-live-is-loading]` to appear and then clear, and returns at once for non-JS drivers. Registration fields call it after every `setValue()` ([`RegisterElement.php` L43–47, L120–123](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Behat/Element/Shop/Account/RegisterElement.php#L43-L47)). The admin `CreatePage::create()` blurs and waits only on JS drivers, then presses Create ([`CreatePage.php` L36–43](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Behat/Page/Admin/Crud/CreatePage.php#L36-L43)).

### Sylius' official plugins (Sylius 2 branches)

All use Behat + Mink against `sylius/test-application`, and all build the app with `BuildTestAppAction` and `e2e_js: "yes"`.

| Plugin @ branch | JS driver(s) | CI Behat command |
| --- | --- | --- |
| [`PluginSkeleton@2.2`](https://github.com/Sylius/PluginSkeleton/blob/ad98a823a413df3ea5370ee542f592c03bc1441b/behat.yml.dist#L20-L32) | ChromeDriver (`javascript_session: chrome`, `composer.json` L16–19) | `behat … \|\| behat … --rerun` ([`build.yaml` L111–112](https://github.com/Sylius/PluginSkeleton/blob/ad98a823a413df3ea5370ee542f592c03bc1441b/.github/workflows/build.yaml#L111-L112)) |
| [`CmsPlugin@2.0`](https://github.com/Sylius/CmsPlugin/blob/dd2c4b10e2472c3b37694d568114b45fd9edfe20/composer.json#L20-L45) | ChromeDriver (L22; `javascript_session: chrome` in [`behat.yaml.dist` L18](https://github.com/Sylius/CmsPlugin/blob/dd2c4b10e2472c3b37694d568114b45fd9edfe20/behat.yaml.dist#L18)); also requires `robertfausk/behat-panther-extension` (L44) | shop and admin, each with one `--rerun` ([`build.yaml` L104–122](https://github.com/Sylius/CmsPlugin/blob/dd2c4b10e2472c3b37694d568114b45fd9edfe20/.github/workflows/build.yaml#L104-L122)) |
| [`InvoicingPlugin@2.3`](https://github.com/Sylius/InvoicingPlugin/blob/2049602a64426f90ea43ebbb0e90cf5d8ebd0d5c/composer.json#L37-L57) | ChromeDriver (L39, L55) | one `--rerun` ([`build.yaml` L179–184](https://github.com/Sylius/InvoicingPlugin/blob/2049602a64426f90ea43ebbb0e90cf5d8ebd0d5c/.github/workflows/build.yaml#L179-L184)) |
| [`RefundPlugin@2.2`](https://github.com/Sylius/RefundPlugin/blob/0ca6789ac408f43e05452ee8595c1fe219c3c3d2/composer.json#L35-L51) | ChromeDriver (L37, L49) | up to 2 or 4 `--rerun`s ([`build.yaml` L219–224](https://github.com/Sylius/RefundPlugin/blob/0ca6789ac408f43e05452ee8595c1fe219c3c3d2/.github/workflows/build.yaml#L219-L224)) |
| [`PayPalPlugin@2.2`](https://github.com/Sylius/PayPalPlugin/blob/732fbf5b5688f656c4eb391b1539af64879e6e30/composer.json#L24-L43) | ChromeDriver (L26, L40) | one `--rerun` ([`build.yaml` L135–136](https://github.com/Sylius/PayPalPlugin/blob/732fbf5b5688f656c4eb391b1539af64879e6e30/.github/workflows/build.yaml#L135-L136)) |
| [`MolliePlugin@3.5`](https://github.com/Sylius/MolliePlugin/blob/12c1a2db79c4fbd18485ff33eedf090c7f1f0295/composer.json#L15-L36) | ChromeDriver (L17, L34) | one `--rerun`, against a Mockoon API mock ([`build.yml` L137–144](https://github.com/Sylius/MolliePlugin/blob/12c1a2db79c4fbd18485ff33eedf090c7f1f0295/.github/workflows/build.yml#L137-L144)) |
| [`StripePlugin@2.1`](https://github.com/Sylius/StripePlugin/blob/0e04a5c3a2caf7e09e00ac0b6cbc9e8d9b169fcc/composer.json#L19-L44) | Panther (`robertfausk/behat-panther-extension ^2.0`, L39; `bdi`, L21) | non-UI, non-JS, then Panther, mirroring core ([`build.yaml` L186–263](https://github.com/Sylius/StripePlugin/blob/0e04a5c3a2caf7e09e00ac0b6cbc9e8d9b169fcc/.github/workflows/build.yaml#L186-L263)) |

Most recent successful runs: Invoicing plugin cells took 2m27s–2m53s (test-app build 104 s, Behat 34 s); Cms plugin cells took 5m00s–7m50s (test-app build 110 s, Behat 225 s).

### Setono plugins

- **Sylius 2 plugins have no browser tests.** `SyliusPartnerAdsPlugin@3.x`, `SyliusAbandonedCartPlugin@3.x`, `SyliusPickupPointPlugin@2.x`, `SyliusCouponUrlApplicationPlugin@3.x` and `SyliusRedirectPlugin@3.x` declare only the `unit` and `functional` suites and have no `tests/UI`, `tests/Browser`, `features/` or Behat config.
- **`SyliusCMSPlugin@2.x` (Sylius 1.14) uses playwright-symfony:**
  - `require-dev` has `playwright-php/playwright-symfony: ^0.12` and `dama/doctrine-test-bundle: ^7.3` ([`composer.json` L51, L55](https://github.com/Setono/SyliusCMSPlugin/blob/2588cb62de452ff323a521668226a296622f48d0/composer.json#L51-L55)).
  - A third suite, `ui` → `tests/UI`, sits next to `unit` and `functional`, with the DAMA extension registered ([`phpunit.xml.dist` L10–24](https://github.com/Setono/SyliusCMSPlugin/blob/2588cb62de452ff323a521668226a296622f48d0/phpunit.xml.dist#L10-L24)).
  - The base class says: "The kernel runs in the test's PHP process, inside the test's database transaction, so data a test creates is visible to the pages it visits" ([`tests/UI/UITestCase.php` L10–15](https://github.com/Setono/SyliusCMSPlugin/blob/2588cb62de452ff323a521668226a296622f48d0/tests/UI/UITestCase.php#L10-L15)). It signs in with `loginUser(..., 'admin')`.
  - The asset bridge prefixes are `/build`, `/bundles` and `/media` ([`config/packages/test/playwright.yaml`](https://github.com/Setono/SyliusCMSPlugin/blob/2588cb62de452ff323a521668226a296622f48d0/tests/Application/config/packages/test/playwright.yaml#L1-L8)).
  - CI ([`build.yaml` L202–273](https://github.com/Setono/SyliusCMSPlugin/blob/2588cb62de452ff323a521668226a296622f48d0/.github/workflows/build.yaml#L202-L273)): one job template for `functional` and `ui` (PHP 8.2/8.3/8.4). It starts MySQL, runs `yarn install && yarn build`, and runs `vendor/bin/playwright-install --with-deps chromium` (UI only), then the schema, the `test` fixtures and `phpunit --testsuite ui`.
  - Observed cost: UI jobs 1m33s–2m59s versus functional 1m08s–2m15s. In [run 43828963414](https://github.com/Setono/SyliusCMSPlugin/actions/runs/43828963414), browser install took 22 s and the suite 77 s for 23 tests.
  - Last 37 runs (2026-08-19 to 2026-11-16): 6 of 111 UI jobs failed. All six came from two runs on in-progress branches (`walking-skeleton`, `skeleton`), where all three PHP cells failed together. No run had a single failing cell.

### MonsieurBiz (Sylius 2 branches)

`SyliusRichEditorPlugin@3.x`, `SyliusCmsPagePlugin@2.x`, `SyliusMenuPlugin@2.x` and `SyliusSettingsPlugin@2.x` require only PHPUnit, PHPSpec, PHPStan, PHPMD and PHP-CS-Fixer for testing. CI runs `make test.phpunit`, `test.phpspec`, the linters and a schema check, with no browser tests ([RichEditor `Makefile` L113–145](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/da5c6dbec15c2bd2d64bbf9292eb126dbc613087/Makefile#L113-L145), [`tests.yaml` L60–80](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/da5c6dbec15c2bd2d64bbf9292eb126dbc613087/.github/workflows/tests.yaml#L60-L80)).

## 2. The candidates

### Behat + Mink

- **Drivers.** The BrowserKit driver (`friends-of-behat/mink-browserkit-driver` via `friends-of-behat/symfony-extension`) runs in the test kernel, so it needs no server or browser and has no JS. ChromeDriver (`dmore/chrome-mink-driver` 2.11.2, 2026-10-30) talks to a Chrome started with `--remote-debugging-port`. Panther runs through `sylius-labs/behat-panther-extension` v1.3.0 → `robertfausk/mink-panther-driver` v1.1.2 → `symfony/panther`. Selenium2 needs a Selenium server.
- **What a plugin gets for free.** Sylius' Behat contexts, pages and suites ship in `sylius/sylius` (`src/Sylius/Behat`), and Sylius/PluginSkeleton imports `vendor/sylius/sylius/src/Sylius/Behat/Resources/config/suites.yml` ([`behat.yml.dist` L1–3](https://github.com/Sylius/PluginSkeleton/blob/ad98a823a413df3ea5370ee542f592c03bc1441b/behat.yml.dist#L1-L3)).
- **The cost.** A second test runner and a second config. Tests are Gherkin features plus PHP contexts and page objects, outside PHPUnit, so the skeleton's `unit`/`functional` split, `composer phpunit` and the `setono/sylius-plugin` actions don't cover them.

### Symfony Panther under PHPUnit

- **Install** ([`end_to_end.rst` L21–63](https://github.com/symfony/symfony-docs/blob/ad12e7e1fd86bfa23de1c6576393be0fc7f70f85/testing/end_to_end.rst#L21-L63)): `composer require --dev symfony/panther`, then install drivers with `dbrekelmans/bdi` (`vendor/bin/bdi detect drivers`) or the OS package manager.
- **PHPUnit extension** (L67–95): `Symfony\Component\Panther\ServerExtension` is "strongly recommended". Without it the server starts on demand and stops in `tearDownAfterClass()`; with it, the server stops only after the last test.
- **Web server** (L289–313): the default is `127.0.0.1:9080`. "The built-in web server runs in its own process, which inherits the environment variables of the test process", and it "is started only once and then reused by all tests".
- **GitHub Actions** (L751–780): the minimal workflow is checkout, `ramsey/composer-install` and `bin/phpunit`.
- **Version**: `symfony/panther` v2.4.0 (2026-01-08) requires `php-webdriver/webdriver`, `symfony/browser-kit` and `symfony/process` (Packagist).

### zenstruck/browser (v1.14.0)

- **Two browsers documented** ([README L87–91](https://github.com/zenstruck/browser/blob/v1.14.0/README.md#L87-L91)): `KernelBrowser` ("makes requests using your Symfony Kernel *(fast)*") and `PlaywrightBrowser` ("drives a real browser with `playwright-php/playwright-symfony` but passes its requests to your Symfony Kernel, so no webserver is required *(slow)*").
- **`PlaywrightBrowser` is experimental** ([README L516–531](https://github.com/zenstruck/browser/blob/v1.14.0/README.md#L516-L531)): "experimental and may be subject to BC Breaks". It needs playwright-symfony 0.12+, Node.js 20+ and the Playwright browsers.
- **Auto-waiting** (L621–656): Playwright actions and DOM/URL assertions auto-wait, up to `BROWSER_DEFAULT_TIMEOUT` (default 2000 ms).
- **Panther is deprecated**: `PantherBrowser` is "@deprecated since 1.11, use PlaywrightBrowser instead" ([`src/Browser/PantherBrowser.php` L24](https://github.com/zenstruck/browser/blob/v1.14.0/src/Browser/PantherBrowser.php#L24)), and `pantherBrowser()` triggers a deprecation ([`HasBrowser.php` L65–77](https://github.com/zenstruck/browser/blob/v1.14.0/src/Browser/Test/HasBrowser.php#L65-L77)).
- **Dependency**: `require` includes `behat/mink: ^1.8` ([`composer.json` L14–23](https://github.com/zenstruck/browser/blob/v1.14.0/composer.json#L14-L23)).

### playwright-symfony (v0.12.0)

- **What it is** ([README L11–16](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/README.md#L11-L16)): "Run real-browser Symfony tests while routing application requests through the kernel in the same PHP process." It is "in active development. Its public API may change before 1.0."
- **Requirements** (L18–39): PHP 8.2+, Symfony 6.4/7.x/8.x and Node.js 20+. Install with `composer require --dev playwright-php/playwright-symfony` and `vendor/bin/playwright-install --browsers`, then register `PlaywrightSymfonyBundle` for `test` only.
  - `playwright-install` npm-installs the bundled Node server, whose `package.json` depends on `playwright ^1.62.0`, then runs `npx playwright install` ([`bin/package.json`](https://github.com/playwright-php/playwright/blob/v1.6.0/bin/package.json), [`bin/playwright-install`](https://github.com/playwright-php/playwright/blob/v1.6.0/bin/playwright-install)).
- **How it works** (README L83–103; [`docs/architecture.md` L34–49](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/docs/architecture.md#L34-L49)):
  - The browser's requests to intercepted hosts are converted into Symfony requests, handled by `HttpKernel` and then `terminate()`d ([`PlaywrightKernelClient.php` L888–893](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/src/Client/PlaywrightKernelClient.php#L888-L893)).
  - Static files under the asset prefixes are served straight from disk.
  - The kernel is not rebooted between requests. Instead, `Kernel::handle()` re-boots the already-booted kernel, which resets the `kernel.reset` services (comment at L967–969).
- **No server, shared DB** ([`docs/performance.md` L23–30](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/docs/performance.md#L23-L30)): "No local web server … is required" and "Database transactions can be shared between your test and the application code".
- **Defaults** ([`Configuration.php`](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/src/DependencyInjection/Configuration.php#L37-L124)):
  - `intercepted_hosts` defaults to `localhost`, `127.0.0.1` and `testapp.local` (L39); `base_url` falls back to `http://localhost` (L54–56).
  - Asset `public_roots` defaults to `%kernel.project_dir%/public` (L117) and `prefixes` to `/assets`, `/build` and `/_framework/ux` (L122).
  - Sylius' fixture channel hostname defaults to `localhost` ([`CoreBundle/Resources/config/app/fixtures.yml` L14](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/CoreBundle/Resources/config/app/fixtures.yml#L14)).
- **Test case**: `PlaywrightTestCase` extends `WebTestCase` (since 0.10, [CHANGELOG L51](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/CHANGELOG.md#L51)). It offers `loginUser()` with `KernelBrowser` semantics (L44) and the `WebTestCase` assertions (L45). The browser process is shared per test class, with a fresh context per test ([`docs/performance.md` L5–21](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/docs/performance.md#L5-L21)).
- **Suite placement**: "Keep them in a dedicated PHPUnit suite or group" (README L133). The getting-started guide suggests a `<testsuite>` or `#[Group('e2e')]` ([`docs/getting-started.md` L84–86](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/docs/getting-started.md#L84-L86)).
- **Live Components**:
  - Issue [#5](https://github.com/playwright-php/playwright-symfony/issues/5) (closed 2026-07-19): Live Component responses (`application/vnd.live-component+html`) were base64-encoded, so "the DOM never updates". It was fixed by treating `+html` as text (CHANGELOG L74).
  - The package's own suite clicks a Live Component action and follows its redirect ([`tests/Functional/UxRedirectTest.php` L46–54](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/tests/Functional/UxRedirectTest.php#L46-L54)), using AssetMapper, not Encore.
- **CI** ([`docs/ci.md` L30–33](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/docs/ci.md#L30-L33)): `playwright-php/setup-playwright@v1` installs the Playwright JS package and browsers. The alternative is `vendor/bin/playwright-install --with-deps`.
- **Stability**: "Before 1.0, breaking changes are released in minor versions" ([CHANGELOG L6](https://github.com/playwright-php/playwright-symfony/blob/v0.12.0/CHANGELOG.md#L6)). 0.12.0 had a BC break in routing (L15); 0.10.0 had seven BC breaks (L49–56). It has 42 stars, and the repo was last pushed 2026-11-15.

### Playwright (JS test runner, v1.65.1)

- **Web server**: the `webServer` config launches the app before the tests, with `reuseExistingServer: !process.env.CI` recommended ([`docs/src/test-webserver-js.md` L8–40](https://github.com/microsoft/playwright/blob/v1.65.1/docs/src/test-webserver-js.md#L8-L40)).
- **CI**: install browsers with `npx playwright install --with-deps`, and use `workers: process.env.CI ? 1 : undefined` in the sample config ([`docs/src/ci.md` L20, L58, L84–109](https://github.com/microsoft/playwright/blob/v1.65.1/docs/src/ci.md#L84-L109)).
- **Auto-waiting**: actionability checks run before every action ([`docs/src/actionability.md` L8–17](https://github.com/microsoft/playwright/blob/v1.65.1/docs/src/actionability.md#L8-L17)). playwright-symfony gets the same behaviour, since it drives the same Playwright server.
- **Separation from PHP**: tests are TS/JS run by `npx playwright test`, against a separately started Symfony server. They can't reach the PHP container or a test transaction.

## 3. What the plugin's UI tests need from a browser

- **Shop registration** (`sylius_shop:account:register:form`, a Live Component):
  - The form is a plain `POST` to `sylius_shop_register` inside the component root ([`ShopBundle/templates/account/register/content/form.html.twig` L1–9](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/ShopBundle/templates/account/register/content/form.html.twig#L1-L9)).
  - Sylius runs its registration scenarios with `@ui` and no JS tag, i.e. in the BrowserKit session ([`features/shop/account/registering.feature` L10](https://github.com/Sylius/Sylius/blob/v2.3.2/features/shop/account/registering.feature#L10)).
  - A real browser is needed only for live behaviour. The shop research found that "after a live re-render, hookables see only what the component template passes, not page-level context" (`docs/research/sylius2-shop-integration.md` on `research/sylius2-shop-integration`). A terms checkbox that disappears or loses its state on re-render shows up only in a real browser.
- **Checkout complete and contact**: neither is a Live Component (same shop research), so a non-JS submit drives them fully.
- **Admin CRUD**:
  - A plain create or update is a normal submit. Sylius' `CreatePage` presses Create directly on non-JS drivers.
  - The Live Component parts need a browser: a `#[LiveAction]` slug button (`data-action="live#action"`), translation-tab or collection re-renders. The admin research found the slug pattern requires the form to be a Live Component (`docs/research/sylius2-admin-integration.md` on `research/sylius2-admin-integration`, §4).
- **Test hooks**: the skeleton CLAUDE.md asks for `{{ sylius_test_html_attribute('name', value) }}`. Sylius' Twig function renders `data-test-<name>="<value>"` only when the environment name starts with `test` or debug is on ([`Sylius/TwigExtra` v0.14.3 `TestHtmlAttributeExtension.php` L30–38](https://github.com/Sylius/TwigExtra/blob/v0.14.3/src/Twig/Extension/TestHtmlAttributeExtension.php#L30-L38)). playwright-symfony runs the kernel in `APP_ENV=test`, so the attributes are present.

## 4. Test-app needs (beyond the skeleton's functional setup)

The skeleton's `functional-tests` action already starts MySQL, installs Node 20, runs `yarn install` + `yarn build` (Encore), creates and validates the schema, loads fixtures and can run one named suite ([`setono/sylius-plugin` `functional-tests/action.yml` L37–107](https://github.com/Setono/sylius-plugin/blob/745c4288b7ec577b67a93734bed0678f55ab7e4d/functional-tests/action.yml#L37-L107)). On top of that:

| Option | Browser binary | Web server | Other |
| --- | --- | --- | --- |
| Behat BrowserKit | — | — | Behat config, `friends-of-behat/symfony-extension` bootstrap |
| Behat ChromeDriver | Chrome (headless, `--remote-debugging-port=9222`) | `symfony server` (port 8080 in Sylius) | as above |
| Behat Panther / Panther under PHPUnit | Chrome + ChromeDriver (`bdi`) | Panther's PHP built-in server (separate process) | data must be committed (no shared transaction) |
| zenstruck `KernelBrowser` | — | — | `behat/mink` comes in as a dependency |
| playwright-symfony / zenstruck `PlaywrightBrowser` | Playwright Chromium via `vendor/bin/playwright-install` (npm) | — (in-process kernel) | Node 20+ at test runtime, `PlaywrightSymfonyBundle` in `bundles.php` (test), asset prefixes for `/bundles`, `/media` if pages use them |
| Playwright JS | `npx playwright install --with-deps` | `symfony serve` via `webServer` | `package.json` with `@playwright/test` |

## 5. Fit with the skeleton's PHPUnit split and `setono/sylius-plugin`

- **The skeleton today**:
  - [`phpunit.xml.dist` L5–12](https://github.com/Setono/SyliusPluginSkeleton/blob/ecdeaf21fd5bf2d68017c128f0c42d493a20d7c3/phpunit.xml.dist#L5-L12) declares `unit` and `functional`.
  - [`CLAUDE.md` L75–85](https://github.com/Setono/SyliusPluginSkeleton/blob/ecdeaf21fd5bf2d68017c128f0c42d493a20d7c3/CLAUDE.md#L75-L85) adds, under "Add when needed":
    - **Browser tests**: playwright-symfony "rather than Panther or a JavaScript Playwright suite". Tests extend `PlaywrightTestCase` in `tests/Browser/` with a `browser` suite, and use `sylius_test_html_attribute` hooks. "Make sure CI runs the suite. The `setono/sylius-plugin` actions don't install Playwright browsers yet."
    - **Database writes in tests**: `dama/doctrine-test-bundle`.
  - "UI verification" (L186–189) says to use the Playwright MCP while developing, and that "Durable coverage belongs in browser tests".
- **Which CI job would run a third suite** ([skeleton `build.yaml` L66–95](https://github.com/Setono/SyliusPluginSkeleton/blob/ecdeaf21fd5bf2d68017c128f0c42d493a20d7c3/.github/workflows/build.yaml#L66-L95)):
  - `unit-tests` and `functional-tests` pass an explicit `testsuite`, so neither picks up a new suite.
  - `code-coverage` passes none, and the action "runs all suites" when it's empty ([`code-coverage/action.yml` L32–35, L95](https://github.com/Setono/sylius-plugin/blob/745c4288b7ec577b67a93734bed0678f55ab7e4d/code-coverage/action.yml#L32-L95)). It installs no Playwright browser.
  - `mutation-tests` runs `infection` with no MySQL, no assets and no browsers ([`mutation-tests/action.yml` L22–41](https://github.com/Setono/sylius-plugin/blob/745c4288b7ec577b67a93734bed0678f55ab7e4d/mutation-tests/action.yml#L22-L41)), and the skeleton's [`infection.json5`](https://github.com/Setono/SyliusPluginSkeleton/blob/ecdeaf21fd5bf2d68017c128f0c42d493a20d7c3/infection.json5) passes no test-framework arguments. Infection "will quit if any of your tests are failing" ([Infection `usage.md` L163](https://github.com/infection/site/blob/e95e69f9a639a6da7981635cac8e26d27b414661/src/guide/usage.md#L163)).
  - Setono's Sylius 2 plugins already scope these jobs: `SyliusAbandonedCartPlugin@3.x` sets `"testFrameworkOptions": "--testsuite=unit"` ([`infection.json5` L10](https://github.com/Setono/SyliusAbandonedCartPlugin/blob/11c85e73ff7866ca51095c2f5812a36164f144bf/infection.json5#L10)), and `SyliusRedirectPlugin@3.x` passes `testsuite: "unit"` to `code-coverage` ([`build.yaml` L85–88](https://github.com/Setono/SyliusRedirectPlugin/blob/85ffb2188381b73f525efa8d7c2b7b3f58bf348e/.github/workflows/build.yaml#L85-L88)).
- **Per option**:
  - **PHPUnit-based** (playwright-symfony, Panther, zenstruck/browser): a third `<testsuite>` (`browser`/`ui`) run by a job that installs browsers. One way is `functional-tests` with `testsuite: browser` after a browser-install step, which the action doesn't have. The other is a hand-rolled job like the CMS plugin's. The `code-coverage` and `mutation-tests` jobs must exclude the suite or install browsers.
  - **Behat**: a separate job, outside every `setono/sylius-plugin` action. Sylius' own route is `BuildTestAppAction` with `e2e_js`.
  - **Playwright JS**: a separate Node job and runner, plus a running server.

## 6. Playbook and skeleton notes

- **Playbook vs skeleton on browser tests.** The playbook's intro says divergences from the skeleton are called out ([L19](https://github.com/Setono/SyliusPluginSkeleton/blob/ecdeaf21fd5bf2d68017c128f0c42d493a20d7c3/sylius-v1-to-v2-upgrade-playbook.md#L19)), but it never mentions the skeleton's browser-test convention:
  - §9 documents only `unit` and `functional` (L605–618).
  - §16 "UI verification with Playwright" covers only the Playwright MCP (L905–931).
  - §21 step 16 asks for "Playwright green on every UI surface" (L1032) without saying whether that means MCP checks or a test suite.

  Filed on the skeleton as [Playbook §9/§16/§21: no mention of the skeleton's playwright-symfony browser-test suite](https://github.com/Setono/SyliusPluginSkeleton/issues/109).
- **The skeleton's browser-test recipe meets two jobs that run every suite.** `code-coverage` and `mutation-tests` would run a `browser` suite without browsers, and Infection would also run DB-backed functional tests without MySQL. Filed as [Skeleton CI: code-coverage and mutation-tests run every PHPUnit suite, so a browser (or DB-backed) suite breaks them](https://github.com/Setono/SyliusPluginSkeleton/issues/110).
