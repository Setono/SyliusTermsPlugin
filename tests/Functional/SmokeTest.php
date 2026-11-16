<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\SetonoSyliusTermsPlugin;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_test_application_boots_with_the_plugin(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('test', $kernel->getEnvironment());
        self::assertInstanceOf(SetonoSyliusTermsPlugin::class, $kernel->getBundle('SetonoSyliusTermsPlugin'));
    }

    #[Test]
    public function the_shop_homepage_returns_200(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en_US/');

        self::assertResponseStatusCodeSame(200);
    }
}
