<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\ClickStrategyApplicator;

use Doctrine\Common\Collections\ArrayCollection;

final class CompositeClickStrategyApplicator extends ArrayCollection implements ClickStrategyApplicatorInterface
{
    /** @var string */
    private $clickStrategy;

    public function __construct(string $clickStrategy)
    {
        $this->clickStrategy = $clickStrategy;

        parent::__construct();
    }

    public function addClickStrategyApplicator(ClickStrategyApplicatorInterface $clickStrategyApplicator, string $alias): void
    {
        $this->set($alias, $clickStrategyApplicator);
    }

    public function applyClickStrategy(string $termsLink): string
    {
        if (!$this->containsKey($this->clickStrategy)) {
            return $termsLink;
        }

        /** @var ClickStrategyApplicatorInterface $clickStrategyApplicator */
        $clickStrategyApplicator = $this->get($this->clickStrategy);

        return $clickStrategyApplicator->applyClickStrategy($termsLink);
    }
}
