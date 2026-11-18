<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

final class TermsExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('terms_content', [ContentRuntime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('terms_path', [TermsRuntime::class, 'path']),
            new TwigFunction('terms_link', [TermsRuntime::class, 'link'], ['needs_environment' => true, 'is_safe' => ['html']]),
            new TwigFunction('terms_channel_path', [TermsRuntime::class, 'channelPath']),
        ];
    }
}
