# Terms content is Markdown that allows HTML, sanitised on output

Terms content is written in Markdown and stored as written. Raw HTML inside it is allowed, and every rendering of it goes through an HTML sanitiser, the way GitHub renders Markdown. We chose this so that 2.x content, which is HTML typed into a plain textarea, carries over with no conversion and no format flag, and so that authors can still use HTML where Markdown falls short.

## Considered options

- **Escape raw HTML and convert 2.x rows to Markdown on upgrade.** Rejected: `league/html-to-markdown` cannot convert nested lists, and legal texts use them for numbered sub-clauses.
- **Escape raw HTML and flag legacy rows as HTML.** Rejected: it keeps two render paths for good, and the flag would have to be part of every Terms version.
- **WYSIWYG HTML.** Rejected: it needs an editor the app has to build, and it renders less cleanly in email.

## Consequences

- Safety comes from the sanitiser, not from escaping. The plugin renders with its own CommonMark converter, never the app-wide `twig_extra` one, whose defaults are unsafe. An app that replaces the renderer has to sanitise its output itself.
- Some 2.x rows render differently. Lines indented by four spaces after a blank line become code blocks, and plain text that starts with `1.`, `-` or `#` becomes a list or a heading. Styling held in `class` and `style` attributes is dropped. UPGRADE.md lists these cases.
- The sanitiser's default 20,000-character input limit is lifted, because legal texts are often longer.
