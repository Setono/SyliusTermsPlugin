<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Resource\Repository\RepositoryInterface;

interface TermsRepositoryInterface extends RepositoryInterface
{
    /**
     * Returns the enabled Terms placed on the Form in the channel, in position order
     *
     * @param string $form a Form code
     *
     * @return list<TermsInterface>
     */
    public function findByFormAndChannel(string $form, ChannelInterface $channel): array;

    public function findOneByChannelAndLocaleAndSlug(ChannelInterface $channel, string $locale, string $slug): ?TermsInterface;

    public function findOneByChannelAndLocaleAndCode(ChannelInterface $channel, string $locale, string $code): ?TermsInterface;
}
