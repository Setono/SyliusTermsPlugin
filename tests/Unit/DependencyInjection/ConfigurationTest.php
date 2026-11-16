<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\DependencyInjection;

use Matthias\SymfonyConfigTest\PhpUnit\ConfigurationTestCaseTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\DependencyInjection\Configuration;
use Sylius\Bundle\CoreBundle\Form\Type\Checkout\CompleteType;

final class ConfigurationTest extends TestCase
{
    use ConfigurationTestCaseTrait;

    protected function getConfiguration(): Configuration
    {
        return new Configuration();
    }

    #[Test]
    public function it_adds_terms_to_the_checkout_complete_form_by_default(): void
    {
        $this->assertProcessedConfigurationEquals(
            [],
            ['forms' => [CompleteType::class => ['label' => null]]],
            'forms',
        );
    }

    #[Test]
    public function it_serves_terms_under_the_terms_path_by_default(): void
    {
        $this->assertProcessedConfigurationEquals(
            [],
            ['routing' => ['terms' => 'terms']],
            'routing',
        );
    }
}
