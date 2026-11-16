<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Model\Terms;

final class TermsTest extends TestCase
{
    #[Test]
    public function a_terms_is_required_by_default(): void
    {
        $terms = new Terms();
        self::assertTrue($terms->isRequired());

        $terms->setRequired(false);
        self::assertFalse($terms->isRequired());
    }

    #[Test]
    public function a_new_terms_has_no_position(): void
    {
        self::assertNull((new Terms())->getPosition());
    }

    #[Test]
    public function it_keeps_the_position_it_is_given(): void
    {
        $terms = new Terms();
        $terms->setPosition(3);

        self::assertSame(3, $terms->getPosition());
    }

    #[Test]
    public function a_null_position_is_stored_as_gedmos_last_position(): void
    {
        $terms = new Terms();
        $terms->setPosition(3);
        $terms->setPosition(null);

        self::assertSame(-1, $terms->getPosition());
    }
}
