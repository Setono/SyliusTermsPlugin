# Terms content: authoring and rendering options in Sylius 2

Research for [#90](https://github.com/Setono/SyliusTermsPlugin/issues/90), a child of the 3.0 map [#87](https://github.com/Setono/SyliusTermsPlugin/issues/87). The decision itself belongs to [#95](https://github.com/Setono/SyliusTermsPlugin/issues/95); this file only collects facts and a comparison.

- Researched: 2026-11-16. Versions and dates below are as of that day.
- Primary sources: Sylius v2.3.2 source, Sylius-Standard `2.3`, Sylius TestApplication `2.3`, Sylius CmsPlugin v1.2.0, Setono SyliusPluginSkeleton `2.2.x` (commit `ecdeaf2`, 2026-11-02), library repositories, licence pages, Packagist and the npm registry.
- Statements marked *(inference)* are my reading of the sources, not something a source says outright. Nothing was prototyped.

## 1. Baseline: what 2.x does today

- `TermsTranslation.content` is a nullable `text` column ([`TermsTranslation.orm.xml`](../../src/Resources/config/doctrine/model/TermsTranslation.orm.xml)).
- It is edited in a plain `TextareaType` ([`TermsTranslationType`](../../src/Form/Type/TermsTranslationType.php)).
- It is printed unescaped on the shop terms page: `{{ terms.content|raw }}` ([`show.html.twig`](../../src/Resources/views/shop/terms/show.html.twig)). Any markup an admin saves, scripts included, reaches the shop as-is.
- The fixture factory fills it with `faker->paragraph`, which is plain text with no tags ([`TermsExampleFactory`](../../src/Fixture/Factory/TermsExampleFactory.php)). So existing rows can be HTML or plain text.
- Both earlier Sylius 2 PRs ([#82](https://github.com/Setono/SyliusTermsPlugin/pull/82), [#84](https://github.com/Setono/SyliusTermsPlugin/pull/84)) kept the textarea and `{{ terms.content|raw }}`.
- In 2019 the maintainer said in [#1](https://github.com/Setono/SyliusTermsPlugin/issues/1) that he was "leaning more and more towards markdown". The same thread linked MonsieurBiz SyliusRichEditorPlugin and the FriendsOfSylius WYSIWYG guide.

## 2. Shipping editor JavaScript in the Sylius 2 admin

Every option that needs JavaScript in the browser runs into this.

### 2.1 How the Sylius 2 admin loads JS

- The admin layout prints `{{ encore_entry_script_tags('admin-entry', null, 'admin') }}` from `@SyliusAdmin/shared/layout/base/javascripts.html.twig`. That template is hooked into `sylius_admin.base#javascripts` ([layout.yaml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/Resources/config/app/twig_hooks/common/layout.yaml)). A plugin can add its own template to the same hook.
- `@sylius-ui/admin` offers two webpack builders ([Resources/npm/index.js](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/Resources/npm/index.js)):
  - `getBaseWebpackConfig()` is the "light Webpack configuration… All the stimulus stuff should be handled by the app.admin entrypoint".
  - `getWebpackConfig()` provides "a ready-to-use Stimulus bridge. Should be used only for sylius/sylius tests". It wires Sylius' own `controllers.json` and nothing else.
- Sylius-Standard 2.3 (the app template) uses `getBaseWebpackConfig()` and adds its own `app.admin` build ([webpack.config.mjs](https://github.com/Sylius/Sylius-Standard/blob/2.3/webpack.config.mjs)):
  - The Stimulus bridge is fed by merging the root `assets/controllers.json` with `assets/admin/controllers.json`. The comment there reads: "remove the following line if you don't want to add automatically controllers provided by plugins".
  - Plugin JS reaches the build through manual imports in the app's [`assets/admin/entrypoint.js`](https://github.com/Sylius/Sylius-Standard/blob/2.3/assets/admin/entrypoint.js), for example `import '@vendor/sylius/mollie-plugin/assets/admin/entrypoint';`, using the `@vendor` alias.
  - The `app-admin-entry` script is printed by the app's `templates/admin/javascripts.html.twig`.
- Symfony Flex syncs the app's root `assets/controllers.json` and `package.json` automatically for Composer packages that meet two conditions ([PackageJsonSynchronizer](https://github.com/symfony/flex/blob/2.x/src/PackageJsonSynchronizer.php), [Symfony docs: Create a UX bundle](https://symfony.com/doc/current/frontend/create_ux_bundle.html)):
  - the `symfony-ux` keyword is in `composer.json`, and
  - an `assets/package.json` declares `symfony.controllers`.

  In a Sylius-Standard app, such a plugin's Stimulus controller therefore reaches the admin (and the shop) build after `yarn install && yarn build`, with no manual import. Plain imported JS (not a Stimulus controller) still needs the manual entrypoint import.
- The official Sylius plugin skeleton ships `assets/admin/entrypoint.js` and `assets/admin/controllers.json` for exactly this purpose ([Sylius/PluginSkeleton](https://github.com/Sylius/PluginSkeleton/tree/2.2/assets/admin)). Sylius' TestApplication builds a plugin's `../../../assets/admin/entrypoint.js` as `plugin-admin-entry` and merges the plugin's `controllers.json` ([TestApplication webpack.config.js](https://github.com/Sylius/TestApplication/blob/2.3/webpack.config.js)).

### 2.2 The three delivery routes available to a plugin

| Route | How | Who pays | Precedent |
|---|---|---|---|
| **A. No plugin JS** | Plain `TextareaType`. Optionally a server-rendered preview by making the form a Live Component: Sylius' generic `Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent` is registered per resource, as `sylius_admin.twig.component.promotion_coupon.form` shows ([promotion_coupon.php](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/Resources/config/services/twig/component/promotion_coupon.php)). Sylius already ships `@symfony/ux-live-component`. | Nobody. The preview approach is feasible on paper, not prototyped. | Sylius core admin forms |
| **B. App-built** | Plugin ships `assets/admin/entrypoint.js` and/or a Stimulus controller with `assets/package.json` (+ `symfony-ux` keyword for Flex auto-sync). The app installs the npm deps and rebuilds. | Every installing app: npm deps, possibly a manual entrypoint import, a rebuild on every upgrade. | Sylius CmsPlugin v1.2.0 (below) |
| **C. Prebuilt** | Plugin runs its own Encore build, commits the output under `src/Resources/public`, and adds a hook template on `sylius_admin.base#javascripts` that loads it after `assets:install`. | The plugin repo: a JS toolchain, committed build artefacts, and the bundled editor's licence travels inside the plugin. | MonsieurBiz SyliusRichEditorPlugin v3.2.0 ([webpack.config.js](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/v3.2.0/webpack.config.js), [twig_hooks.yaml](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/v3.2.0/src/Resources/config/sylius/twig_hooks.yaml)) |

**Sylius CmsPlugin as a precedent.** This is the official, Sylius-maintained Sylius 2 plugin ([v1.2.0](https://github.com/Sylius/CmsPlugin/tree/v1.2.0), 2026-11-03). It offers two WYSIWYG strategies behind one `WysiwygType`:

- **Trix**: imported in [`assets/admin/js/trix/trix-editor.js`](https://github.com/Sylius/CmsPlugin/blob/v1.2.0/assets/admin/js/trix/trix-editor.js), with a form theme printing `<trix-editor>` against a hidden input ([trix.html.twig](https://github.com/Sylius/CmsPlugin/blob/v1.2.0/templates/admin/shared/editor/trix.html.twig)).
- **Quill**: through the `ehyiah/ux-quill` Symfony UX bundle, registered in the plugin's [`assets/admin/controllers.json`](https://github.com/Sylius/CmsPlugin/blob/v1.2.0/assets/admin/controllers.json).

Both need workarounds to survive Live Component re-renders. [`QuillStrategy`](https://github.com/Sylius/CmsPlugin/blob/v1.2.0/src/Form/Strategy/Wysiwyg/QuillStrategy.php) says: "The Quill widget is data-live-ignore, so a morph would leave a stale editor behind: rebuild the whole row from the server HTML whenever it changes (same as the Trix editor)". It also sets `data-model="norender|<full_name>"`. Any editor in a Live-Component form will need the same care *(inference)*.

**The Setono skeleton test app cannot exercise route B as it stands.** [`tests/Application/webpack.config.js`](https://github.com/Setono/SyliusPluginSkeleton/blob/2.2.x/tests/Application/webpack.config.js) calls `SyliusAdmin.getWebpackConfig()`, the "sylius/sylius tests" builder, with no `app.admin` build and no plugin entrypoint. A plugin controller or entrypoint is never built in the test app unless that file is reworked along the lines of Sylius' TestApplication. See the errata candidates in section 10.

## 3. Option family: Markdown

### 3.1 Rendering (server side)

- **`league/commonmark`**: BSD-3-Clause, 2.10.7 (2026-10-31), PHP `^7.4 || ^8.0` ([Packagist](https://packagist.org/packages/league/commonmark), [repo](https://github.com/thephpleague/commonmark)). Security defaults per the [security docs](https://commonmark.thephpleague.com/2.x/security/):
  - "All HTML input is unescaped by default". `html_input` accepts `allow`, `escape` and `strip`.
  - "Unsafe links are also allowed by default due to CommonMark spec compliance". Set `allow_unsafe_links: false` to change that.
  - `max_nesting_level` and `max_delimiters_per_line` are unlimited by default.
  - The page says some users add HTMLPurifier on top.
- **Tables are not CommonMark.** They need the Table or GitHub-Flavored Markdown extension. The GFM bundle includes [Disallowed Raw HTML](https://commonmark.thephpleague.com/2.x/extensions/disallowed-raw-html/), which only neutralises nine tags (`title`, `textarea`, `style`, `xmp`, `iframe`, `noembed`, `noframes`, `script`, `plaintext`) by escaping their `<`. "Other HTML tags pass through unchanged", so it is not a sanitiser. For example, an `<img onerror>` would survive under `html_input: allow` *(inference from the extension's documented scope)*.
- **`twig/markdown-extra`**: MIT, stable v3.29.0 (2026-09-06), PHP ≥ 8.2 ([Packagist](https://packagist.org/packages/twig/markdown-extra), [docs](https://twig.symfony.com/doc/3.x/filters/markdown_to_html.html)). It provides the `markdown_to_html` filter, whose output "is marked as safe for HTML".
  - [`DefaultMarkdown`](https://github.com/twigphp/Twig/blob/v3.30.0/extra/markdown-extra/DefaultMarkdown.php) picks the first installed library in this order: `league/commonmark`, Michelf, Parsedown, Tempest.
  - With `twig/extra-bundle`, the converter is configured by the **app-global** `twig_extra.commonmark` tree ([Configuration.php](https://github.com/twigphp/Twig/blob/v3.30.0/extra/twig-extra-bundle/DependencyInjection/Configuration.php)). `html_input` has no default there (so CommonMark's `allow` applies) and `allow_unsafe_links` defaults to `true`.
  - Consequence *(inference)*: a plugin that calls the app's `markdown_to_html` inherits whatever safety settings the app chose, which are unsafe by default. The safe alternative is for the plugin to register its own `CommonMarkConverter` or `GithubFlavoredMarkdownConverter` service with `html_input: escape|strip` and `allow_unsafe_links: false`. It would expose that through its own Twig filter or function, so the shop page and the email go through one converter. That needs only `league/commonmark`; `twig/markdown-extra` becomes optional.

### 3.2 Editors

| Editor | Licence | Health | Fit |
|---|---|---|---|
| Plain `<textarea>` | n/a | n/a | Route A. A preview can be server-rendered via a Live Component using the same PHP converter, so preview and output cannot disagree. |
| [EasyMDE](https://github.com/Ionaru/easy-markdown-editor) | MIT | `easymde` 2.21.0 (2026-05-03). Depends on `codemirror ^5.65.15` (CodeMirror 5 line; 5.65.21 on 2026-02-07, while `codemirror` latest is 6.0.2) and `marked ^4.1.0` (marked latest 18.2.0) ([npm](https://www.npmjs.com/package/easymde)). | Route B or C. It is not a Stimulus controller, so the plugin writes a small controller that attaches it to the textarea. Its preview uses `marked` in JS, not the PHP renderer, so edge cases can differ *(inference)*. |
| [Toast UI Editor](https://github.com/nhn/tui.editor) | MIT | Repo **archived**. npm `@toast-ui/editor` 3.2.3 carries the deprecation "Package no longer supported." | Not viable. |
| Markdown-storing WYSIWYG: [Tiptap](https://github.com/ueberdosis/tiptap) + `@tiptap/markdown`, [Milkdown](https://github.com/Milkdown/milkdown), CKEditor 5 + `@ckeditor/ckeditor5-markdown-gfm` | Tiptap and Milkdown MIT. CKEditor licence: see 4.1. | `@tiptap/markdown` 3.34.0 (2026-11-13; uses `marked`). `@milkdown/kit` 7.23.0 (2026-10-27). `@ckeditor/ckeditor5-markdown-gfm` 49.1.0. | Route B or C. Tiptap and Milkdown are headless, so the plugin builds the toolbar UI. The stored Markdown's formatting (list markers, wrapping) is decided by the editor's serialiser, so the first save of an old row can produce diff noise *(inference)*. |

### 3.3 Markdown and the other criteria

- **Sanitising.** Renderer-level. `html_input: escape` (or `strip`) plus `allow_unsafe_links: false` yields output with no raw HTML and no `javascript:` links. No separate sanitiser is needed unless `html_input: allow` is kept, for example for legacy rows.
- **Diff.** Best of all options. The source is line-oriented text whose line breaks the author chose, so ordinary line or word diffs are readable (section 6).
- **Email.** Good. Core CommonMark output is class-free semantic HTML (`p`, `h1`–`h6`, `ul`/`ol`/`li`, `a`, `strong`/`em`, `blockquote`, and `table` with GFM). It inherits the email layout's inline-styled container. The Markdown source also reads acceptably as plain text if a text part is ever added (section 7).

## 4. Option family: WYSIWYG HTML

### 4.1 Licences

- **CKEditor 5** ([licence and legal](https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-and-legal.html), [licence key](https://ckeditor.com/docs/ckeditor5/latest/getting-started/licensing/license-key-and-activation.html)):
  - "CKEditor is dual-licensed": "The open-source distribution of CKEditor 5 is licensed under the terms of GPL 2+", or commercial.
  - Self-hosted open-source use sets `licenseKey: 'GPL'`. "The 'GPL' key works only with the self-hosted distribution (npm or ZIP download)".
  - GPL use shows a "Powered by CKEditor" logo.
  - The LTS edition "cannot be used under GPL".
  - Projects with an "OSS license incompatible with GPL" are told to contact CKSource.
  - Premium features (real-time collaboration, Word import/export, multi-level lists, merge fields, AI and others) are commercial only.
  - Cloud and CDN use is commercial and usage-billed, with a free plan of "up to 1,000 editor loads per month".
- **TinyMCE**:
  - Since TinyMCE 7.0 (March 2024) the licence is GPLv2+, announced in [tinymce discussion #9496](https://github.com/tinymce/tinymce/discussions/9496). Older versions keep their MIT licence.
  - TinyMCE 6 (MIT) open-source reached end of life on 2024-10-31 with no further security patches ([discussion #9609](https://github.com/tinymce/tinymce/discussions/9609)).
  - TinyMCE 8 self-hosted: "a license key must be provided and it must be valid. Otherwise, the editor will be disabled". `license_key: 'gpl'` selects GPL ([licence key docs](https://www.tiny.cloud/docs/tinymce/latest/license-key/)).
  - [HugeRTE](https://github.com/hugerte/hugerte) is an MIT fork of TinyMCE 6, at v1.0.15 (2026-10-25).
- **Trix**: MIT. **Quill**: BSD-3-Clause. **Tiptap** core packages: MIT. **SunEditor**: MIT.
- **FOSCKEditorBundle**: MIT, 2.8.0. It integrates **CKEditor 4** ([README](https://github.com/FriendsOfSymfony/FOSCKEditorBundle/blob/2.x/README.md)), and "As of 30 June 2023, CKEditor 4 is considered end-of-life" with security fixes only in the commercial LTS ([ckeditor.com/ckeditor-4](https://ckeditor.com/ckeditor-4/)). Not viable.

### 4.2 Integration and output characteristics

| Editor | Latest (date) | Symfony / Sylius 2 integration | Output notes |
|---|---|---|---|
| **Trix** | 2.1.19 (2026-05-09), Basecamp | A `<trix-editor input="…">` custom element; `import 'trix'` is enough, no Stimulus needed. Proven in a Sylius 2 Live form by Sylius CmsPlugin. | "outputs terse and consistent HTML". Supported formats: inline `bold`, `italic`, `href`, `strike`; block `heading1`, `quote`, `code`, `bullet`, `number`. So there is **one heading level and no tables** ([README](https://github.com/basecamp/trix/blob/main/README.md)). Its content styles live under `.trix-content` in `trix.css`. The README warns: never place untrusted HTML inside the `<trix-editor>` tag; use the hidden input. |
| **Quill 2** via [`ehyiah/ux-quill`](https://github.com/ehyiah/ux-quill) | quill 2.0.3 (2024-11-30; repo last push 2025-07-25). ux-quill 3.10.0 (2026-08-08), MIT, needs `symfony/stimulus-bundle`, works with Encore and AssetMapper. | A Symfony UX bundle (Stimulus controller, Flex-syncable). Proven in Sylius CmsPlugin. | In Quill 2.0.3 the list container's tag is `OL` and each item carries `data-list="bullet\|ordered"` ([list.ts](https://github.com/slab/quill/blob/v2.0.3/packages/quill/src/formats/list.ts)). Only `getSemanticHTML()` converts this to `ul`/`ol` ([editor.ts](https://github.com/slab/quill/blob/v2.0.3/packages/quill/src/core/editor.ts)). ux-quill stores `quill.root.innerHTML` unless `extra_options.use_semantic_html` is set ([controller.ts](https://github.com/ehyiah/ux-quill/blob/main/assets/src/controller.ts), [extra options](https://github.com/ehyiah/ux-quill/blob/main/docs/guide/configuration/extra-options.md)). By default, then, bullet lists are stored as `<ol>` and render numbered anywhere Quill's CSS is absent. ux-quill also has a `style: class\|inline` option. Initial HTML is re-parsed through `quill.clipboard.convert`, so unsupported markup is normalised away on the first save. |
| **Tiptap 3** | 3.34.0 (2026-11-13), very active | Headless. I found no first-party Symfony integration, so the plugin writes the Stimulus controller and the toolbar. | Schema-driven semantic HTML from `getHTML()`. Markup outside the schema is dropped on load *(inference from the ProseMirror model)*. |
| **TinyMCE 8** / HugeRTE | 8.10.2 (2026-11-11) / 1.0.15 | [`eckinox/tinymce-bundle`](https://packagist.org/packages/eckinox/tinymce-bundle) v2.0.0 (2024-04-25). TinyMCE also publishes a Composer package [`tinymce/tinymce`](https://packagist.org/packages/tinymce/tinymce) with prebuilt files. | Loads arbitrary HTML well. Licence as in 4.1. |
| **CKEditor 5** | 49.1.0 (2026-11-04), very active | No CKSource Symfony bundle. [`mati365/ckeditor5-symfony`](https://packagist.org/packages/mati365/ckeditor5-symfony) 1.11.3 is unofficial and requires PHP ≥ 8.3, above the 3.0 floor of 8.2. | Schema-driven. Some features serialise with wrapper elements or classes (for example tables as `<figure class="table">`), and markup outside enabled features is dropped unless General HTML Support is configured *(general CKEditor 5 knowledge, not re-verified here)*. |
| **MonsieurBiz SyliusRichEditorPlugin** | v3.2.0 (2026-09-30), MIT, 75 stars | **Sylius 2 is supported.** The README table lists "2.0, 2.1". `composer.json` requires `php ^8.2`, `sylius/sylius ~2.0` (so 2.3 installs), plus `twig/markdown-extra`, `twig/extra-bundle` and `league/commonmark` ([composer.json](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/v3.2.0/composer.json)). It ships prebuilt assets in `src/Resources/public`, loaded via the `sylius_admin.base#javascripts` hook (route C), so the app needs no build. | `RichEditorType` stores a **JSON array of UI elements** (`code` + `data`), not HTML, rendered with `monsieurbiz_richeditor_render_field`. Pre-existing HTML is wrapped "as an HTML Element which contains… HTML". It has Markdown and HTML elements. A separate admin-only `WysiwygType` uses SunEditor and stores HTML ([README](https://github.com/monsieurbiz/SyliusRichEditorPlugin/blob/v3.2.0/README.md)). Adopting it means a hard dependency on another plugin and on the full `sylius/sylius` metapackage. |

## 5. Sanitising

- **Today, nothing sanitises.** `|raw` prints whatever was saved.
- **The email has no escaping either.** The core email layout wraps the whole body in `{% autoescape false %}` ([layout.html.twig](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/CoreBundle/Resources/views/Email/layout.html.twig)), so whatever reaches the email template must already be safe.
- **`symfony/html-sanitizer`** (MIT, [docs](https://symfony.com/doc/current/html_sanitizer.html), [CHANGELOG](https://github.com/symfony/html-sanitizer/blob/7.4/CHANGELOG.md)):
  - Added in 6.1 "as experimental". 6.4 added support for unlimited input length; 7.2 added a configurable default action; 7.4 uses PHP 8.4's native HTML5 parser.
  - The Twig filter `sanitize_html` (with an optional sanitizer name) works on output.
  - On input there is a form option: `sanitize_html` / `sanitizer` on `TextType` and its children such as `TextareaType`. It runs in a `PRE_SUBMIT` listener at priority 10000 ([TextTypeHtmlSanitizerExtension](https://github.com/symfony/form/blob/6.4/Extension/HtmlSanitizer/Type/TextTypeHtmlSanitizerExtension.php)).
  - **Length gotcha.** "by default the HTML sanitizer limits the input length to `20000` characters… All the contents exceeding that length will be truncated". Long terms would be cut silently unless `max_input_length` is raised (`-1` means no limit, per [`withMaxInputLength`](https://github.com/symfony/html-sanitizer/blob/7.4/HtmlSanitizerConfig.php)).
  - **`allowSafeElements()` drops `class` and `style`.** Both are marked unsafe in [`W3CReference`](https://github.com/symfony/html-sanitizer/blob/7.4/Reference/W3CReference.php). Class- or style-based editor formatting (Quill alignment and indent, CKEditor table wrappers, TinyMCE inline styles) is stripped unless explicitly allowed.
  - **Not configured by Sylius.** Sylius 2.3.2 neither requires `symfony/html-sanitizer` nor configures `framework.html_sanitizer`, so the plugin would add the dependency. It could build its own `HtmlSanitizer` service or prepend framework config *(inference: either works; building its own avoids touching app config)*.
- **`ezyang/htmlpurifier`** is an older alternative: LGPL-2.1, v4.19.1 (2026-09-15).
- **Per format:**
  - *Markdown with `escape`/`strip`*: safe by construction at render time.
  - *Markdown with `allow`*: needs an output sanitiser.
  - *WYSIWYG HTML*: sanitise on submit (stores clean HTML) and/or on output. Output sanitising also covers rows saved before 3.0. Any editor's own client-side cleaning (for example Trix) is not a security boundary, because requests can bypass the editor *(inference)*.

## 6. Versioning and diffs

3.0 adds immutable Terms version snapshots. Showing what changed between two versions is a natural admin feature. It is not specified yet.

- **Diff libraries** (all checked on Packagist):
  - [`sebastian/diff`](https://github.com/sebastianbergmann/diff), BSD-3-Clause: 6.x needs PHP ≥ 8.2, 7.x ≥ 8.3, 9.x ≥ 8.4. It is also pulled in by PHPUnit in dev.
  - [`jfcherng/php-diff`](https://github.com/jfcherng/php-diff), BSD-3-Clause: 6.16.x needs PHP ≥ 7.4, 7.0.1 needs ≥ 8.3. It renders inline and side-by-side HTML diffs at line, word or character level.
  - [`caxy/php-htmldiff`](https://github.com/caxy/php-htmldiff) is HTML-aware, but **GPL-2.0**; v0.1.17 dates from 2025-05-16.
- **Markdown** diffs well with the BSD libraries, because the line structure is the author's.
- **WYSIWYG HTML.** Line structure is whatever the editor serialises, and diffs carry markup noise. The options are an HTML-aware differ (the only one found is GPL-2.0), or diffing a text projection (`strip_tags`, or `league/html-to-markdown`), which loses structure *(inference)*.
- **MonsieurBiz JSON** needs render-then-diff.
- **Fidelity of a snapshot** *(inference)*:
  - A Markdown snapshot stores the source. The HTML a customer saw also depends on the converter version and configuration at render time. If a version must reproduce exactly what was shown, store the rendered HTML in the snapshot too, or freeze the render configuration.
  - The same applies to output-time sanitising of HTML: changing the sanitiser config later changes how old versions render.

## 7. Email (order confirmation)

- **HTML only, no text part.** SyliusMailerBundle v2.2.1 renders the template's `subject` and `body` blocks ([EmailTwigAdapter](https://github.com/Sylius/SyliusMailerBundle/blob/v2.2.1/src/Bundle/Renderer/Adapter/EmailTwigAdapter.php)). `SymfonyMailerAdapter` sends the body with `->html(...)` only ([SymfonyMailerAdapter](https://github.com/Sylius/SyliusMailerBundle/blob/v2.2.1/src/Bundle/Sender/Adapter/SymfonyMailerAdapter.php)).
- **The template has no hooks.** The order confirmation email is `@SyliusCore/Email/orderConfirmation.html.twig` (configured in [sylius_mailer.yml](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/CoreBundle/Resources/config/app/sylius/sylius_mailer.yml)). Its content block uses inline `style=""` attributes and contains no Twig hooks ([_content.html.twig](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/CoreBundle/Resources/views/Email/Blocks/OrderConfirmation/_content.html.twig)). How the plugin gets terms into it is a separate question for the email ticket.
- **Per format:**
  - *Markdown*: plain semantic HTML with no CSS dependency, which is the friendliest input for email clients *(inference)*.
  - *WYSIWYG HTML*: fine if the markup is semantic. Class-dependent markup degrades, because email clients never see the shop's or the editor's CSS. Quill's default `<ol data-list="bullet">` turns bullets into numbers; Trix's look comes from `.trix-content`; CKEditor uses class-based wrappers.
  - The fixes are to sanitise classes away, to use semantic output (`use_semantic_html` for ux-quill), or to inline CSS with `inline_css` from [`twig/cssinliner-extra`](https://twig.symfony.com/doc/3.x/filters/inline_css.html) (MIT).
  - *MonsieurBiz JSON*: must go through its Twig render filters, whose element templates are written for the shop, not for email *(inference)*.

## 8. Migrating existing 2.x rows

Existing rows hold HTML or plain text (section 1). This feeds the map's open "Data upgrade" item.

- **WYSIWYG HTML.** No format change, so nothing to convert. Each editor re-parses the HTML into its own model on load (Quill via `clipboard.convert`; Trix converts input into its document model; Tiptap and CKEditor apply their schemas), so markup outside the model is dropped the first time a row is saved. With Trix that includes `h2`–`h6` and tables *(inference from the supported-format lists)*. An output sanitiser covers rows that are never re-saved.
- **Markdown, keeping `html_input: allow`.** CommonMark passes raw HTML through ([spec 0.31.2](https://spec.commonmark.org/0.31.2/)):
  - A line starting with a block-level tag (`<p>`, `<div>`, `<table>`, `<ul>`, `<h1>`… HTML block type 6), or with any complete open tag (type 7), starts an HTML block. The block is passed through verbatim and ends at the next blank line.
  - Markdown inside an HTML block is not parsed. Inline tags inside a paragraph are kept as raw inline HTML.
  - **Edge cases.** After a blank line, a line indented by four or more spaces becomes an indented **code block**, so pretty-printed HTML with blank lines and indentation can break. In plain-text rows, lines starting with `1.`, `-`, `*` or `#` gain list or heading meaning, and blank lines become paragraph breaks.
  - Safety then rests on an output sanitiser.
- **Markdown with `escape` or `strip`.** Old HTML would render as literal tags (`escape`) or lose all markup (`strip`), so rows need a one-off conversion. [`league/html-to-markdown`](https://github.com/thephpleague/html-to-markdown) (MIT, 5.2.3, 2026-10-19) does it, with limits:
  - Tags with no Markdown equivalent are kept as HTML unless `strip_tags` or `remove_nodes` is set.
  - Tables need `TableConverter`.
  - H1 and H2 come out as Setext unless `header_style: atx` is set.
  - Its README To-do lists "Support for nested lists and lists inside blockquotes". **Nested lists are therefore unsupported**, which matters for numbered legal clauses.
  - The conversion is lossy and needs a review step.
- **A per-row format flag** (for example `html` or `markdown` on the translation or version) lets legacy rows keep rendering as sanitised HTML until someone edits them. The cost is two permanent render paths.

## 9. Comparison table

Status as of 2026-11-16. "Route" refers to section 2.2.

| Option | Editor fit with Sylius 2 admin | Licence | Sanitising story | Existing HTML rows | Versioning / diff fit | Email fit | Maintenance health |
|---|---|---|---|---|---|---|---|
| **HTML in plain textarea + sanitiser** (status quo, hardened) | Route A, zero JS; authors write HTML by hand | `symfony/html-sanitizer` MIT | Replace `\|raw` with `sanitize_html` on output and/or the `sanitize_html` form option; raise `max_input_length` | No conversion; sanitiser strips disallowed markup (`class`/`style` under safe config) | Raw HTML diff; readable only if authors format it | As good as the author's HTML; must be sanitised (email layout has autoescape off) | Symfony component |
| **Markdown in plain textarea** (+ optional server-rendered preview via Live Component) | Route A, zero JS; preview through Sylius' `ResourceFormComponent` (feasible, not prototyped) | `league/commonmark` BSD-3; `twig/markdown-extra` MIT (optional) | `html_input: escape\|strip` + `allow_unsafe_links: false` in a plugin-owned converter; don't rely on the app-global `twig_extra.commonmark` (unsafe defaults) | `allow`: mostly passes through, with blank-line/indentation and plain-text edge cases. `escape`/`strip`: convert (html-to-markdown, no nested lists) or keep a per-row format flag | Best: author-controlled lines; BSD diff libs | Best: class-free semantic HTML; source readable as text | commonmark 2.10.7 (2026-10-31), very active |
| **Markdown + EasyMDE** | Route B or C plus a small plugin Stimulus wrapper | MIT (CodeMirror 5 MIT, marked MIT) | Same as Markdown; JS preview (`marked`) ≠ PHP output | Same as Markdown | Same as Markdown | Same as Markdown | 2.21.0 (2026-05-03), but built on legacy CodeMirror 5 and `marked ^4` |
| **WYSIWYG storing Markdown** (Tiptap + `@tiptap/markdown`, Milkdown) | Route B or C; headless, so the plugin builds the toolbar; Live-morph care needed | MIT (CKEditor 5 markdown-gfm: GPL-2+/commercial) | Same as Markdown on output | Same as Markdown, plus serialiser normalisation on first save | Good, with possible one-time reformat churn | Same as Markdown | Tiptap 3.34.0 (2026-11-13), Milkdown 7.23.0 (2026-10-27) |
| **Trix** | Route B (CmsPlugin pattern) or C; custom element, no Stimulus needed; proven in a Sylius 2 Live form | MIT | Server sanitiser required; Trix's own cleaning is client-side only | Loaded as-is, normalised on save; loses `h2`–`h6` and tables | HTML diff with markup noise | Simple markup; `.trix-content` styling is absent in email | 2.1.19 (2026-05-09), Basecamp |
| **Quill 2 via `ehyiah/ux-quill`** | Route B with Flex-syncable Symfony UX bundle; proven in Sylius CmsPlugin | Quill BSD-3, ux-quill MIT | Server sanitiser must allow Quill markup or formatting is lost | Re-parsed via `clipboard.convert`, normalised on save | HTML diff with Quill-specific markup | Weak by default: bullets stored as `<ol data-list="bullet">`; needs `use_semantic_html` | quill 2.0.3 (2024-11-30, no release in ~2 years); ux-quill 3.10.0 (2026-08-08) |
| **Tiptap (HTML output)** | Route B or C; headless, no first-party Symfony integration; plugin writes controller and toolbar | MIT (core) | Server sanitiser; output is semantic | Schema re-parse on load, unknown markup dropped | HTML diff | Good after sanitising | 3.34.0 (2026-11-13), very active |
| **TinyMCE 8** (or HugeRTE fork) | Route B or C; `eckinox/tinymce-bundle` (2024); Composer `tinymce/tinymce` has prebuilt files | **GPLv2+** since 7.0; `license_key: 'gpl'` or the editor is disabled. HugeRTE MIT | Server sanitiser; inline styles dropped by safe config | Loads arbitrary HTML well | HTML diff | OK after sanitising | 8.10.2 (2026-11-11); HugeRTE 1.0.15 (2026-10-25) |
| **CKEditor 5** | Route B or C; no official Symfony bundle (unofficial one needs PHP ≥ 8.3); FOSCKEditorBundle is CKEditor 4 (EOL) | **GPL-2+ or commercial**; `licenseKey: 'GPL'` self-hosted only; "Powered by CKEditor" logo | Server sanitiser; class-based wrappers stripped by safe config | Schema re-parse; needs General HTML Support to keep foreign markup | HTML diff | OK after sanitising; class-based features degrade | 49.1.0 (2026-11-04), very active |
| **MonsieurBiz RichEditor** (`RichEditorType`) | Route C, prebuilt, no app build; supports Sylius 2 (README 2.0/2.1; composer `~2.0`) | MIT (SunEditor MIT) | Per UI element; its "HTML element" passes HTML | Legacy HTML auto-wrapped as an HTML element | Poor: JSON structure; render-then-diff | Must render via its shop-oriented Twig filters | v3.2.0 (2026-09-30); small project (75 stars); hard dep on full `sylius/sylius` |

Questions the decision ticket will have to answer *(not decided here)*:

1. Who writes terms in practice: shop staff who would accept Markdown, or lawyers pasting from Word, where WYSIWYG paste handling matters?
2. Are tables or deep nested numbering needed in terms? This rules out Trix, and affects whether html-to-markdown can do the migration.
3. Which JS delivery route is acceptable: A (none), B (every app rebuilds), or C (the plugin carries a build and a bundled licence)?
4. Should a Terms version snapshot store the rendered HTML next to the source?
5. Convert legacy rows, keep a per-row format flag, or keep `html_input: allow` plus a sanitiser?

## 10. Playbook / skeleton errata candidates

Found while checking the asset pipeline. They concern the skeleton's test application, which the playbook tells plugins to mirror (§9 "Key files to align with the skeleton"). No issues were opened.

1. **The playbook recommends a custom Stimulus controller, but neither it nor the skeleton gives plugin JS a path to the browser.**
   - *Claim, playbook §7a:* plugins on a non-Live-Component form "must either expose their own component or fall back to a custom Stimulus controller".
   - *Evidence:*
     - The playbook says nothing about shipping plugin JS.
     - The skeleton's [`tests/Application/webpack.config.js`](https://github.com/Setono/SyliusPluginSkeleton/blob/2.2.x/tests/Application/webpack.config.js) uses `SyliusAdmin.getWebpackConfig()`. Sylius documents that builder as "Should be used only for sylius/sylius tests" in both 2.2.10 and 2.3.2. It has no `app.admin` build, no app-owned Stimulus bridge and no plugin entrypoint.
   - *What is true:* the Sylius 2 convention is `assets/admin/entrypoint.js` + `assets/admin/controllers.json` in the plugin ([Sylius/PluginSkeleton](https://github.com/Sylius/PluginSkeleton/tree/2.2/assets/admin)). The app consumes them through `getBaseWebpackConfig()` plus an `app.admin` build: Sylius' [TestApplication](https://github.com/Sylius/TestApplication/blob/2.3/webpack.config.js) builds `plugin-admin-entry` and merges the plugin's controllers, and [Sylius-Standard 2.3](https://github.com/Sylius/Sylius-Standard/blob/2.3/webpack.config.mjs) does the app side. Flex can auto-register controllers for `symfony-ux`-keyworded packages.
2. **The skeleton test app still overrides Sylius 1 templates and imports Sylius 1 asset paths.**
   - *Evidence:*
     - `tests/Application/templates/bundles/SyliusAdminBundle/{_scripts,_styles,_logo}.html.twig` and `SyliusShopBundle/{_scripts,_styles}.html.twig`, `Homepage/_banner.html.twig`, `Layout/Header/_logo.html.twig` and `Layout/Footer/Grid/_plus.html.twig` all target templates that do not exist in Sylius 2.3.2. Admin scripts now come from `@SyliusAdmin/shared/layout/base/javascripts.html.twig` via the `sylius_admin.base#javascripts` hook, and shop templates live under lowercase `homepage/` and `shared/`.
     - `tests/Application/assets/{admin,shop}/entry.js` import `sylius/bundle/{Admin,Shop}Bundle/Resources/private/entry`. No `Resources/private` exists in Sylius 2, and `webpack.config.js` never references these files.
   - *What is true:* the files are dead and misleading; they are harmless at runtime.
3. **Forward-looking: the skeleton's `package.json` breaks on Sylius 2.3.** The skeleton pins `sylius/sylius ~2.2.10` (playbook §1 shows `~2.2.5`), and for 2.2 its [`tests/Application/package.json`](https://github.com/Setono/SyliusPluginSkeleton/blob/2.2.x/tests/Application/package.json) is correct. Sylius [UPGRADE-2.3.md](https://github.com/Sylius/Sylius/blob/v2.3.2/UPGRADE-2.3.md) changes two things the file depends on:
   - §6: installing `@sylius-ui/admin` and `@sylius-ui/shop` from the bundle root "is deprecated since Sylius 2.3 and will no longer be supported in Sylius 3.0". The new path is `…/Resources/npm`. The old path emits a build-time warning, as [AdminBundle/index.js](https://github.com/Sylius/Sylius/blob/v2.3.2/src/Sylius/Bundle/AdminBundle/index.js) shows.
   - §9: `@symfony/webpack-encore` moves from `^5.0.1` to `^6.0.0`.

   The skeleton still uses the bundle-root paths and `^5.0.1`. This is only an erratum once the floor or the dev pin moves to 2.3, which the map leaves open.

## Sources

Local, read-only: Sylius v2.3.2 source tree; Setono SyliusPluginSkeleton `2.2.x` @ `ecdeaf2` including `sylius-v1-to-v2-upgrade-playbook.md`. Everything else is linked inline above. Registry data came from `repo.packagist.org/p2/*.json` and `registry.npmjs.org/*`, and repository metadata (licence, archive flag, latest release) from the GitHub API, all on 2026-11-16.
