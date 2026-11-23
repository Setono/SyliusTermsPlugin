<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Snapshotter;

use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Model\TermsVersionInterface;

interface TermsVersionSnapshotterInterface
{
    /**
     * Creates the Terms' next version from its current translations and adds it to the Terms. The version copies the
     * name, label and content of every locale, the content as TermsVersionTranslationInterface::setContent() stores
     * it, is numbered after the Terms' latest version (1 if it has none), and is in force from now.
     *
     * The version is persisted with the Terms on the next flush
     */
    public function snapshot(TermsInterface $terms): TermsVersionInterface;
}
