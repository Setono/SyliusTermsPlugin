<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Renderer;

use League\CommonMark\ConverterInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\NodeIterator;
use League\CommonMark\Node\RawMarkupContainerInterface;
use League\CommonMark\Node\StringContainerInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class ContentRenderer implements ContentRendererInterface
{
    public function __construct(
        private ConverterInterface $converter,
        private HtmlSanitizerInterface $sanitizer,
    ) {
    }

    public function render(string $content): RenderedContent
    {
        $converted = $this->converter->convert($content);

        return new RenderedContent(
            $this->sanitizer->sanitize($converted->getContent()),
            self::outline($converted->getDocument()),
        );
    }

    /**
     * Headings written as raw HTML aren't Heading nodes, so they are never in the outline
     *
     * @return list<OutlineHeading>
     */
    private static function outline(Document $document): array
    {
        $outline = [];

        foreach ($document->iterator(NodeIterator::FLAG_BLOCKS_ONLY) as $node) {
            if (!$node instanceof Heading || 2 !== $node->getLevel()) {
                continue;
            }

            $id = $node->data->get('attributes/id', null);
            $text = self::text($node);

            if (!is_string($id) || '' === $text) {
                continue;
            }

            $outline[] = new OutlineHeading($id, $text);
        }

        return $outline;
    }

    private static function text(Heading $heading): string
    {
        $text = '';

        foreach ($heading->iterator() as $node) {
            if ($node instanceof Newline) {
                $text .= ' ';
            } elseif ($node instanceof StringContainerInterface && !$node instanceof RawMarkupContainerInterface) {
                $text .= $node->getLiteral();
            }
        }

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
