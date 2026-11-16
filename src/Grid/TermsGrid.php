<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Grid;

use Sylius\Bundle\GridBundle\Builder\Action\Action;
use Sylius\Bundle\GridBundle\Builder\Action\CreateAction;
use Sylius\Bundle\GridBundle\Builder\Action\DeleteAction;
use Sylius\Bundle\GridBundle\Builder\Action\UpdateAction;
use Sylius\Bundle\GridBundle\Builder\Field\StringField;
use Sylius\Bundle\GridBundle\Builder\Field\TwigField;
use Sylius\Bundle\GridBundle\Builder\Filter\BooleanFilter;
use Sylius\Bundle\GridBundle\Builder\Filter\StringFilter;
use Sylius\Component\Grid\Attribute\AsGrid;
use Sylius\Component\Grid\Builder\GridBuilderInterface;
use Webmozart\Assert\Assert;

/**
 * The admin index of the Terms. Change it from an app with an #[AsGridMutator] for the setono_sylius_terms_terms grid
 */
#[AsGrid(resourceClass: '%setono_sylius_terms.model.terms.class%', name: self::NAME)]
final class TermsGrid
{
    public const NAME = 'setono_sylius_terms_terms';

    /** @var array<string, string> The labels of the configured Forms by their code */
    private array $formLabels = [];

    /**
     * @param array<string, array{label: string}> $forms
     */
    public function __construct(array $forms)
    {
        foreach ($forms as $code => $form) {
            $this->formLabels[$code] = $form['label'];
        }
    }

    public function __invoke(GridBuilderInterface $gridBuilder): void
    {
        // Core's delete action, with a confirmation that tells the admin the Terms' versions go with it
        $delete = DeleteAction::create();
        Assert::isInstanceOf($delete, Action::class);
        $delete->setTemplate('@SetonoSyliusTermsPlugin/admin/terms/grid/action/delete.html.twig');

        $gridBuilder
            // Joins the translation in the admin's locale, which the name column shows and the search filter matches
            ->setRepositoryMethod('createListQueryBuilder', ["expr:service('sylius.context.locale').getLocaleCode()"])
            ->orderBy('position', 'asc')
            ->withFields(
                StringField::create('position')
                    ->setLabel('setono_sylius_terms.ui.position')
                    ->setSortable(true),
                TwigField::create('code', '@SyliusAdmin/shared/grid/field/code.html.twig')
                    ->setLabel('setono_sylius_terms.ui.code')
                    ->setSortable(true),
                TwigField::create('name', '@SyliusAdmin/shared/grid/field/name.html.twig')
                    ->setLabel('setono_sylius_terms.ui.name')
                    ->setSortable(true, 'translation.name'),
                TwigField::create('forms', '@SetonoSyliusTermsPlugin/admin/terms/grid/field/forms.html.twig')
                    ->setLabel('setono_sylius_terms.ui.forms')
                    ->addOptions(['vars' => ['labels' => $this->formLabels]]),
                TwigField::create('channels', '@SyliusAdmin/shared/grid/field/channels.html.twig')
                    ->setLabel('setono_sylius_terms.ui.channels'),
                TwigField::create('required', '@SyliusAdmin/shared/grid/field/boolean.html.twig')
                    ->setLabel('setono_sylius_terms.ui.required')
                    ->setSortable(true)
                    ->addOptions(['vars' => ['th_class' => 'w-1 text-center']]),
                TwigField::create('enabled', '@SyliusAdmin/shared/grid/field/boolean.html.twig')
                    ->setLabel('setono_sylius_terms.ui.enabled')
                    ->setSortable(true)
                    ->addOptions(['vars' => ['th_class' => 'w-1 text-center']]),
            )
            ->withFilters(
                StringFilter::create('search', ['code', 'translation.name'])
                    ->setLabel('sylius.ui.search'),
                BooleanFilter::create('enabled')
                    ->setLabel('setono_sylius_terms.ui.enabled'),
            )
            ->withMainActions(
                CreateAction::create(),
            )
            ->withItemActions(
                UpdateAction::create(),
                $delete,
            )
        ;
    }
}
