## Agent skills

- When grilling (`/mattpocock-skills:grilling`, `/mattpocock-skills:grill-me`, `/mattpocock-skills:grill-with-docs`, and grilling inside other skills), ask via the `AskUserQuestion` tool instead of as plain chat text. The skills ask in chat on purpose and leave this to the project's CLAUDE.md ([mattpocock/skills: native question tool](https://github.com/mattpocock/skills/blob/main/.out-of-scope/native-question-tool.md)).

### Issue tracker

Issues and specs live in GitHub Issues on `Setono/SyliusTermsPlugin`, managed with the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

The five default triage labels are used as-is (`needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`). See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: one `GLOSSARY.md` and `docs/adr/` at the repo root, created only when a term or decision gets settled. See `docs/agents/domain.md`.
