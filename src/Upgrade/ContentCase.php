<?php

declare(strict_types=1);

namespace Setono\SyliusTermsPlugin\Upgrade;

/**
 * A way 2.x Content, which is HTML, may render differently as 3.0's Markdown that allows HTML (ADR 0005). Part of the
 * upgrade from 2.x, which 4.0 removes
 */
enum ContentCase
{
    case IndentedCode;
    case ListOrHeading;
    case ClassOrStyle;

    public function describe(): string
    {
        return match ($this) {
            self::IndentedCode => 'A line indented by four spaces after a blank line becomes a code block',
            self::ListOrHeading => 'Plain text that starts with 1., - or # becomes a list or a heading',
            self::ClassOrStyle => 'Styling held in class and style attributes is dropped',
        };
    }
}
