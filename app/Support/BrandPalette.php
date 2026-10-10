<?php

namespace App\Support;

/**
 * The colors of the agency and the scales the panels are built from.
 *
 * Red is the main color and pink its light tone; purple is the dark of the brand and cream its
 * "white". The gray scale runs from the cream to the purple, so page backgrounds, borders and
 * text all come from the palette and nothing in the panels is a neutral gray.
 */
final class BrandPalette
{
    public const RED = '#f4193e';

    public const PINK = '#ffb1bb';

    public const PURPLE = '#2c0735';

    public const CREAM = '#fff7e9';

    public const YELLOW = '#ffedbb';

    /**
     * Buttons, links and highlights: the red at 500 and the pink at 200.
     *
     * @return array<int, string>
     */
    public static function primary(): array
    {
        return [
            50 => '#fff1f3',
            100 => '#ffdce2',
            200 => self::PINK,
            300 => '#ff8197',
            400 => '#fb4b69',
            500 => self::RED,
            600 => '#d40a30',
            700 => '#ae0a2b',
            800 => '#8d0f29',
            900 => '#741228',
            950 => '#42030f',
        ];
    }

    /**
     * Backgrounds, borders and text, from the cream to the purple. In the dark theme the
     * purple is the page and the next shade the cards.
     *
     * @return array<int, string>
     */
    public static function gray(): array
    {
        return [
            50 => self::CREAM,
            100 => '#fbeee0',
            200 => '#efe1d6',
            300 => '#d8c8d4',
            400 => '#a88fb0',
            500 => '#7c6484',
            600 => '#5c4465',
            700 => '#45304f',
            800 => '#3a1f45',
            900 => '#331040',
            950 => self::PURPLE,
        ];
    }

    /**
     * Warnings: the yellow of the brand is the light tone used for their backgrounds.
     *
     * @return array<int, string>
     */
    public static function warning(): array
    {
        return [
            50 => '#fff9e6',
            100 => self::YELLOW,
            200 => '#ffe08a',
            300 => '#ffcd52',
            400 => '#fbb824',
            500 => '#f59e0b',
            600 => '#d97706',
            700 => '#b45309',
            800 => '#92400e',
            900 => '#78350f',
            950 => '#451a03',
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function colors(): array
    {
        return [
            'primary' => self::primary(),
            'gray' => self::gray(),
            'warning' => self::warning(),
        ];
    }
}
