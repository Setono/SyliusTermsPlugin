<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class TermsValidationTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        restore_exception_handler();
    }

    #[Test]
    public function the_code_content_is_reserved_for_the_terms_page_hooks(): void
    {
        $violations = self::getValidator()->validatePropertyValue(self::createTerms(), 'code', 'content', ['setono_sylius_terms']);

        self::assertCount(1, $violations);
        self::assertSame('setono_sylius_terms.terms.code.reserved', $violations->get(0)->getMessageTemplate());
        self::assertSame('The code "content" is reserved for the hooks of the Terms page. Choose another code.', $violations->get(0)->getMessage());
    }

    #[Test]
    #[DataProvider('provideCodesThatAreNotReserved')]
    public function only_the_exact_code_content_is_reserved(string $code): void
    {
        self::assertCount(0, self::getValidator()->validatePropertyValue(self::createTerms(), 'code', $code, ['setono_sylius_terms']));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideCodesThatAreNotReserved(): \Generator
    {
        yield 'another code' => ['terms_and_conditions'];
        yield 'longer' => ['content_policy'];
        // Hook names are case-sensitive, so Content doesn't collide with the content hook
        yield 'other case' => ['Content'];
    }

    #[Test]
    #[DataProvider('provideLocales')]
    public function the_reserved_code_message_is_translated(string $locale): void
    {
        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get('translator');

        $english = $translator->trans('setono_sylius_terms.terms.code.reserved', [], 'validators', 'en');
        $translated = $translator->trans('setono_sylius_terms.terms.code.reserved', [], 'validators', $locale);

        self::assertNotSame('setono_sylius_terms.terms.code.reserved', $translated);
        self::assertStringContainsString('content', $translated);
        self::assertNotSame($english, $translated, 'The message falls back to English');
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function provideLocales(): \Generator
    {
        foreach (['da', 'de', 'fr', 'nl'] as $locale) {
            yield $locale => [$locale];
        }
    }

    private static function createTerms(): TermsInterface
    {
        /** @var FactoryInterface $factory */
        $factory = self::getContainer()->get('setono_sylius_terms.factory.terms');

        $terms = $factory->createNew();
        self::assertInstanceOf(TermsInterface::class, $terms);

        return $terms;
    }

    private static function getValidator(): ValidatorInterface
    {
        /** @var ValidatorInterface $validator */
        $validator = self::getContainer()->get('validator');

        return $validator;
    }
}
