<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

/**
 * Upgrades the Terms in a 2.x database, once the 3.0 schema is in place. Safe to run again: only Terms without a
 * version are treated as 2.x data. Part of the upgrade from 2.x, which 4.0 removes
 */
interface UpgraderInterface
{
    /**
     * Works out what the upgrade does, and writes nothing. It reads the clock once: the version 1 of each Terms from
     * 2.x is in force from that moment
     */
    public function plan(): UpgradePlan;

    /**
     * Writes a plan from plan(), in one transaction: version 1 and a position for each Terms from 2.x, and the Form
     * codes of each Terms whose form type classes get mapped
     */
    public function apply(UpgradePlan $plan): void;
}
