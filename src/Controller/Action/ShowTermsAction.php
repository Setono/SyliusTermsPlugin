<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Controller\Action;

use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;

final class ShowTermsAction
{
    /** @var TermsRepositoryInterface */
    private $termsRepository;

    /** @var ChannelContextInterface */
    private $channelContext;

    /** @var Environment */
    private $twig;

    public function __construct(TermsRepositoryInterface $termsRepository, ChannelContextInterface $channelContext, Environment $twig)
    {
        $this->termsRepository = $termsRepository;
        $this->channelContext = $channelContext;
        $this->twig = $twig;
    }

    public function __invoke(string $slug): Response
    {
        $channel = $this->channelContext->getChannel();
        $terms = $this->termsRepository->findOneByChannelAndSlug($channel, $slug);

        if (null === $terms) {
            throw new NotFoundHttpException('The terms page does not exist');
        }

        try {
            // here we test if the user has placed a special template for this particular set of terms
            // if not it throws an exception and we will use the default template
            $template = $this->twig->load(sprintf(
                '@SetonoSyliusTermsPlugin/Shop/Terms/Show/%s.html.twig',
                $terms->getCode(),
            ));
        } catch (LoaderError $e) {
            $template = $this->twig->load('@SetonoSyliusTermsPlugin/Shop/Terms/show.html.twig');
        }

        return new Response($template->render([
            'terms' => $terms,
        ]));
    }
}
