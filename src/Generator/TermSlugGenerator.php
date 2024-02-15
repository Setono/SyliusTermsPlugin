<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Generator;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Webmozart\Assert\Assert;

final class TermSlugGenerator implements TermSlugGeneratorInterface
{
    public function generate(TermsInterface $term, ?string $locale = null): string
    {
        $name = $term->getTranslation($locale)->getName();

        Assert::notNull($name, 'Cannot generate slug without a name.');

        return (new AsciiSlugger())->slug($name)->toString();
    }
}
