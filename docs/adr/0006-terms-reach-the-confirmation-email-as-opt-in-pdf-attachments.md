# Terms reach the order confirmation email as opt-in PDF attachments

When an app switches it on, the order confirmation email carries one PDF per required Terms on the checkout complete Form. Each PDF holds the Terms version in force when checkout completed, in the order's locale. This covers both `order_confirmation` and the admin's `order_confirmation_resent`. The email body is left as it is. We chose a PDF so that the customer gets a standalone file for each Terms version they accepted, which they can save, print or forward apart from the email. The feature is off by default, and its PDF tooling (`sylius/pdf-generation-bundle` with `dompdf/dompdf`) is suggested, not required, so shops that don't use it carry no PDF dependencies.

## Considered options

- **Full text inline in the email body.** It shows the exact version, the email itself is a durable medium, and it needs no new dependency. Rejected: the text is bound to the email's layout instead of being a file the customer keeps, and long legal texts make long emails.
- **Links to the Terms pages.** Rejected: there are no public URLs for past versions, so a link shows the current text, not the accepted one. In the EU a link isn't a durable medium either (CJEU, Content Services, C-49/11).
- **Required PDF tooling, on by default.** Rejected: every shop would install dompdf for a feature it may not use, and upgraders' emails would change without their asking.

## Consequences

- Only required Terms are attached. Answers aren't stored (ADR 0001), so the email can't know whether an optional Terms was ticked.
- Which Terms are attached follows their current placement (ADR 0002), so an admin resend after a placement change can differ from the first email.
- A PDF depends only on its Terms version, locale and channel, so it is cached and shared by every order that accepted that version.
- PDFs are generated while the email is sent, which by default is inside the checkout request. A failure is logged and the email goes out without that PDF; it never breaks checkout.
- Images in Content appear in the PDF because the plugin downloads them itself, with a timeout, and inlines them as `data:` URIs. The PDF engine never fetches anything. We don't leave this to dompdf, because dompdf draws a broken-image box when a download fails, without raising an error, and the broken PDF would be cached for every later order. A PDF with an image that failed to download is attached but not cached. Relative image URLs only resolve when the channel has a hostname.
- The feature can only be installed where `sylius/pdf-generation-bundle` can: Symfony 6.4 or 7.4 and later.
