# Terms answers are dispatched after the Form's save, not on submit

The plugin does not store answers (ADR 0001); it hands them to integrators in one event, which is dispatched only once the submitted Form's work is saved. Each Form names the event that marks its save (checkout complete: `sylius.order.post_complete`; registration: `sylius.customer.post_register`); a Form with nothing to save, such as contact, dispatches right after its valid submit. Until then the answers wait in a per-request holder.

We rejected dispatching when the Form validates. On checkout, Sylius' `OrderIntegrityChecker` can still stop the order after that point (an expired promotion, a changed total) and flushes the entity manager as it does, so a consent record an integrator persisted would be saved for an order that never completed, and duplicated when the customer resubmits. On registration the customer would have no ID yet. Changing a public event's timing after release breaks every listener, so it is fixed now.

## Consequences

- A custom Form gets the after-save guarantee only if its config names a save event; otherwise its answers are dispatched on valid submit.
- Listeners can no longer change the subject inside Sylius' own flush; they flush their own changes.
