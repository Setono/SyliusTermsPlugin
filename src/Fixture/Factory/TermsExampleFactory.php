<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Fixture\Factory;

use Setono\SyliusTermsPlugin\Generator\TermSlugGeneratorInterface;
use Setono\SyliusTermsPlugin\Model\TermsInterface;
use Setono\SyliusTermsPlugin\Doctrine\ORM\TermsRepositoryInterface;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Locale\Model\LocaleInterface;
use Sylius\Component\Resource\Factory\FactoryInterface;
use Sylius\Bundle\CoreBundle\Fixture\Factory\AbstractExampleFactory;
use Sylius\Bundle\CoreBundle\Fixture\OptionsResolver\LazyOption;
use Sylius\Component\Channel\Repository\ChannelRepositoryInterface;
use Sylius\Component\Core\Formatter\StringInflector;
use Sylius\Component\Resource\Repository\RepositoryInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TermsExampleFactory extends AbstractExampleFactory
{
    /**
     * @var FactoryInterface
     */
    protected $termsFactory;

    /**
     * @var TermsRepositoryInterface
     */
    private $termsRepository;

    /**
     * @var RepositoryInterface
     */
    private $localeRepository;

    /**
     * @var ChannelRepositoryInterface
     */
    private $channelRepository;

    /**
     * @var TermSlugGeneratorInterface
     */
    private $termsSlugGenerator;

    /**
     * @var \Faker\Generator
     */
    private $faker;

    /**
     * @var OptionsResolver
     */
    private $optionsResolver;

    public function __construct(
        FactoryInterface $termsFactory,
        TermsRepositoryInterface $termsRepository,
        RepositoryInterface $localeRepository,
        ChannelRepositoryInterface $channelRepository,
        TermSlugGeneratorInterface $termsSlugGenerator
    ) {
        $this->termsFactory = $termsFactory;
        $this->termsRepository = $termsRepository;
        $this->localeRepository = $localeRepository;
        $this->channelRepository = $channelRepository;
        $this->termsSlugGenerator = $termsSlugGenerator;

        $this->faker = \Faker\Factory::create();
        $this->optionsResolver = new OptionsResolver();

        $this->configureOptions($this->optionsResolver);
    }

    /**
     * {@inheritdoc}
     */
    public function create(array $options = []): TermsInterface
    {
        $options = $this->optionsResolver->resolve($options);

        /** @var TermsInterface $terms */
        $terms = $this->termsRepository->findOneBy(['code' => $options['code']]);

        if (null === $terms) {
            /** @var TermsInterface $terms */
            $terms = $this->termsFactory->createNew();
        }

        $terms->setCode($options['code']);
        $terms->setChannel($options['channel']);

        // add translation for each defined locales
        foreach ($this->getLocales() as $localeCode) {
            $this->createTranslation($terms, $localeCode, $options);
        }

        // create or replace with custom translations
        foreach ($options['translations'] as $localeCode => $translationOptions) {
            $this->createTranslation($terms, $localeCode, $translationOptions);
        }

        $terms->setCreatedAt($options['created_at']);
        $terms->setUpdatedAt($options['updated_at']);

        return $terms;
    }

    protected function createTranslation(TermsInterface $terms, string $localeCode, array $options = []): void
    {
        $options = $this->optionsResolver->resolve($options);

        $terms->setCurrentLocale($localeCode);
        $terms->setFallbackLocale($localeCode);

        $terms->setName($options['name']);
        $terms->setExplanation($options['explanation']);
        $terms->setContent($options['content']);
        $terms->setSlug($options['slug'] ?: $this->termsSlugGenerator->generate($terms, $localeCode));
    }

    /**
     * {@inheritdoc}
     */
    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setDefault('name', function (Options $options): string {
                return $this->faker->words(3, true);
            })

            ->setDefault('code', function (Options $options): string {
                return StringInflector::nameToCode($options['name']);
            })

            ->setRequired('channel')
            ->setDefault('channel', LazyOption::randomOne($this->channelRepository))
            ->setAllowedTypes('channel', ['string', ChannelInterface::class])
            ->setNormalizer('channel', LazyOption::findOneBy($this->channelRepository, 'code'))

            ->setDefault('slug', null)

            ->setDefault('explanation', function (Options $options): string {
                return $this->faker->text(60); // @todo add link to this text
            })

            ->setDefault('content', function (Options $options): string {
                return $this->faker->paragraph;
            })

            ->setDefault('translations', [])
            ->setAllowedTypes('translations', ['array'])

            ->setDefault('created_at', null)
            ->setAllowedTypes('created_at', ['null', 'string', \DateTimeInterface::class])
            ->setNormalizer('created_at', self::getDateTimeNormalizer())

            ->setDefault('updated_at', null)
            ->setAllowedTypes('updated_at', ['null', 'string', \DateTimeInterface::class])
            ->setNormalizer('updated_at', self::getDateTimeNormalizer())
        ;
    }

    private function getLocales(): iterable
    {
        /** @var LocaleInterface[] $locales */
        $locales = $this->localeRepository->findAll();
        foreach ($locales as $locale) {
            yield $locale->getCode();
        }
    }

    /**
     * @return \Closure
     */
    private static function getDateTimeNormalizer(): \Closure
    {
        return function (Options $options, $previousValue) {
            if (null === $previousValue) {
                return $previousValue;
            }

            if (is_object($previousValue)) {
                return $previousValue;
            }

            return new \DateTime($previousValue);
        };
    }
}
