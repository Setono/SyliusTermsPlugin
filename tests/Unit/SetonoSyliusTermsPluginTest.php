<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\SetonoSyliusTermsPlugin;

final class SetonoSyliusTermsPluginTest extends TestCase
{
    #[Test]
    public function its_path_is_the_repository_root(): void
    {
        self::assertSame(dirname(__DIR__, 2), (new SetonoSyliusTermsPlugin())->getPath());
    }
}
