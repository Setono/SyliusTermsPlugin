<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Checker;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsTranslationInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionTranslationInterface;
use Webmozart\Assert\Assert;

final class VersionedTextChangeChecker implements VersionedTextChangeCheckerInterface
{
    public function hasChanged(TermsInterface $terms): bool
    {
        $latestVersion = $terms->getLatestVersion();
        if (null === $latestVersion) {
            return true;
        }

        $currentText = [];
        foreach ($terms->getTranslations() as $translation) {
            Assert::isInstanceOf($translation, TermsTranslationInterface::class);
            $currentText[(string) $translation->getLocale()] = self::text($translation);
        }

        $versionedText = [];
        foreach ($latestVersion->getTranslations() as $translation) {
            Assert::isInstanceOf($translation, TermsVersionTranslationInterface::class);
            $versionedText[(string) $translation->getLocale()] = self::text($translation);
        }

        ksort($currentText);
        ksort($versionedText);

        return $currentText !== $versionedText;
    }

    /**
     * Content is compared with LF line breaks. Both setters store it that way, but Content upgraded from 2.x keeps its
     * CRLF line breaks until it is set again, because Doctrine loads it without the setter, while its version 1 has LF
     *
     * @return array{string|null, string|null, string|null} name, label and content
     */
    private static function text(TermsTranslationInterface|TermsVersionTranslationInterface $translation): array
    {
        $content = $translation->getContent();

        return [
            $translation->getName(),
            $translation->getLabel(),
            null === $content ? null : str_replace(["\r\n", "\r"], "\n", $content),
        ];
    }
}
