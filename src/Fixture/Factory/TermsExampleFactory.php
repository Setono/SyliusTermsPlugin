<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Fixture\Factory;

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Repository\TermsRepositoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Model\ChannelInterface;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\String\Slugger\AsciiSlugger;

class TermsExampleFactory extends AbstractExampleFactory
{
    private const TRANSLATION_OPTIONS = ['name', 'slug', 'label', 'content'];

    private readonly FakerGenerator $faker;

    private readonly OptionsResolver $optionsResolver;

    /** @var list<string> */
    private readonly array $formCodes;

    /**
     * @param array<string, mixed> $forms the enabled Forms, keyed by code (the setono_sylius_terms.forms parameter)
     */
    public function __construct(
        private readonly FactoryInterface $termsFactory,
        private readonly TermsRepositoryInterface $termsRepository,
        private readonly RepositoryInterface $localeRepository,
        private readonly ChannelRepositoryInterface $channelRepository,
        array $forms,
    ) {
        $this->faker = FakerFactory::create();
        $this->formCodes = array_keys($forms);
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    public function create(array $options = []): TermsInterface
    {
        /**
         * @var array{
         *     code: string,
         *     name: string,
         *     slug: string|null,
         *     label: string,
         *     content: string,
         *     enabled: bool,
         *     required: bool,
         *     position: int|null,
         *     forms: list<string>,
         *     channels: list<ChannelInterface>,
         *     locales: list<string>,
         *     translations: array<string, array<string, mixed>>,
         * } $options
         */
        $options = $this->optionsResolver->resolve($options);

        /** @var TermsInterface|null $terms */
        $terms = $this->termsRepository->findOneBy(['code' => $options['code']]);

        /** @var TermsInterface $terms */
        $terms ??= $this->termsFactory->createNew();

        $terms->setCode($options['code']);
        foreach ($options['channels'] as $channel) {
            $terms->addChannel($channel);
        }

        $terms->setEnabled($options['enabled']);
        $terms->setRequired($options['required']);
        $terms->setPosition($options['position']);
        $terms->setForms($options['forms']);

        // The name, slug, label and content are the defaults for every locale, and each locale's translations override them
        $defaults = array_intersect_key($options, array_flip(self::TRANSLATION_OPTIONS));
        $translations = array_fill_keys($options['locales'], []);
        foreach ($options['translations'] as $localeCode => $translationOptions) {
            $translations[$localeCode] = $translationOptions;
        }

        foreach ($translations as $localeCode => $translationOptions) {
            $this->createTranslation($terms, $localeCode, array_merge($defaults, $translationOptions));
        }

        return $terms;
    }

    /**
     * @param array<string, mixed> $options the name, slug, label and content of the translation
     */
    protected function createTranslation(TermsInterface $terms, string $localeCode, array $options = []): void
    {
        $resolver = new OptionsResolver();
        $resolver
            ->setRequired(['name', 'label', 'content'])
            ->setAllowedTypes('name', 'string')
            ->setAllowedTypes('label', 'string')
            ->setAllowedTypes('content', 'string')
            ->setDefault('slug', null)
            ->setAllowedTypes('slug', ['null', 'string'])
        ;

        /** @var array{name: string, slug: string|null, label: string, content: string} $options */
        $options = $resolver->resolve($options);

        $terms->setCurrentLocale($localeCode);
        $terms->setFallbackLocale($localeCode);

        $terms->setName($options['name']);
        $terms->setLabel($options['label']);
        $terms->setContent($options['content']);
        $terms->setSlug($options['slug'] ?? (new AsciiSlugger())->slug($options['name'])->toString());
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('name', fn (Options $options): string => $this->fakeTitle())
            ->setAllowedTypes('name', ['string'])

            ->setDefault('code', static function (Options $options): string {
                /** @var string $name */
                $name = $options['name'];

                return StringInflector::nameToCode($name);
            })
            ->setAllowedTypes('code', ['string'])

            ->setDefault('slug', null)
            ->setAllowedTypes('slug', ['null', 'string'])

            ->setDefault('label', static function (Options $options): string {
                /** @var string $name */
                $name = $options['name'];

                return sprintf('I have read and accept the [link:%s]', $name);
            })
            ->setAllowedTypes('label', ['string'])

            ->setDefault('content', fn (Options $options): string => sprintf(
                "## %s\n\n%s\n\n## %s\n\n%s",
                $this->fakeTitle(),
                $this->faker->paragraph(),
                $this->fakeTitle(),
                $this->faker->paragraph(),
            ))
            ->setAllowedTypes('content', ['string'])

            ->setDefault('enabled', true)
            ->setAllowedTypes('enabled', ['bool'])

            ->setDefault('required', true)
            ->setAllowedTypes('required', ['bool'])

            // A blank position puts the Terms last
            ->setDefault('position', null)
            ->setAllowedTypes('position', ['null', 'int'])

            // A Terms is on a Form only when the fixture says so
            ->setDefault('forms', [])
            ->setAllowedTypes('forms', ['string[]'])
            ->setNormalizer('forms', $this->normalizeForms(...))

            // Every channel, like Sylius' promotion and payment method fixtures
            ->setDefault('channels', LazyOption::all($this->channelRepository))
            ->setAllowedTypes('channels', ['array'])
            ->setNormalizer('channels', LazyOption::findBy($this->channelRepository, 'code'))

            // The locales to translate the Terms into. Defaults to every locale
            ->setDefault('locales', fn (Options $options): array => $this->getLocales())
            ->setAllowedTypes('locales', ['string[]'])

            // Per-locale name, slug, label and content, overriding the defaults above. A locale not in locales is added
            ->setDefault('translations', [])
            ->setAllowedTypes('translations', ['array'])
        ;
    }

    /**
     * @param array<string> $forms the Form codes, which the allowed type has checked are strings
     *
     * @return list<string>
     */
    private function normalizeForms(Options $options, array $forms): array
    {
        $unconfigured = array_diff($forms, $this->formCodes);
        if ([] !== $unconfigured) {
            throw new InvalidOptionsException(sprintf(
                'The Form(s) "%s" are not configured. The configured Forms are "%s".',
                implode('", "', $unconfigured),
                implode('", "', $this->formCodes),
            ));
        }

        return array_values($forms);
    }

    /**
     * Three words, capitalised, with no full stop
     */
    private function fakeTitle(): string
    {
        return rtrim($this->faker->sentence(3, false), '.');
    }

    /**
     * @return list<string>
     */
    private function getLocales(): array
    {
        $codes = [];

        /** @var LocaleInterface[] $locales */
        $locales = $this->localeRepository->findAll();
        foreach ($locales as $locale) {
            $code = $locale->getCode();
            if (null !== $code) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
