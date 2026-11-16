# A Terms version covers the whole Terms in every locale, and only its text

A Terms version snapshots a Terms' name, label and content in every locale at once, and is numbered per Terms. Any save that changes one of those fields in any locale creates the next version, in force immediately. We chose whole-Terms versions over a history per locale so that one number per Terms identifies "what the customer accepted" whatever their locale. The cost: a German-only edit also bumps the English version number, though the English text didn't change.

Where a Terms is shown (channels, Forms, enabled, required) is not part of a version. A version records what a Terms said, not whether it was on a given form. So the inferred acceptance (ADR 0001) can name a version for an order whose checkout never showed that Terms, for example when a Terms is added to checkout after the order. We accepted this rather than bumping versions on every placement change or keeping a separate placement history. Callers that need to know whether a Terms was shown judge that from the current placement.

## Consequences

- A Terms only has a version in force from its first version on. Lookups before that moment, or in a locale missing from the version in force, return nothing, with no fallback.
- If an admin saves a new version while a customer is mid-checkout, the order is inferred to have accepted the new version. This race is documented, not prevented.
