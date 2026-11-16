<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Positions are shared by every Terms, so the tests compare them with the positions the baseline Terms already hold
 */
final class TermsMappingTest extends KernelTestCase
{
    use CreatesTermsTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function a_new_terms_with_a_blank_position_is_appended_last(): void
    {
        $lastPosition = $this->getLastPosition();

        $terms = self::createTerms('appended');

        self::assertSame($lastPosition + 1, self::reloadTerms($terms)->getPosition());
    }

    #[Test]
    public function setting_a_position_inserts_the_terms_there_and_shifts_the_others(): void
    {
        $first = self::createTerms('first');
        $second = self::createTerms('second');
        $third = self::createTerms('third');
        $position = (int) $first->getPosition();

        $third->setPosition($position);
        self::getEntityManager()->flush();

        self::assertSame($position, self::reloadTerms($third)->getPosition());
        self::assertSame($position + 1, self::reloadTerms($first)->getPosition());
        self::assertSame($position + 2, self::reloadTerms($second)->getPosition());
    }

    #[Test]
    public function a_new_terms_given_a_position_is_inserted_there(): void
    {
        $first = self::createTerms('first');
        $position = (int) $first->getPosition();

        $inserted = self::createTerms('inserted', position: $position);

        self::assertSame($position, self::reloadTerms($inserted)->getPosition());
        self::assertSame($position + 1, self::reloadTerms($first)->getPosition());
    }

    #[Test]
    public function clearing_the_position_of_a_saved_terms_moves_it_last(): void
    {
        $first = self::createTerms('first');
        $second = self::createTerms('second');
        $third = self::createTerms('third');
        $position = (int) $first->getPosition();

        $first = self::reloadTerms($first);
        $first->setPosition(null);
        self::getEntityManager()->flush();

        self::assertSame($position + 2, self::reloadTerms($first)->getPosition());
        self::assertSame($position, self::reloadTerms($second)->getPosition());
        self::assertSame($position + 1, self::reloadTerms($third)->getPosition());
    }

    #[Test]
    public function the_required_column_defaults_to_true_in_the_database(): void
    {
        $column = self::getEntityManager()->getConnection()->createSchemaManager()
            ->introspectTable('setono_sylius_terms__terms')
            ->getColumn('required')
        ;

        self::assertTrue($column->getNotnull());
        self::assertSame('1', $column->getDefault());
    }

    #[Test]
    public function an_optional_terms_is_saved_as_optional(): void
    {
        self::assertFalse(self::reloadTerms(self::createTerms('optional', required: false))->isRequired());
        self::assertTrue(self::reloadTerms(self::createTerms('required'))->isRequired());
    }

    private function getLastPosition(): int
    {
        $lastPosition = self::getEntityManager()->getConnection()->fetchOne('SELECT MAX(position) FROM setono_sylius_terms__terms');

        return is_numeric($lastPosition) ? (int) $lastPosition : -1;
    }
}
