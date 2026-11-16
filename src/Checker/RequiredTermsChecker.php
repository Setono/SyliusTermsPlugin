<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Checker;

use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Sylius\Component\Channel\Model\ChannelInterface;

final readonly class RequiredTermsChecker implements RequiredTermsCheckerInterface
{
    public function __construct(private FormTermsLookupInterface $formTermsLookup)
    {
    }

    public function getUntickedRequiredTerms(string $form, ChannelInterface $channel, string $localeCode, array $tickedTerms): array
    {
        $unticked = [];

        foreach ($this->formTermsLookup->lookup($form, $channel, $localeCode) as $translation) {
            $terms = $translation->getVersion()->getTerms();
            if (null === $terms || !$terms->isRequired() || in_array($terms->getCode(), $tickedTerms, true)) {
                continue;
            }

            $unticked[] = $translation;
        }

        return $unticked;
    }
}
