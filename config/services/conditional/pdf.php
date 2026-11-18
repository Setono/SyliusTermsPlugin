<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Setono\SyliusTermsPlugin\EventListener\AttachTermsPdfsListener;
use Setono\SyliusTermsPlugin\Generator\SlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Setono\SyliusTermsPlugin\Pdf\ImageInliner;
use Setono\SyliusTermsPlugin\Pdf\ImageInlinerInterface;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGenerator;
use Setono\SyliusTermsPlugin\Pdf\TermsPdfGeneratorInterface;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiser;
use Setono\SyliusTermsPlugin\Pdf\UrlAbsolutiserInterface;
use Sylius\Component\Mailer\SyliusMailerEvents;
use Sylius\PdfGenerationBundle\Core\Renderer\HtmlToPdfRendererInterface;

/**
 * Loaded only when setono_sylius_terms.order_confirmation_email.pdf is true, see SetonoSyliusTermsExtension
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(UrlAbsolutiser::class)
        ->args([
            '%sylius.unsecured_urls%',
        ])
    ;

    $services->alias(UrlAbsolutiserInterface::class, UrlAbsolutiser::class);

    $services->set(ImageInliner::class)
        ->args([
            service('http_client'),
        ])
    ;

    $services->alias(ImageInlinerInterface::class, ImageInliner::class);

    $services->set(TermsPdfGenerator::class)
        ->args([
            service('twig'),
            service(UrlAbsolutiserInterface::class),
            service(ImageInlinerInterface::class),
            service(HtmlToPdfRendererInterface::class),
            // Configured in SetonoSyliusTermsExtension::prepend()
            service(TermsPdfGenerator::CACHE_POOL),
            service(SlugGeneratorInterface::class),
            service('logger'),
        ])
    ;

    $services->alias(TermsPdfGeneratorInterface::class, TermsPdfGenerator::class);

    $services->set(AttachTermsPdfsListener::class)
        ->args([
            service(FormTermsLookupInterface::class),
            service(TermsPdfGeneratorInterface::class),
            service('logger'),
        ])
        ->tag('kernel.event_listener', ['event' => SyliusMailerEvents::EMAIL_PRE_SEND])
    ;
};
