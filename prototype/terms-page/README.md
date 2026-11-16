# PROTOTYPE: terms page and footer links in the Sylius 2 shop

Throwaway prototype for [What should the terms page and footer links look like in the Sylius 2 shop?](https://github.com/Setono/SyliusTermsPlugin/issues/96). This branch is never merged; the decision lives in the issue's resolution comment.

Run `prototype/terms-page/setup.sh`, then open <http://127.0.0.1:8096/en_US/terms/terms-and-conditions>. Use ←/→ to switch the page variant and Shift+←/→ to switch the footer variant, or click the black bar.

`app/` is copied over the skeleton's `tests/Application`. All data is stand-in: the Terms list is a Twig global, and the content is hand-written HTML shaped like the sanitised CommonMark output.

| Key | Variant | Verdict |
|---|---|---|
| `?variant=A` | Reading column: one centred column, version under the title | |
| `?variant=B` | Table of contents: sticky "On this page" from `##` headings, permalinks, version in the sidebar | **Chosen** (TOC server-side, no Print button, collapsed `<details>` on mobile) |
| `?variant=C` | Legal hub: every Terms of the channel beside the content, content in a card | |
| `?footer=1` | Plugin links alongside Sylius' `href="#"` placeholders | |
| `?footer=2` | Plugin replaces Sylius' placeholders | |
| `?footer=3` | Placeholders removed, separate legal strip under the footer | |

Footer verdict: none of the three. The plugin ships no footer hookables. Apps put `terms_link(code)` / `terms_path(code)` in their own footer, and the test app replaces Sylius' two placeholders with `terms_link()` in its own config.

`app/config/packages/zz_prototype_doctrine.yaml` works around the skeleton test app failing on PHP 8.4 (see [Test app fails on PHP 8.4](https://github.com/Setono/SyliusPluginSkeleton/issues/112)).

Screenshots are in `screenshots/`.
