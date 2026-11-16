<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Browser;

use PHPUnit\Framework\Attributes\Test;
use Playwright\Symfony\Test\PlaywrightTestCase;

final class SmokeTest extends PlaywrightTestCase
{
    #[Test]
    public function the_shop_homepage_opens_in_chromium(): void
    {
        $page = $this->visit('/en_US/');

        $userAgent = $page->evaluate('navigator.userAgent');
        self::assertIsString($userAgent);
        self::assertStringContainsString('Chrome', $userAgent);
        self::assertStringEndsWith('/en_US/', $page->url());
        // The title is the name of the channel the default fixtures create, so an error page doesn't pass
        self::assertSame('Fashion Web Store', $page->title());
        self::assertSame([], $page->pageErrors(), 'The shop homepage threw JavaScript errors');
    }
}
