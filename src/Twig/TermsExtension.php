<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TermsExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('terms_link', [TermsRuntime::class, 'link'], ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }
}
