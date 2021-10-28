<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Twig\Extension;

use Safe\Exceptions\StringsException;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Provider\TermsProviderInterface;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Templating\EngineInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Webmozart\Assert\Assert;
use function Safe\sprintf;

class FooterTermViewExtension extends AbstractExtension
{
    /** @var TermsProviderInterface */
    private $termsProvider;

    /** @var RouterInterface */
    private $router;

    /** @var EngineInterface|Environment */
    private $templatingEngine;

    public function __construct(
        TermsProviderInterface $termsProvider,
        RouterInterface $router,
        $templatingEngine
    )
    {
        $this->termsProvider = $termsProvider;
        $this->router = $router;
        $this->templatingEngine = $templatingEngine;
    }

    /** {@inheritdoc} */
    public function getFunctions()
    {
        return [
            new TwigFunction('footer_terms_view', [$this, 'findEnabledTermsForFooterTemplate']),
            new TwigFunction('footer_term_link', [$this, 'getFooterTermLink'], ['is_safe' => ['html']]),

        ];
    }

    /**
     * @return TermsInterface[]|null
     *
     * @throws StringsException
     */
    public function findEnabledTermsForFooterTemplate(): ?array
    {
        $terms = $this->termsProvider->getEnabledTermsForFooterTemplate();
        if (!$terms) {
            return null;
        }

        $checkType = function($term): void {
            if (!$term instanceof TermsInterface) {
                throw new InvalidConfigurationException(
                    sprintf('Each object passed as terms list must implement "%s"', TermsInterface::class)
                );
            }
        };

        array_map($checkType, $terms);

        return $terms;
    }

    /**
     * @param TermsInterface $term
     * @param string|null $locale
     * @param string|null $template
     *
     * @return string
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getFooterTermLink(TermsInterface $term, ?string $locale = null, ?string $template = null): string
    {
        if (!$template) {
            $template = '@SetonoSyliusTermsPlugin/Shop/Footer/View/_term.html.twig';
        }

        $slug = $term->getTranslation($locale)->getSlug() ?? $term->getSlug();;
        Assert::notEmpty($slug, 'Cannot generate link without a slug.');

        $termLink = $this->router->generate('setono_sylius_terms_show', ['slug' => $slug]);
        $termName =  $term->getTranslation($locale)->getName() ?? $term->getCode();

        return $this->templatingEngine->render(
            $template, [ 'termLink' => $termLink, 'termName' => $termName]
        );
    }
}
