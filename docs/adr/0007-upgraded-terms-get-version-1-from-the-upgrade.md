# A Terms upgraded from 2.x gets version 1 in force from the upgrade, not from its creation

Upgrading a 2.x database to 3.0 makes version 1 of every Terms from its current translations. It is in force from the moment the upgrade command runs. 2.x never recorded when a Terms' text changed: translations carry no timestamps, and editing only a translation leaves the Terms' `updatedAt` as it was. Dating version 1 at `createdAt` or `updatedAt` would claim today's text was in force at times it may not have been, and orders from those times would be inferred to have accepted it (ADR 0001). We chose to infer nothing for those orders rather than something that may be false.

## Considered options

- **`createdAt`.** Rejected: every order since the Terms was created would be inferred to have accepted today's text, even if the text was edited in between.
- **`updatedAt`, falling back to `createdAt`.** Rejected: it is still wrong after an edit that changed only translations, and nothing in the data shows when that happened.

## Consequences

- Orders completed before the upgrade have no inferred Terms version. Resending their confirmation email attaches no Terms PDFs (ADR 0006).
- The admin shows every upgraded Terms as version 1, in force since the upgrade date.
