<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;

/**
 * Finds the ways 2.x Content may render differently in 3.0 (ADR 0005). 2.x printed Content as raw HTML, so the
 * Markdown that the plugin's converter finds in it is what changes: code blocks, lists and headings. Raw HTML passes
 * through, except the class and style attributes, which the sanitiser drops. Part of the upgrade from 2.x, which 4.0
 * removes
 */
final readonly class ContentCaseDetector
{
    public function __construct(private ConverterInterface $converter)
    {
    }

    /**
     * @return list<ContentCase> in the order of ContentCase::cases()
     */
    public function detect(string $content): array
    {
        $found = [];

        foreach ($this->converter->convert($content)->getDocument()->iterator() as $node) {
            $case = match (true) {
                $node instanceof IndentedCode => ContentCase::IndentedCode,
                $node instanceof ListBlock, $node instanceof Heading => ContentCase::ListOrHeading,
                ($node instanceof HtmlBlock || $node instanceof HtmlInline) && self::hasClassOrStyle($node->getLiteral()) => ContentCase::ClassOrStyle,
                default => null,
            };

            if (null !== $case) {
                $found[] = $case;
            }
        }

        return array_values(array_filter(
            ContentCase::cases(),
            static fn (ContentCase $case): bool => in_array($case, $found, true),
        ));
    }

    private static function hasClassOrStyle(string $html): bool
    {
        return 1 === preg_match('/<[a-z][^>]*\s(?:class|style)\s*=/i', $html);
    }
}
