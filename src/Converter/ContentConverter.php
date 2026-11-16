<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Converter;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalink;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Converts Content to HTML: CommonMark plus the GFM tables and autolinks extensions, with raw HTML passed through.
 *
 * The output is not safe to print, because raw HTML passes through. The content renderer sanitises it
 */
final class ContentConverter extends MarkdownConverter
{
    public function __construct()
    {
        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'heading_permalink' => [
                'apply_id_to_heading' => true,
                'insert' => 'after',
                'symbol' => '#',
                // aria_hidden also adds tabindex="-1". The sanitiser keeps tabindex but drops aria-hidden, which would
                // leave a link that screen readers announce but keyboards can't reach
                'aria_hidden' => false,
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension());
        $environment->addExtension(new TableExtension());
        $environment->addExtension(new AutolinkExtension());
        $environment->addExtension(new HeadingPermalinkExtension());

        // Runs after the heading permalink processor (priority -100), which gives every Markdown heading a permalink
        $environment->addEventListener(DocumentParsedEvent::class, self::keepPermalinksOnSectionHeadings(...), -200);

        parent::__construct($environment);
    }

    /**
     * Every Markdown heading keeps its id, but only the ## headings, the ones in the outline, show a permalink
     */
    private static function keepPermalinksOnSectionHeadings(DocumentParsedEvent $event): void
    {
        $permalinks = [];

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof HeadingPermalink && $node->parent() instanceof Heading && 2 !== $node->parent()->getLevel()) {
                $permalinks[] = $node;
            }
        }

        // Detached after the walk, because detaching a node while iterating would end the walk early
        foreach ($permalinks as $permalink) {
            $permalink->detach();
        }
    }
}
