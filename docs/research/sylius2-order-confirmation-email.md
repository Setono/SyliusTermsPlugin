# Adding Terms to the Sylius 2 order confirmation email

Research for [#91](https://github.com/Setono/SyliusTermsPlugin/issues/91), part of the Sylius 2 upgrade map [#87](https://github.com/Setono/SyliusTermsPlugin/issues/87). It collects facts for the decision ticket [#97](https://github.com/Setono/SyliusTermsPlugin/issues/97) ("What does the order confirmation email include about terms?"). It makes no decision.

Researched 2026-11-16. Primary sources, all read as source code, nothing executed:

| Source | Version | Why |
| --- | --- | --- |
| [`Sylius/Sylius`](https://github.com/Sylius/Sylius/tree/v2.3.2) | `v2.3.2` (latest stable) | email codes, templates, senders, triggers, order model |
| `Sylius/Sylius` | `v2.0.0`, `v2.1.0`, `v2.2.0`, branch `2.4` | lowest minor where each API exists |
| [`Sylius/SyliusMailerBundle`](https://github.com/Sylius/SyliusMailerBundle/tree/v2.2.1) | `v2.2.1` (Sylius 2.3.2 requires `^2.2`), `v2.1.0` (Sylius 2.0 to 2.2 require `^2.1`) | `SenderInterface`, attachments, events, email modifiers |
| [`Sylius/SyliusResourceBundle`](https://github.com/Sylius/SyliusResourceBundle/tree/v1.14.2) | `v1.14.2` | order of `pre_complete`, state machine, flush and `post_complete` |
| `symfony/mime` `v7.4.22`, `symfony/twig-bundle` `v7.4.21`, `symfony/dependency-injection` `v7.4.23` | | attachment loading, Twig path order, prepend semantics |
| [`Setono/SyliusPluginSkeleton`](https://github.com/Setono/SyliusPluginSkeleton/tree/2.2.x) `2.2.x` | incl. `sylius-v1-to-v2-upgrade-playbook.md` | playbook errata check |
| This plugin, branch `2.x` | | how the current code looks up and links Terms |

Paths below are relative to the repository named in each section. `S:` means `Sylius/Sylius` at `v2.3.2`; `M:` means `Sylius/SyliusMailerBundle` at `v2.2.1`.

## Answer in short

- **Bundle and code.** The email is defined by SyliusMailerBundle config in SyliusCoreBundle. Its code is `order_confirmation` (`Sylius\Bundle\CoreBundle\Mailer\Emails::ORDER_CONFIRMATION`). The admin "resend" uses a second code, `order_confirmation_resent`. Both are sent by `Sylius\Bundle\CoreBundle\Mailer\OrderEmailManager` through `sylius.email_sender` (`Sylius\Component\Mailer\Sender\SenderInterface`).
- **Template.** `@SyliusShop/email/order_confirmation.html.twig` (only `extends '@SyliusCore/Email/orderConfirmation.html.twig'`), which includes `@SyliusCore/Email/Blocks/OrderConfirmation/_content.html.twig`. The resent email uses `@SyliusCore/Email/orderConfirmation.html.twig` directly. Both end up in the same `_content.html.twig`.
- **Triggers.** There are three paths and three different service ids:
  - Shop checkout: `sylius.order.post_complete` → `sylius_shop.listener.order_complete` → `sylius_shop.mailer.order_email_manager`.
  - API checkout: `OrderCompleted` → `SendOrderConfirmation` command → `sylius.mailer.order_email_manager`.
  - Admin resend: `ResendOrderConfirmationEmail` command → `sylius.mailer.order_email_manager` with code `order_confirmation_resent`.
- **No Twig hooks.** No email template in Sylius 2.0.0 to 2.3.2, or on the `2.4` branch, has a hookpoint.
- **Ways a plugin can add content.**
  - The app overrides a template.
  - The email's `template` is re-pointed by app config or by an email modifier (`sylius_mailer.email_modifier` tag).
  - The plugin decorates `sylius.email_sender`, which can add render data and attachment file paths.
  - The plugin listens to `sylius.email_send.pre_send`, which gets the mutable `Symfony\Component\Mime\Email` plus the email code and data, so it can attach in-memory content.
  - Decorating only `OrderEmailManagerInterface` misses the shop checkout, because the shop uses its own service id.
  - A plugin cannot change the template through `prependExtensionConfig('sylius_mailer', …)`, because Sylius's own imported config wins.
- **Attachments.** Yes. `SenderInterface::send(string $code, array $recipients, array $data = [], array $attachments = [], array $replyTo = [])` takes `$attachments` as a list of **file paths**. The Symfony Mailer adapter calls `attachFromPath()` on each, and the file is read when the message is rendered, not when it is queued.
- **Render context.** The render data is `['order' => $order, 'channel' => $order->getChannel(), 'localeCode' => $order->getLocaleCode()]`, plus Twig globals. Only the `subject` and `body` blocks are rendered.
- **Getting channel, locale and time.**
  - Channel: `$order->getChannel()`.
  - Locale: `$order->getLocaleCode()`. The shop overwrites it with the request locale at `sylius.order.pre_complete`.
  - Checkout completion time: `$order->getCheckoutCompletedAt()`. The `complete` transition sets it (`new \DateTime()`) before the flush and before the email in every path.
  - Do not use request contexts. The `sylius` Twig global, `ChannelContext`, `LocaleContext`, and the current locale of translatable entities all follow the request, which is the admin's request (or none) on resend, API and CLI paths.
- **Lowest minor.** Every Sylius and mailer API above is identical from Sylius **2.0.0** (with mailer-bundle 2.1.0) to 2.3.2. Nothing in the email area raises the floor above what the skeleton already requires (`sylius/core-bundle ^2.2`). One behaviour change: since **2.2.12 / 2.3.2**, `sylius_channel_url` returns a relative path when the channel has no hostname and `trusted_hosts` is not set.
- **PDF tooling** in use with Sylius 2 plugins: `knplabs/knp-snappy-bundle` (wkhtmltopdf), `dompdf/dompdf`, Gotenberg, and the new `sylius/pdf-generation-bundle`, which wraps all three. Sylius's own InvoicingPlugin and RefundPlugin 2.x use the last two. Full list in section 4.

## 1. How Sylius 2 sends the order confirmation email

### Email definitions

| Code | Defined in | Template |
| --- | --- | --- |
| `order_confirmation` | `S:src/Sylius/Bundle/CoreBundle/Resources/config/app/sylius/sylius_mailer.yml` L13-15 | `@SyliusCore/Email/orderConfirmation.html.twig` |
| `order_confirmation` (redefined) | `S:src/Sylius/Bundle/ShopBundle/Resources/config/app/sylius/sylius_mailer.yml` L9-11 | `@SyliusShop/email/order_confirmation.html.twig` |
| `order_confirmation_resent` | `S:src/Sylius/Bundle/AdminBundle/Resources/config/app/sylius/sylius_mailer.yml` L6-8 | `@SyliusCore/Email/orderConfirmation.html.twig` |

The ShopBundle definition wins because the app imports `@SyliusShopBundle/Resources/config/app/config.yml` after `@SyliusCoreBundle/...` (see the skeleton's `tests/Application/config/packages/_sylius.yaml`). Code constants are in `S:src/Sylius/Bundle/CoreBundle/Mailer/Emails.php`.

### Templates

- `S:src/Sylius/Bundle/ShopBundle/templates/email/order_confirmation.html.twig` is one line: `{% extends '@SyliusCore/Email/orderConfirmation.html.twig' %}`.
- `S:src/Sylius/Bundle/CoreBundle/Resources/views/Email/orderConfirmation.html.twig` extends `@SyliusCore/Email/layout.html.twig`. It fills `subject` and `content` by including `Blocks/OrderConfirmation/_subject.html.twig` and `Blocks/OrderConfirmation/_content.html.twig`.
- `S:src/Sylius/Bundle/CoreBundle/Resources/views/Email/layout.html.twig` defines `{% block body %}` (wrapped in `{% autoescape false %}`) with an inner `{% block content %}`. It builds the logo and homepage URLs with `sylius_channel_url(..., channel)` and `'_locale': localeCode`.
- `S:src/Sylius/Bundle/CoreBundle/Resources/views/Email/Blocks/OrderConfirmation/_content.html.twig` holds the order number, a "view order" button, and a thank-you line. Every string is translated with `|trans({}, null, localeCode)`. The button URL is `sylius_channel_url(path('sylius_shop_order_show', {'tokenValue': order.tokenValue, '_locale': localeCode}), channel)` (L12), the pattern to copy for absolute links in an email.
- **No Twig hooks.** `grep hook` over `CoreBundle/Resources/views/Email`, `ShopBundle/templates/email` and `AdminBundle/templates/email` finds nothing. No `twig_hooks/*.yaml` in Shop or Admin declares an email hook. The same templates are byte-identical at `v2.0.0`, `v2.1.0`, `v2.2.0` and on the `2.4` branch (only the logo path in `layout.html.twig` changed after 2.0.0).

### Sender service

`S:src/Sylius/Bundle/CoreBundle/Mailer/OrderEmailManager.php`:

```php
public function sendConfirmationEmail(OrderInterface $order): void   // L27
{
    $this->emailSender->send(Emails::ORDER_CONFIRMATION, [$email], [
        'order' => $order,
        'channel' => $order->getChannel(),
        'localeCode' => $order->getLocaleCode(),
    ]);
}
public function resendConfirmationEmail(OrderInterface $order): void // L45, same data, code ORDER_CONFIRMATION_RESENT
```

The same class is registered **twice** with different ids:

| Service id | Where | Used by |
| --- | --- | --- |
| `sylius.mailer.order_email_manager` (alias `OrderEmailManagerInterface`) | `S:src/Sylius/Bundle/CoreBundle/Resources/config/services/mailer.php` L54-58 | API checkout, admin resend |
| `sylius_shop.mailer.order_email_manager` (no interface alias) | `S:src/Sylius/Bundle/ShopBundle/Resources/config/services/email.php` L28-30 | shop checkout |

Both get `service('sylius.email_sender')`.

### What triggers it

1. **Shop checkout.** Route `sylius_shop_checkout_complete` (`S:src/Sylius/Bundle/ShopBundle/Resources/config/routing/checkout.yml` L68-89) calls `sylius.controller.order::updateAction` with `event: complete`, `state_machine: {graph: sylius_order_checkout, transition: complete}` and form `CompleteType`. In `SyliusResourceBundle v1.14.2` `src/Bundle/Controller/ResourceController.php::updateAction`, the sequence is:
   - dispatch `sylius.order.pre_complete` (event name built in `src/Bundle/Controller/EventDispatcher.php`)
   - `ResourceUpdateHandler::handle()`, which applies the transition and then calls `$manager->flush()`
   - dispatch `sylius.order.post_complete`

   `sylius_shop.listener.order_complete` (`OrderCompleteListener`) listens to `sylius.order.post_complete` and calls `sylius_shop.mailer.order_email_manager` (`S:src/Sylius/Bundle/ShopBundle/Resources/config/services/listeners.php` L75-79).
2. **API checkout.** `S:src/Sylius/Bundle/ApiBundle/CommandHandler/Checkout/CompleteOrderHandler.php` L83 dispatches `OrderCompleted` on `sylius.event_bus` with `DispatchAfterCurrentBusStamp`. `ApiBundle/EventHandler/OrderCompletedHandler.php` turns it into `SendOrderConfirmation`, and `ApiBundle/CommandHandler/Checkout/SendOrderConfirmationHandler.php` calls `OrderEmailManagerInterface::sendConfirmationEmail()`.
3. **Admin resend.** `AdminBundle/Action/ResendOrderConfirmationEmailAction` dispatches `ResendOrderConfirmationEmail`, and `S:src/Sylius/Bundle/CoreBundle/CommandHandler/ResendOrderConfirmationEmailHandler.php` calls `resendConfirmationEmail()`. This is allowed only for order states `new` and `fulfilled` (`S:src/Sylius/Bundle/OrderBundle/Resources/config/app/config.yml` L4-7), so checkout is always completed.

Sylius routes none of these messages to an async transport (`S:src/Sylius/Bundle/CoreBundle/Resources/config/app/messenger.yaml`). Sylius-Standard `2.3` ships only a `sync://` transport. The email is sent inside the request unless the app reroutes it.

### How SyliusMailerBundle sends

`M:src/Component/Sender/Sender.php::send()`:

1. `EmailProvider::getEmail($code)` builds a **new** `Email` model from config on every call (`M:src/Component/Provider/EmailProvider.php` L38-47).
2. `EmailModifierInterface::modify($email, $data)` (L53). This is a `CompositeEmailModifier` over services tagged `sylius_mailer.email_modifier` (`M:src/Bundle/Resources/config/services/modifier.php` L25). Sylius core registers none, and the tag is not autoconfigured.
3. If `!$email->isEnabled()`, it returns early (L56).
4. `EmailTwigAdapter::render()` merges Twig globals into `$data` and renders **only** the `subject` and `body` blocks of the template (`M:src/Bundle/Renderer/Adapter/EmailTwigAdapter.php` L62-67). It then dispatches `sylius.email_rendered` (`EMAIL_PRE_RENDER`) with an `EmailRenderEvent` that carries only the `RenderedEmail` (subject and body), with no code and no data.
5. `SymfonyMailerAdapter` (`M:src/Bundle/Sender/Adapter/SymfonyMailerAdapter.php`) builds `Symfony\Component\Mime\Email` with an HTML body only, calls `attachFromPath()` for each attachment (L110), and dispatches `sylius.email_send.pre_send` with `EmailSendEvent($message, $email, $data, $recipients, $replyTo)` (L113-115). It then calls `MailerInterface::send()` and dispatches `sylius.email_send.post_send`. Sylius selects this adapter with `sender_adapter: sylius.email_sender.adapter.symfony_mailer` (Core `sylius_mailer.yml` L8).

## 2. Ways a plugin can add content

| # | Mechanism | Reaches shop / API / resend | Can do | Caveats | Source |
| --- | --- | --- | --- | --- | --- |
| A | **The app overrides** `templates/bundles/SyliusCoreBundle/Email/Blocks/OrderConfirmation/_content.html.twig` (or `orderConfirmation.html.twig`) | all three, because every path ends in this include | anything in Twig | Only the **app** can do this; it is a documentation step for the plugin, not something the plugin ships | Symfony bundle-override path `templates/bundles/<Bundle>/` (`symfony/twig-bundle` `DependencyInjection/TwigExtension.php::getBundleTemplatePaths`) |
| B | **Re-point `sylius_mailer.emails.order_confirmation.template`** (and `order_confirmation_resent`) to a plugin template that extends the Sylius one and adds to `{% block content %}` | all three if both codes are re-pointed | anything in Twig | Works from **app** config. From the plugin's `prepend()` it does **not** work: `prependExtensionConfig()` does `array_unshift` (`symfony/dependency-injection` `ContainerBuilder.php` L758-765), so Sylius's own `sylius_mailer.yml` files, which the app imports, merge later and win on the scalar `template` key (inferred from Symfony config merge rules; not run). A compiler pass that rewrites the `sylius.mailer.emails` parameter is the remaining plugin-side option (not verified). | `M:src/Bundle/DependencyInjection/Configuration.php`, `SyliusMailerExtension.php` |
| C | **Email modifier** (`EmailModifierInterface`, tag `sylius_mailer.email_modifier`) | all three | per send, look at `$email->getCode()` and `$data` (order) and change `template`, `subject`, sender, or `enabled` | Cannot add render data, because `$data` is passed by value. It replaces whatever template the app configured, so a plugin template that `extends` a fixed Sylius template would bypass an app's custom template. | `M:src/Component/Modifier/*`, `Sender.php` L53 |
| D | **Decorate `sylius.email_sender`** (`SenderInterface`) | all three, since every manager uses this service | for codes `order_confirmation` and `order_confirmation_resent`: add keys to `$data` (e.g. a precomputed Terms list) and append file paths to `$attachments` | `Sender::send()` reads CC/BCC as **undeclared** 6th and 7th args via `func_get_args()` (`Sender.php` L47, L65-80). A decorator must forward `...func_get_args()` or it drops CC/BCC. | `M:src/Component/Sender/*` |
| E | **Decorate `OrderEmailManager`** | API and resend only, unless **both** `sylius.mailer.order_email_manager` and `sylius_shop.mailer.order_email_manager` are decorated | add data and attachments | Decorating `OrderEmailManagerInterface` alone misses the shop checkout | Core `mailer.php` L54-58, Shop `email.php` L28-30 |
| F | **Listen to `sylius.email_send.pre_send`** (`SyliusMailerEvents::EMAIL_PRE_SEND`) | all three | `getEmail()->getCode()` gives the code, `getData()['order']` the order, and `getMessage()` the mutable `Symfony\Component\Mime\Email`: `->attach($pdfBytes, 'terms.pdf', 'application/pdf')` with **in-memory** content, or rewrite `->html()` | `getMessage()` is typed `mixed`; it is a Symfony `Email` only with the symfony_mailer adapter, so check `instanceof`. Runs after rendering, so it is not a good place for HTML content. | `M:src/Bundle/Sender/Adapter/SymfonyMailerAdapter.php` L113-115, `M:src/Component/Event/EmailSendEvent.php` |
| G | `sylius.email_rendered` (`EMAIL_PRE_RENDER`) | all | replace the subject or body string | The event has no email code and no data, so it cannot tell which email or order it is | `M:src/Component/Event/EmailRenderEvent.php`, `EmailTwigAdapter.php` L42 |
| H | Twig hooks | none | none | No hookpoints exist in any email template. Adding one needs A or B anyway. | section 1 |
| I | Plugin prepends `twig.paths` with namespace `SyliusCore` | all | shadow `_content.html.twig` | User-configured `twig.paths` are added **before** bundle paths, including the app's `templates/bundles/SyliusCoreBundle` override (`TwigExtension.php` L126-154). A plugin doing this would silently override the app's own override. Listed as a hazard, not an option. | `symfony/twig-bundle` `v7.4.21` |

### Does the 2.x `SenderInterface` support attachments?

Yes. `M:src/Component/Sender/SenderInterface.php`:

```php
/** @param string[] $attachments A list of file paths to attach to the message. */
public function send(string $code, array $recipients, array $data = [], array $attachments = [], array $replyTo = []): void;
```

- Attachments are **file paths only** through this interface. `SymfonyMailerAdapter` calls `$message->attachFromPath($attachment)` for each one.
- In `symfony/mime` `v7.4.22`, `attachFromPath()` wraps the path in `Part\File`. `TextPart`'s constructor doc says *"Use a File instance to defer loading the file until rendering"*. If the app routes Symfony Mailer's `SendEmailMessage` to an async transport, the file must still exist when the worker sends the message.
- In-memory content (no temp file) is possible only on the Symfony `Email` itself, through mechanism F (`Email::attach($body, $name, $contentType)`).
- Precedent: Sylius InvoicingPlugin `v2.3.0` `src/Email/InvoiceEmailSender.php` stores the invoice PDF and passes its full path as the 4th argument to `SenderInterface::send()`. That is a separate email, not the order confirmation.

## 3. Rendering context, and getting channel, locale and completion time

### What the template sees

- Data from `OrderEmailManager`: `order` (`Sylius\Component\Core\Model\OrderInterface`), `channel` (`$order->getChannel()`), `localeCode` (`$order->getLocaleCode()`).
- Merged Twig globals (`EmailTwigAdapter.php` L62), for example:
  - `sylius` = `@sylius.context.shopper` (`S:src/Sylius/Bundle/CoreBundle/Resources/config/app/config.yml` L109)
  - `sylius_base_locale`
  - `sylius_meta`
  - `sylius_order_states_that_allows_to_resend_order_confirmation_email`

  The `sylius` shopper context resolves channel and locale from the current request. On admin resend that is the admin request, and in API, CLI or worker contexts there may be no request at all. Email content must use `order`, `channel` and `localeCode`, not `sylius.*`.
- Only `{% block subject %}` and `{% block body %}` are rendered. Content added by a plugin template must sit inside `body` (in practice, `content`).

### Channel

`$order->getChannel()`. The shop sets it when the cart is created (`S:src/Sylius/Component/Core/Cart/Context/ShopBasedCartContext.php`). The API sets it through `OrderFactory::createNewCart()` (`S:src/Sylius/Bundle/CoreBundle/Factory/OrderFactory.php` L51).

### Locale

`$order->getLocaleCode()`:

- Shop: the cart gets the shopper context locale at creation, and `sylius_shop.listener.order_locale_assigner` (`OrderLocaleAssigner`) **overwrites** it with `sylius.context.locale` on `sylius.order.pre_complete` (`S:src/Sylius/Bundle/ShopBundle/Resources/config/services/listeners.php` L105-109). So it is the locale the customer completed checkout in.
- API: set at cart pickup from the request's `localeCode`, or the channel default (`S:src/Sylius/Bundle/ApiBundle/CommandHandler/Cart/PickupCartHandler.php` L78, L98-110).

Translatable entities loaded by Doctrine get their *current* locale from the request locale context, or from the default locale when running from the CLI (`S:src/Sylius/Component/Core/Translation/TranslatableEntityLocaleAssigner.php`). In the email, Terms text must be read with `getTranslation($order->getLocaleCode())`, not through current-locale getters. This matters most on admin resend.

### Checkout completion time

`$order->getCheckoutCompletedAt(): ?\DateTimeInterface` (`S:src/Sylius/Component/Order/Model/OrderInterface.php` L34). `Order::completeCheckout()` sets it to `new \DateTime()` (`S:src/Sylius/Component/Order/Model/Order.php` L86-89), in PHP's default timezone. Two setters exist, depending on the state machine adapter:

- Symfony Workflow: `sylius.listener.workflow.order_checkout.save_checkout_completion_date` on `workflow.sylius_order_checkout.completed.complete`, priority 300 (`S:src/Sylius/Bundle/CoreBundle/Resources/config/services/listeners/workflow/order_checkout.php` L44-46, class `CoreBundle/EventListener/Workflow/OrderCheckout/SaveCheckoutCompletionDateListener.php`).
- winzou: callback `sylius_save_checkout_completion_date` (`S:src/Sylius/Bundle/CoreBundle/Resources/config/app/winzou_state_machine/sylius_order_checkout.yml` L52-54).

In all three send paths, the timestamp is set, and flushed, before the email is rendered:

- shop: set during the transition, before `post_complete`
- API: set in the command, and the email goes out after the bus via `DispatchAfterCurrentBusStamp`
- resend: only for `new` or `fulfilled` orders

For ADR 0001, the inputs (channel, locale, at time) are therefore `$order->getChannel()`, `$order->getLocaleCode()` and `$order->getCheckoutCompletedAt()`, all available on the `order` in the email data.

### Links to Terms pages from an email

- Copy the core pattern: `sylius_channel_url(path(<route>, {..., '_locale': localeCode}), channel)`.
- `S:src/Sylius/Bundle/CoreBundle/Twig/ChannelUrlExtension.php::generateChannelUrl()` returns:
  - `https://<channel hostname><path>` when the channel has a hostname
  - otherwise an absolute URL from the request **only** if `framework.trusted_hosts` is set
  - otherwise **the bare path**

  The last fallback changed in the security releases **2.2.12** and **2.3.2** (`S:UPGRADE-2.2.md`, `S:UPGRADE-2.3.md` "Host header injection in the e-mail links of a channel without a hostname"). Emails rendered without a request, such as in a worker or CLI, need the channel hostname set.
- The current plugin's `LabelRenderer` (`src/Renderer/LabelRenderer.php`, branch `2.x`) calls `UrlGeneratorInterface::generate('setono_sylius_terms_shop_show_terms', ['slug' => ...])`. That gives a relative path, with no `_locale` and no channel host, so it cannot be reused as-is for an email. The shop route is under `/{_locale}` (`src/Resources/config/routes.yaml`).
- The current plugin's `TermsProvider::getTerms($form)` reads channel and locale from `ChannelContextInterface` and `LocaleContextInterface`, which are request-bound. The repository method `findByFormAndChannelAndLocale(string $form, ChannelInterface $channel, string $locale)` already takes them explicitly. An email-side lookup needs that explicit form plus a point in time (for Terms versions).

## 4. PDF tooling used with Sylius 2 plugins (list only, no decision)

Packagist figures fetched 2026-11-16.

| Package | Engine | Notes |
| --- | --- | --- |
| [`sylius/pdf-generation-bundle`](https://github.com/Sylius/PdfGenerationBundle) `v1.0.0` (2026-04-16) | abstraction | Sylius-maintained. `HtmlToPdfRendererInterface` with adapters `knp_snappy`, `dompdf`, `gotenberg`; per-context adapter and storage (filesystem, Flysystem, Gaufrette). Required by Sylius InvoicingPlugin `v2.3.0` and RefundPlugin `v2.2.1`. About 40k downloads a month. |
| [`knplabs/knp-snappy-bundle`](https://github.com/KnpLabs/KnpSnappyBundle) `v1.10.6` | wkhtmltopdf binary | Required by Sylius InvoicingPlugin `v2.3.0` and RefundPlugin `v2.2.1`. The upstream `wkhtmltopdf/wkhtmltopdf` repository is **archived**, with its last push on 2022-11-22. About 527k downloads a month. |
| [`dompdf/dompdf`](https://github.com/dompdf/dompdf) `v3.1.6` | pure PHP | No binary. Limited CSS. About 13.9M downloads a month. |
| [`mpdf/mpdf`](https://github.com/mpdf/mpdf) `v8.3.1` | pure PHP | About 4.2M downloads a month. |
| [`tecnickcom/tcpdf`](https://github.com/tecnickcom/TCPDF) `7.0.24` | pure PHP | About 4.4M downloads a month. |
| [`gotenberg/gotenberg-php`](https://github.com/gotenberg/gotenberg-php) `v3.4.0` / [`sensiolabs/gotenberg-bundle`](https://github.com/sensiolabs/GotenbergBundle) `v1.4.0` | Gotenberg (headless Chromium in Docker) | Needs a running Gotenberg service. About 628k and 126k downloads a month. |
| [`spatie/browsershot`](https://github.com/spatie/browsershot) `5.5.0` | Puppeteer / Chromium | Needs Node.js and Chromium. About 2.9M downloads a month. |

## 5. Lowest Sylius 2 minor for each API

The files were compared at `v2.0.0`, `v2.1.0`, `v2.2.0` and `v2.3.2`. Until 2.2 the service definitions were XML (`*.xml`); in 2.3 they are PHP (`*.php`). The ids, classes, arguments and tags are the same.

| API | Exists unchanged since | Notes |
| --- | --- | --- |
| Email codes `order_confirmation`, `order_confirmation_resent` and their templates | 2.0.0 | the three `sylius_mailer.yml` files and `Emails.php` are identical |
| `OrderEmailManager` data keys `order`, `channel`, `localeCode` | 2.0.0 | file identical |
| `sylius_shop.mailer.order_email_manager` + `OrderCompleteListener` on `sylius.order.post_complete` | 2.0.0 | `ShopBundle/Resources/config/services/{email,listeners}.xml` |
| `OrderLocaleAssigner` on `sylius.order.pre_complete` | 2.0.0 | |
| `SaveCheckoutCompletionDateListener` (workflow, priority 300) and the winzou callback | 2.0.0 | `CoreBundle/Resources/config/services/listeners/workflow/order_checkout.xml` |
| API `SendOrderConfirmationHandler`; admin `ResendOrderConfirmationEmailHandler` | 2.0.0 | files identical |
| `_content.html.twig`, `orderConfirmation.html.twig`, `@SyliusShop/email/order_confirmation.html.twig` | 2.0.0 | identical; only the logo path in `layout.html.twig` changed after 2.0.0 |
| `sylius_channel_url` | 2.0.0 | the "relative path when no hostname and no trusted_hosts" behaviour applies from **2.2.12** and **2.3.2** |
| `SenderInterface::send(..., $attachments, $replyTo)`, `attachFromPath`, `sylius.email_send.pre_send` with the Symfony `Email`, `sylius_mailer.email_modifier` | mailer-bundle **2.1.0** | Sylius 2.0.0 to 2.2.x require `sylius/mailer-bundle ^2.1`, and 2.3 requires `^2.2`. Between `v2.1.0` and `v2.2.1` only docblocks and types changed in these files, plus a `trim()` of the subject. |

Result: the email area does not need anything newer than **Sylius 2.0**, so the floor comes from other tickets. The skeleton's `sylius/core-bundle ^2.2` covers everything here.

## 6. Playbook errata candidates

The playbook (`sylius-v1-to-v2-upgrade-playbook.md`, skeleton `2.2.x`) says nothing about emails. Two of its general claims are wrong or incomplete when applied to emails:

1. **§7 "Templates: rebuild around Sylius Twig hooks"**
   - Claim: "Sylius 2.x replaced direct template overriding with Twig hooks", and template overrides should be ported to hook templates.
   - What is true: email templates have **no** hookpoints in Sylius 2.0.0 to 2.3.2 or on the `2.4` branch. For emails, the only extension points are template override, the mailer `template` config, email modifiers, `SenderInterface` decoration, and the mailer events.
   - Evidence: `S:src/Sylius/Bundle/CoreBundle/Resources/views/Email/*.html.twig`, `S:src/Sylius/Bundle/ShopBundle/templates/email/*.html.twig` and `S:src/Sylius/Bundle/AdminBundle/templates/email/*.html.twig` contain no `hook`, and no `twig_hooks/*.yaml` declares an email hook.
2. **§5 "Bundle prepending: ship config from `prepend()`"**
   - Claim: configure other bundles from `prepend()` with `prependExtensionConfig()`.
   - What is true: prepended config has the **lowest** precedence (`symfony/dependency-injection` `ContainerBuilder::prependExtensionConfig()` does `array_unshift`). It cannot **override** values that Sylius sets in its own `Resources/config/app/*.yml`, which apps import (skeleton `tests/Application/config/packages/_sylius.yaml`). Example: `sylius_mailer.emails.order_confirmation.template`, set by `S:src/Sylius/Bundle/ShopBundle/Resources/config/app/sylius/sylius_mailer.yml` L9-11. It works for **adding** new keys (grids, hookables, new emails).
   - Status: inferred from Symfony merge semantics, not run.

## 7. Side findings that may affect other tickets

- **PHP and Symfony floor per minor.** `sylius/sylius` requires `php ^8.2` up to 2.2.x, and `php ^8.3` from **2.3.0**, which also allows Symfony `^8.0` (`S:composer.json`). With the map's PHP ≥ 8.2, a PHP 8.2 CI job can only resolve Sylius 2.2.x. This is relevant to the "version floor and CI matrix" item.
- **Checkout complete route.** At `v2.0.0` and `v2.1.0`, `sylius_shop_checkout_complete` passes `form.options.validation_groups: 'sylius_checkout_complete'`. From `v2.2.0` this option is gone (`ShopBundle/Resources/config/routing/checkout.yml`). Not investigated further. The research ticket on enforcing required Terms on `CompleteType` should check how validation groups resolve per minor.
- **Shop email manager id.** Sylius has no interface alias for `sylius_shop.mailer.order_email_manager`. Any "decorate the order email manager" design must name both ids.
