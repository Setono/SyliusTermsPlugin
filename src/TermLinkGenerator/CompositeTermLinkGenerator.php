<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\TermLinkGenerator;

use RuntimeException;
use Setono\SyliusTermsPlugin\Model\TermsInterface;

final class CompositeTermLinkGenerator implements TermLinkGeneratorInterface
{
    /** @var list<TermLinkGeneratorInterface> */
    private array $termLinkGenerators = [];

    public function addTermLinkGenerator(TermLinkGeneratorInterface $termLinkGenerator): void
    {
        $this->termLinkGenerators[] = $termLinkGenerator;
    }

    public function isApplicable(TermsInterface $terms): bool
    {
        throw new RuntimeException(sprintf(
            "%s:%s shouldn't be called",
            self::class,
            __FUNCTION__,
        ));
    }

    public function generate(TermsInterface $terms, ?string $locale = null): string
    {
        foreach ($this->termLinkGenerators as $termLinkGenerator) {
            if ($termLinkGenerator->isApplicable($terms)) {
                return $termLinkGenerator->generate($terms);
            }
        }

        throw new RuntimeException(sprintf(
            'Unable to generate link at %s:%s',
            self::class,
            __FUNCTION__,
        ));
    }
}
