<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Controller\Action;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;

final readonly class ShowTermsAction
{
    public function __construct(
        private TermsRepositoryInterface $termsRepository,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
        private Environment $twig,
    ) {
    }

    public function __invoke(string $slug): Response
    {
        $terms = $this->termsRepository->findOneByChannelAndLocaleAndSlug(
            $this->channelContext->getChannel(),
            $this->localeContext->getLocaleCode(),
            $slug,
        );

        if (null === $terms) {
            throw new NotFoundHttpException('The terms page does not exist');
        }

        try {
            // here we test if the user has placed a special template for this particular set of terms
            // if not it throws an exception, and we will use the default template
            $template = $this->twig->load(sprintf(
                '@SetonoSyliusTermsPlugin/shop/terms/show/%s.html.twig',
                (string) $terms->getCode(),
            ));
        } catch (LoaderError) {
            $template = $this->twig->load('@SetonoSyliusTermsPlugin/shop/terms/show.html.twig');
        }

        return new Response($template->render([
            'terms' => $terms,
        ]));
    }
}
