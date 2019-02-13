<p align="center">
    <a href="https://sylius.com" target="_blank">
        <img src="https://demo.sylius.com/assets/shop/img/logo.png" />
    </a>
</p>

<h1 align="center">SetonoSyliusTermsPlugin</h1>

<p align="center">

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-travis]][link-travis]
[![Quality Score][ico-code-quality]][link-code-quality]

Sylius terms and conditions plugin

Will add the requirement to check off terms and conditions when the customer checks out

</p>

## Screenshots

**Admin**

![Screenshot showing admin terms index page](docs/images/admin-terms-index.png)

![Screenshot showing admin terms update page](docs/images/admin-terms-update.png)

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

**Important**. As far as terms pages URLs looks like this
`http://localhost:8000/en_US/terms-conditions`, make sure you don't add
any terms with slugs like `products`, `taxons`, `login`, etc.

### Step 5: Copy template

```bash
cp vendor/setono/sylius-terms-plugin/tests/Application/templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig \
    templates/bundles/SyliusShopBundle/Checkout/Complete/_form.html.twig
```

# TODO

    [+] Probably, we should allow MULTIPLE Channels to be assigned to every Terms
    [+] Generate links from explanation
    [+] Remove /terms/ from routing
    [ ] Add strategies handlers (new_window, modal_div)
    [ ] Extend ResourceTranslationsType and render only translations for locales from selected Channel

[ico-version]: https://img.shields.io/packagist/v/setono/sylius-terms-plugin.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://travis-ci.com/Setono/SyliusTermsPlugin.svg?branch=master
[ico-code-quality]: https://img.shields.io/scrutinizer/g/Setono/SyliusTermsPlugin.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/setono/sylius-terms-plugin
[link-travis]: https://travis-ci.com/Setono/SyliusTermsPlugin
[link-code-quality]: https://scrutinizer-ci.com/g/Setono/SyliusTermsPlugin
