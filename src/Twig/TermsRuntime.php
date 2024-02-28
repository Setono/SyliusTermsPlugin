<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

final class TermsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly TermsRepositoryInterface $termsRepository,
    ) {
    }

    /**
     * Returns a <a href="...">...</a> link to the terms if the given terms code exists
     * else it returns an empty string
     */
    public function link(Environment $env, string $code, string $template = null): string
    {
        $terms = $this->termsRepository->findOneByChannelAndLocaleAndCode(
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
            $code,
        );

        $template ??= '@SetonoSyliusTermsPlugin/shop/terms/link.html.twig';

        return $env->render($template, [
            'terms' => $terms,
        ]);
    }
}
