<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Repository;

use Doctrine\ORM\QueryBuilder;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;

interface TermsRepositoryInterface extends RepositoryInterface
{
    /**
     * The query behind the admin index. Every Terms is listed, with its translation in the locale joined as
     * "translation" when it has one
     */
    public function createListQueryBuilder(string $locale): QueryBuilder;

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
