<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

/**
 * Validates that every required Terms shown on the Form in the current channel and locale is ticked. The value is
 * the list of the ticked Terms' codes; null counts as none ticked. Each unticked required Terms raises a violation at
 * the path [<Terms code>]
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class RequiredTermsTicked extends Constraint
{
    public const NOT_TICKED_ERROR = 'b6f8d1a4-1c1e-4b55-9a37-2b3f6e0c4d71';

    protected const ERROR_NAMES = [
        self::NOT_TICKED_ERROR => 'NOT_TICKED_ERROR',
    ];

    public string $message = 'setono_sylius_terms.terms_checkbox.required';

    /**
     * @param string $form the code of the Form whose Terms are checked
     * @param list<string>|null $groups
     */
    #[HasNamedArguments]
    public function __construct(
        public string $form,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
