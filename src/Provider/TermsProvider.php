<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Provider;

use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;

final readonly class TermsProvider implements TermsProviderInterface
{
    public function __construct(
        private FormTermsLookupInterface $formTermsLookup,
        private ChannelContextInterface $channelContext,
        private LocaleContextInterface $localeContext,
    ) {
    }

    public function getTerms(string $form): array
    {
        $termsList = [];

        foreach ($this->formTermsLookup->lookup($form, $this->channelContext->getChannel(), $this->localeContext->getLocaleCode()) as $translation) {
            $terms = $translation->getVersion()->getTerms();
            if (null !== $terms) {
                $termsList[] = $terms;
            }
        }

        return $termsList;
    }
}
