# Sylius terms and conditions plugin

[![Latest Version][ico-version]][link-packagist]
[![Latest Unstable Version][ico-unstable-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-github-actions]][link-github-actions]
[![Code Coverage][ico-code-coverage]][link-code-coverage]

Will add the requirement to check off terms and conditions when the customer checks out

* [Screenshots](#screenshots)
* [Installation](#installation)

## Screenshots

### Shop

Before the customer can place order, he/she has to check the required terms

![Screenshot showing shop checkout complete page](docs/images/shop-checkout-complete.png)

### Admin

Here is a list of terms. Notice the `terms_and_conditions` which is associated with multiple channels.

![Screenshot showing admin terms index page](docs/images/admin-terms-index.png)

![Screenshot showing admin terms update page](docs/images/admin-terms-update.png)

The `Explanation` field is the text shown on the complete order page. Notice you can use a placeholder (`[link:Link text]`) to tell where the link should be.

![Screenshot showing admin terms translation update page](docs/images/admin-terms-update-translation.png)

## Installation

### Step 1: Download the plugin

Open a command console, enter your project directory and execute the following command to download the latest stable version of this plugin:

```bash
$ composer require setono/sylius-terms-plugin
```

This command requires you to have Composer installed globally, as explained in the [installation chapter](https://getcomposer.org/doc/00-intro.md) of the Composer documentation.


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

### Step 3: Import config
```yaml
# config/packages/_sylius.yaml
imports:
    # ...
    
    - { resource: "@SetonoSyliusTermsPlugin/Resources/config/app/config.yaml" }
    
    # ...
```

### Step 4: Import routing

```yaml
# config/routes/setono_sylius_terms.yaml

setono_sylius_terms_shop:
    resource: "@SetonoSyliusTermsPlugin/Resources/config/shop_routing.yaml"
    prefix: /{_locale}
    requirements:
        _locale: ^[a-z]{2}(?:_[A-Z]{2})?$

setono_sylius_terms_admin:
    resource: "@SetonoSyliusTermsPlugin/Resources/config/admin_routing.yaml"
    prefix: /admin
```

### Step 5: Update your database schema

```bash
$ php bin/console doctrine:migrations:diff
$ php bin/console doctrine:migrations:migrate
```

### Step 6: Override checkout complete form

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
        {% form_theme form.terms '@SetonoSyliusTermsPlugin/Shop/Form/termsTheme.html.twig' %}
        {{ form_row(form.terms) }}
    {% endif %}
    ```
    
    So the final template will look like this:

    ```twig
    {# templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig #}
    {{ form_row(form.notes, {'attr': {'rows': 3}}) }}
    {% if form.terms is defined %}
        {% form_theme form.terms '@SetonoSyliusTermsPlugin/Shop/Form/termsTheme.html.twig' %}
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

* If you see `Uncaught ReferenceError: $ is not defined` in your js console

    This means `jQuery` was loaded after plugin's javascript code.
    Plugin's javascript code injecting into main template via `sylius.shop.layout.javascripts`
    Sonata block. So check your custom `templates/bundles/SyliusShopBundle/layout.html.twig`
    it's `javascript` block should be like this:
    
    ```twig
    {# layout.html.twig #}
    
    {% block javascripts %}
        // We expect jquery to be loaded here
        {% include '@SyliusUi/_javascripts.html.twig' with {'path': 'assets/shop/js/app.js'} %}
    
        // But if you have it as separate script - just make sure
        // it placed before `sylius.shop.layout.javascripts` sonata block
    
        {{ sonata_block_render_event('sylius.shop.layout.javascripts') }}
    {% endblock %}
    ```

[ico-version]: https://poser.pugx.org/setono/sylius-terms-plugin/v/stable
[ico-unstable-version]: https://poser.pugx.org/setono/sylius-terms-plugin/v/unstable
[ico-license]: https://poser.pugx.org/setono/sylius-terms-plugin/license
[ico-github-actions]: https://github.com/Setono/SyliusTermsPlugin/workflows/build/badge.svg
[ico-code-coverage]: https://codecov.io/gh/Setono/SyliusTermsPlugin/branch/master/graph/badge.svg

[link-packagist]: https://packagist.org/packages/setono/sylius-terms-plugin
[link-github-actions]: https://github.com/Setono/SyliusTermsPlugin/actions
[link-code-coverage]: https://codecov.io/gh/Setono/SyliusTermsPlugin
