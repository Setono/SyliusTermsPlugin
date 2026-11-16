<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional\Grid\Entity;

use Doctrine\ORM\Mapping as ORM;
use Setono\SyliusTermsPlugin\Model\Terms;

/**
 * An app's own Terms model, as OverriddenTermsModelKernel configures it
 */
#[ORM\Entity]
#[ORM\Table(name: 'setono_sylius_terms__terms')]
class OverriddenTerms extends Terms
{
}
