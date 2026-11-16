# Sylius terms and conditions plugin

[![Latest Version][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Will add a checkbox to the selects form(s) where the customer has to accept the terms and conditions before continuing.

This can be used to make sure the customer has accepted the terms and conditions before placing an order.

* [Screenshots](#screenshots)
* [Forms](#forms)
* [Required and optional terms, and position](#required-and-optional-terms-and-position)
* [Finding and checking the Terms on a Form in code](#finding-and-checking-the-terms-on-a-form-in-code)
* [Terms versions](#terms-versions)
* [Installation](#installation)

## Screenshots

### Shop

Before the customer can place order, he/she has to check the required terms

![Screenshot showing shop checkout complete page](docs/images/shop-checkout-complete.png)

### Admin

Here is a list of terms. Notice the `terms_and_conditions` which is associated with multiple channels.

![Screenshot showing admin terms index page](docs/images/admin-terms-index.png)

Notice that you select the forms where the terms are shown. By default the checkout complete, registration and contact
forms are available, and you can add more in the `setono_sylius_terms.forms` configuration option (see [Forms](#forms)).

![Screenshot showing admin terms update page](docs/images/admin-terms-update.png)

The `Label` field is the text shown on the complete order page. Notice you can use a placeholder (`[link:Link text]`) to tell where the link should be.

![Screenshot showing admin terms translation update page](docs/images/admin-terms-update-translation.png)

## Forms

A Form is a shop form that Terms can be placed on. Each Form is configured under a short code, and a Terms stores the
codes of the Forms it is placed on. Three Forms are available out of the box:

| Code                | Form type                                                     | Hook                                               |
|---------------------|---------------------------------------------------------------|----------------------------------------------------|
| `checkout_complete` | `Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType`    | `sylius_shop.checkout.complete.content.form`       |
| `registration`      | `Sylius\Bundle\ShopBundle\Form\Type\CustomerRegistrationType` | `sylius_shop.account.register.content.form`        |
| `contact`           | `Sylius\Bundle\CoreBundle\Form\Type\ContactType`              | `sylius_shop.contact.contact_request.content.form` |

Each is hooked at priority 50, just before the submit button. The admin picks which Terms appear on each Form.

### Adding a Form

Your entries add to the three defaults:

```yaml
# config/packages/setono_sylius_terms.yaml
setono_sylius_terms:
    forms:
        newsletter:
            type: App\Form\Type\NewsletterType           # required: the form type the Terms checkboxes are added to
            label: app.form.newsletter                     # optional: what the admin shows for the Form
            hooks: { app.newsletter.form: 50 }             # the Twig hooks the checkboxes render in, with their priority
            saved_event: app.newsletter.post_subscribe     # optional: the event dispatched once the form's data is saved
```

* Set `hooks: []` to render the checkboxes yourself with `{{ form_row(form.terms) }}`.
* Without a `label`, the admin shows the translation key `setono_sylius_terms.form.terms.term_form.<code>`.

### Changing or switching off a default Form

An entry with a default Form's code changes only the keys it sets, and `enabled: false` switches a default Form off:

```yaml
setono_sylius_terms:
    forms:
        checkout_complete:
            hooks: { app.checkout.complete.terms: 10 } # keeps its type and saved event
        contact:
            enabled: false
```

## Required and optional terms, and position

* **Required terms** must be ticked before the form they are on can be submitted. **Optional terms** may be left
  unticked. A Terms is required by default, and the flag applies on every Form it is placed on: a Terms that must be
  required on one Form and optional on another becomes two Terms.
* **Position** orders the Terms on every Form, lowest first. A Terms saved without a position goes last. Setting a
  position moves the Terms there and shifts the Terms from there on one place down. Changing the position or the
  required flag doesn't create a [Terms version](#terms-versions).

## Finding and checking the Terms on a Form in code

Both services work without a request or a Symfony form, so you can use them from a command, an email or an API.

The Form lookup returns the Terms version in force for each enabled Terms placed on a Form in a channel, read in a
locale, in position order. It skips a Terms that has no version in force at that moment, or no translation in that
locale:

```php
/** @var \Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface $formTermsLookup */
$translations = $formTermsLookup->lookup('checkout_complete', $order->getChannel(), $order->getLocaleCode(), $order->getCheckoutCompletedAt());

foreach ($translations as $translation) {
    $translation->getVersion()->getTerms()->getCode(); // terms_and_conditions
    $translation->getVersion()->getNumber();           // 3
    $translation->getLabel();                          // the label of that version, in the order's locale
}
```

The moment defaults to now. The placement (enabled, channels and Forms) is always the current one.

The required terms checker returns the required Terms on a Form that the customer left unticked, given the codes of
the ticked Terms:

```php
/** @var \Setono\SyliusTermsPlugin\Checker\RequiredTermsCheckerInterface $requiredTermsChecker */
$unticked = $requiredTermsChecker->getUntickedRequiredTerms('checkout_complete', $channel, 'en_US', ['terms_and_conditions']);
```

The `RequiredTermsTicked` constraint wraps the checker for the current channel and locale, so a DTO can carry it. The
value is the list of the ticked Terms' codes, and each unticked required Terms raises a violation at `[<Terms code>]`:

```php
use Setono\SyliusTermsPlugin\Validator\Constraints\RequiredTermsTicked;

final class CompleteCheckoutCommand
{
    /** @var list<string> */
    #[RequiredTermsTicked(form: 'checkout_complete')]
    public array $terms = [];
}
```

## Terms versions

Every Terms keeps a history of versions. A version is a numbered snapshot of the Terms' name, label and content in
every locale, for example "Version 3, in force since 16 November 2026". Versions can't be edited: a flush that changes
a saved version throws a `LogicException`.

* Every save that changes the name, label or content in any locale, or adds or removes a translation, creates the
  next version. Creating a Terms creates version 1. This holds for every save, whether it comes from the admin,
  the fixture or your own code.
* The slug, code, channels, Forms, position and the enabled and required flags aren't versioned. Changing only those
  creates no version.
* A version is in force from the moment it is saved until the next version is saved. The plugin reads "now" from
  Symfony Clock.

To find out which version of a Terms was in force at a given moment in a given locale, for example what a customer
accepted when their checkout completed, ask the Terms version repository:

```php
/** @var \Setono\SyliusTermsPlugin\Repository\TermsVersionRepositoryInterface $termsVersionRepository */
$termsVersionRepository = $container->get('setono_sylius_terms.repository.terms_version');

$translation = $termsVersionRepository->findOneInForce($terms, $order->getCheckoutCompletedAt(), $order->getLocaleCode());

$translation?->getVersion()->getNumber(); // 3
$translation?->getContent();              // the content in the order's locale, as entered
```

It returns `null` when the moment is before the Terms' first version, or when the version in force has no translation
in that locale. There is no fallback to another locale.

### Limitations

* **Placement isn't versioned.** The lookup can name a version for an order whose checkout never showed that Terms,
  for example a Terms added to checkout after the order. Callers judge relevance from the current placement.
* **Mid-checkout race.** If an admin saves a new version between a customer loading checkout complete and submitting
  it, the order is inferred to have accepted the new version. Forms carry no version check.
* **Deleting a Terms deletes its versions.** To retire a Terms and keep its history, disable it instead.

## Installation

The plugin requires PHP 8.2 or later, Symfony 6.4 or 7.4 and Sylius 2.2 or later. For Sylius 1, use the `2.x` versions of the plugin.
When upgrading from 2.x, read [UPGRADE.md](UPGRADE.md).

### Step 1: Download the plugin

```bash
composer require setono/sylius-terms-plugin
```

### Step 2: Enable the plugin

Then, enable the plugin by adding it to the list of registered plugins/bundles
in the `config/bundles.php` file of your project:

```php
<?php
# config/bundles.php
return [
    // ...
    
    Setono\SyliusTermsPlugin\SetonoSyliusTermsPlugin::class => ['all' => true],
    
    // It is important to add plugin before the grid bundle
    Sylius\Bundle\GridBundle\SyliusGridBundle::class => ['all' => true],
    
    // ...
];
```

**NOTE** that you must instantiate the plugin before the grid bundle, else you will see an exception like `You have requested a non-existent parameter "setono_sylius_terms.model.terms.class".`

### Step 3: Import routing

```yaml
# config/routes/setono_sylius_terms.yaml
setono_sylius_terms:
    resource: "@SetonoSyliusTermsPlugin/config/routes.yaml"
```

If your store's URLs have no locale prefix, import the non-localised version instead:

```yaml
# config/routes/setono_sylius_terms.yaml
setono_sylius_terms:
    resource: "@SetonoSyliusTermsPlugin/config/routes_no_locale.yaml"
```

Both mount the admin pages under `/%sylius_admin.path_name%` and the Terms page at `/{_locale}/terms/{slug}` and
`/terms/{slug}` respectively. To use your own prefixes, import `@SetonoSyliusTermsPlugin/config/routes/shop.yaml` and
`@SetonoSyliusTermsPlugin/config/routes/admin.yaml` separately.

The `terms` part of the Terms page path is configurable:

```yaml
# config/packages/setono_sylius_terms.yaml
setono_sylius_terms:
    routing:
        terms: vilkaar # the Terms page is now at /{_locale}/vilkaar/{slug}
```

### Step 4: Update your database schema

```bash
$ php bin/console doctrine:migrations:diff
$ php bin/console doctrine:migrations:migrate
```

### Step 5: Override checkout complete form

Override the [Sylius Form](https://github.com/Sylius/Sylius/blob/master/src/Sylius/Bundle/ShopBundle/Resources/views/Checkout/Complete/_form.html.twig):

* If you haven't your own `templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig` yet:

    ```bash
    $ cp vendor/sylius/sylius/src/Sylius/Bundle/ShopBundle/Resources/views/Checkout/Complete/_form.html.twig \
    templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig
    ```

* If you already have it:

    Add terms field (exactly this conditional way):

    ```twig
    {# templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig #}
    {% if form.terms is defined %}
        {{ form_row(form.terms) }}
    {% endif %}
    ```

    So the final template will look like this:

    ```twig
    {# templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig #}
    {{ form_row(form.notes, {'attr': {'rows': 3}}) }}
    {% if form.terms is defined %}
        {{ form_row(form.terms) }}
    {% endif %}
    ```

# Troubleshooting

* If you see `Neither the property "terms" nor one of the methods "terms()", "getterms()"/"isterms()"/"hasterms()" or "__call()" exist and have public access in class "Symfony\Component\Form\FormView".`

    Then see https://github.com/Setono/SyliusTermsPlugin/issues/13
    and double-check you added terms field at template like described
    at `Override checkout complete form` section.
    
* If you see `Grid "setono_sylius_terms_terms" does not exists`

    Then you forgot to import config from `Step 3: Import config` section.

[ico-version]: https://poser.pugx.org/setono/sylius-terms-plugin/v/stable
[ico-license]: https://poser.pugx.org/setono/sylius-terms-plugin/license
[ico-github-actions]: https://github.com/Setono/SyliusTermsPlugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusTermsPlugin/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-terms-plugin
[link-github-actions]: https://github.com/Setono/SyliusTermsPlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusTermsPlugin
