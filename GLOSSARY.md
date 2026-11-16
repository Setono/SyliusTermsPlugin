# Sylius Terms Plugin

Legal documents (terms and conditions, privacy policies, EULAs) that shop customers are shown and asked to accept on shop forms, per channel and locale.

## Language

**Terms**:
One legal document, such as the terms and conditions or a privacy policy, available in some channels and translated per locale. Plural in form, singular in use: "a Terms", "this Terms".
_Avoid_: Term, condition, policy, document

**Content**:
The full text of a Terms in one locale, as its author wrote it, shown on the terms page.
_Avoid_: Body, text, description

**Terms version**:
An immutable, numbered snapshot of a Terms' name, label and content in every locale, made each time any of them changes. It is in force from that moment until the next version, and records what a Terms said, not where it was shown.
_Avoid_: Revision, edition

**Terms page**:
The standalone shop page at a Terms' own URL, always showing the Terms version in force.
_Avoid_: Standalone page, terms URL

**Form**:
A shop form a Terms can be shown on, such as the checkout complete form. The integrator configures which forms are available; the admin picks which Terms appear on each.
_Avoid_: Step, page (when meaning the form)

**Label**:
The text next to a Terms checkbox on a form.

**Link placeholder**:
The `[link:Link text]` marker inside a label that becomes a link to the terms page.

**Required terms**:
A Terms that must be ticked before the form it appears on can be submitted.

**Optional terms**:
A Terms that may be left unticked. The plugin does not keep the customer's answer.

**Answer**:
Whether the customer ticked a Terms checkbox when submitting a form. The plugin passes answers on to the integrator but does not store them.
_Avoid_: Consent, choice

**Acceptance**:
A customer ticking a Terms checkbox and submitting the form. Acceptance is not stored: for an order, the accepted Terms version is the one in force when checkout completed.
_Avoid_: Agreement, consent record
