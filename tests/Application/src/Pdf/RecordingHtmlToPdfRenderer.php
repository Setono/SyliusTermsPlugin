<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Application\Pdf;

use Sylius\PdfGenerationBundle\Core\Renderer\HtmlToPdfRendererInterface;

/**
 * Decorates the PDF renderer in the test environment. It records the HTML and context of each render, then renders
 * with the real adapter
 */
final class RecordingHtmlToPdfRenderer implements HtmlToPdfRendererInterface
{
    /**
     * Rendering HTML that contains this text fails, as a broken PDF engine would. A test puts it in a Terms' Content.
     * It is a constant, not a setting, so it holds in every kernel the test client boots
     */
    public const FAILING_TEXT = 'This Terms PDF fails to render';

    /** @var list<array{html: string, context: string}> */
    private array $renders = [];

    public function __construct(private readonly HtmlToPdfRendererInterface $decorated)
    {
    }

    public function render(string $html, string $context = 'default'): string
    {
        $this->renders[] = ['html' => $html, 'context' => $context];

        if (str_contains($html, self::FAILING_TEXT)) {
            throw new \RuntimeException('The PDF engine failed');
        }

        return $this->decorated->render($html, $context);
    }

    /**
     * @return list<array{html: string, context: string}>
     */
    public function getRenders(): array
    {
        return $this->renders;
    }
}
