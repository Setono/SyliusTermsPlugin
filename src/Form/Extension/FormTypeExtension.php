<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Form\Extension;

use Setono\SyliusTermsPlugin\Dispatcher\TermsAnsweredDispatcherInterface;
use Setono\SyliusTermsPlugin\Form\EventSubscriber\DispatchTermsAnsweredSubscriber;
use Setono\SyliusTermsPlugin\Form\Type\TermsCheckboxCollectionType;
use Setono\SyliusTermsPlugin\Lookup\FormTermsLookupInterface;
use Sylius\Component\Channel\Context\ChannelContextInterface;
use Sylius\Component\Locale\Context\LocaleContextInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Adds the terms field to the form types of the configured Forms, with the Terms the Form lookup finds in the current
 * channel and locale, now, and hands the Answers of a valid submit to the TermsAnsweredEvent dispatcher
 */
final class FormTypeExtension extends AbstractTypeExtension
{
    /**
     * The codes of the Forms, by their form type class
     *
     * @var array<class-string, string>
     */
    private readonly array $formCodes;

    /**
     * @param array<string, array{type: class-string, saved_event: string|null}> $forms
     */
    public function __construct(
        private readonly FormTermsLookupInterface $formTermsLookup,
        private readonly ChannelContextInterface $channelContext,
        private readonly LocaleContextInterface $localeContext,
        private readonly TermsAnsweredDispatcherInterface $termsAnsweredDispatcher,
        private readonly RequestStack $requestStack,
        private readonly array $forms,
    ) {
        // A form being built has only its type to find its Form by, so the configuration rejects two enabled Forms with
        // the same type, and each type maps to one code
        $formCodes = [];
        foreach ($forms as $code => $form) {
            $formCodes[$form['type']] = $code;
        }

        $this->formCodes = $formCodes;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // The type of the form being built, which may extend FormType through other types
        $formCode = $this->formCodes[$builder->getType()->getInnerType()::class] ?? null;
        if (null === $formCode) {
            return;
        }

        $channel = $this->channelContext->getChannel();
        $localeCode = $this->localeContext->getLocaleCode();

        $terms = $this->formTermsLookup->lookup($formCode, $channel, $localeCode);

        // A Form with no Terms renders nothing
        if ([] === $terms) {
            return;
        }

        $builder->add('terms', TermsCheckboxCollectionType::class, [
            'form' => $formCode,
            'terms' => $terms,
        ]);

        // A Live Component re-renders its form by submitting it, so in a Live Component request only the Form's saved
        // event tells a real submit from a re-render. A Form without one dispatches nothing there
        if (null === $this->forms[$formCode]['saved_event'] && $this->isLiveComponentRequest()) {
            return;
        }

        $builder->addEventSubscriber(new DispatchTermsAnsweredSubscriber(
            $this->termsAnsweredDispatcher,
            $formCode,
            $channel,
            $localeCode,
            $terms,
        ));
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    /**
     * The route of Live Component requests, re-renders and actions alike, has this attribute
     */
    private function isLiveComponentRequest(): bool
    {
        return $this->requestStack->getMainRequest()?->attributes->has('_live_component') ?? false;
    }
}
