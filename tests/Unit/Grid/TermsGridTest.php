<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Tests\Unit\Grid;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Setono\SyliusTermsPlugin\Grid\TermsGrid;
use Setono\SyliusTermsPlugin\Model\Terms;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilder;

final class TermsGridTest extends TestCase
{
    #[Test]
    public function it_is_the_setono_sylius_terms_terms_grid_of_the_terms_model(): void
    {
        $attributes = (new \ReflectionClass(TermsGrid::class))->getAttributes(AsGrid::class);
        self::assertCount(1, $attributes);

        $asGrid = $attributes[0]->newInstance();
        self::assertSame('setono_sylius_terms_terms', $asGrid->name);
        self::assertSame('%setono_sylius_terms.model.terms.class%', $asGrid->resourceClass);
    }

    #[Test]
    public function it_lists_the_terms_with_the_translation_in_the_admins_locale(): void
    {
        self::assertSame([
            'name' => 'doctrine/orm',
            'options' => [
                'class' => Terms::class,
                'repository' => [
                    'method' => 'createListQueryBuilder',
                    'arguments' => ["expr:service('sylius.context.locale').getLocaleCode()"],
                ],
            ],
        ], self::build()['driver']);
    }

    #[Test]
    public function it_sorts_by_position(): void
    {
        self::assertSame(['position' => 'asc'], self::build()['sorting']);
    }

    #[Test]
    public function it_has_the_fields(): void
    {
        $fields = self::build()['fields'];
        self::assertIsArray($fields);

        self::assertSame(['position', 'code', 'name', 'forms', 'channels', 'required', 'enabled'], array_keys($fields));

        self::assertSame(['type' => 'string', 'label' => 'setono_sylius_terms.ui.position', 'sortable' => true], $fields['position']);
        self::assertSame([
            'type' => 'twig',
            'label' => 'setono_sylius_terms.ui.code',
            'sortable' => true,
            'options' => ['template' => '@SyliusAdmin/shared/grid/field/code.html.twig'],
        ], $fields['code']);
        self::assertSame([
            'type' => 'twig',
            'label' => 'setono_sylius_terms.ui.name',
            'sortable' => 'translation.name',
            'options' => ['template' => '@SyliusAdmin/shared/grid/field/name.html.twig'],
        ], $fields['name']);
        self::assertSame([
            'type' => 'twig',
            'label' => 'setono_sylius_terms.ui.forms',
            'options' => [
                'template' => '@SetonoSyliusTermsPlugin/admin/terms/grid/field/forms.html.twig',
                'vars' => ['labels' => [
                    'checkout_complete' => 'setono_sylius_terms.form.terms.term_form.checkout_complete',
                    'newsletter' => 'app.form.newsletter',
                ]],
            ],
        ], $fields['forms']);
        self::assertSame([
            'type' => 'twig',
            'label' => 'setono_sylius_terms.ui.channels',
            'options' => ['template' => '@SyliusAdmin/shared/grid/field/channels.html.twig'],
        ], $fields['channels']);

        foreach (['required', 'enabled'] as $field) {
            self::assertSame([
                'type' => 'twig',
                'label' => 'setono_sylius_terms.ui.' . $field,
                'sortable' => true,
                'options' => [
                    'template' => '@SyliusAdmin/shared/grid/field/boolean.html.twig',
                    'vars' => ['th_class' => 'w-1 text-center'],
                ],
            ], $fields[$field]);
        }
    }

    #[Test]
    public function it_filters_by_a_search_over_code_and_name_and_by_enabled(): void
    {
        self::assertSame([
            'search' => [
                'type' => 'string',
                'label' => 'sylius.ui.search',
                'options' => ['fields' => ['code', 'translation.name']],
            ],
            'enabled' => [
                'type' => 'boolean',
                'label' => 'setono_sylius_terms.ui.enabled',
            ],
        ], self::build()['filters']);
    }

    #[Test]
    public function it_has_create_update_and_delete_actions_but_no_bulk_actions(): void
    {
        $actions = self::build()['actions'];
        self::assertIsArray($actions);

        self::assertSame(['main', 'item'], array_keys($actions));
        self::assertSame(['create' => ['type' => 'create', 'label' => 'sylius.ui.create']], $actions['main']);
        self::assertSame([
            'update' => ['type' => 'update', 'label' => 'sylius.ui.edit'],
            'delete' => [
                'type' => 'delete',
                'label' => 'sylius.ui.delete',
                'template' => '@SetonoSyliusTermsPlugin/admin/terms/grid/action/delete.html.twig',
            ],
        ], $actions['item']);
    }

    /**
     * @return array<string, mixed>
     */
    private static function build(): array
    {
        $gridBuilder = GridBuilder::create('setono_sylius_terms_terms', Terms::class);

        (new TermsGrid([
            'checkout_complete' => ['label' => 'setono_sylius_terms.form.terms.term_form.checkout_complete'],
            'newsletter' => ['label' => 'app.form.newsletter'],
        ]))($gridBuilder);

        return $gridBuilder->toArray();
    }
}
