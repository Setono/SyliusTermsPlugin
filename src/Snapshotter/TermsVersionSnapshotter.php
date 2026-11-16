<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Snapshotter;

use Psr\Clock\ClockInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Webmozart\Assert\Assert;

final readonly class TermsVersionSnapshotter implements TermsVersionSnapshotterInterface
{
    public function __construct(
        private FactoryInterface $termsVersionFactory,
        private FactoryInterface $termsVersionTranslationFactory,
        private ClockInterface $clock,
    ) {
    }

    public function snapshot(TermsInterface $terms): TermsVersionInterface
    {
        $version = $this->termsVersionFactory->createNew();
        Assert::isInstanceOf($version, TermsVersionInterface::class);

        $version->setNumber(($terms->getLatestVersion()?->getNumber() ?? 0) + 1);
        $version->setInForceFrom($this->clock->now());

        foreach ($terms->getTranslations() as $translation) {
            Assert::isInstanceOf($translation, TermsTranslationInterface::class);

            $versionTranslation = $this->termsVersionTranslationFactory->createNew();
            Assert::isInstanceOf($versionTranslation, TermsVersionTranslationInterface::class);

            $versionTranslation->setLocale($translation->getLocale());
            $versionTranslation->setName($translation->getName());
            $versionTranslation->setLabel($translation->getLabel());
            $versionTranslation->setContent($translation->getContent());

            $version->addTranslation($versionTranslation);
        }

        $terms->addVersion($version);

        return $version;
    }
}
