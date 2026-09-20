<?php

namespace App\Support;

/**
 * The colour a card type or a character carries, turned into something a card
 * face can print.
 *
 * Mirrored by resources/js/colour.js — the print sheet is rendered server side
 * and the preview in the browser, the same way the markup is, so change one
 * and change the other.
 *
 * Everything here is derived rather than stored: the designer picks a colour
 * and the ink over it, and the readable version on the card's cream body, are
 * worked out from it. A colour nobody picked is null and every caller falls
 * back to the dark head the cards always had.
 */
class Colour
{
    /** The card body: what a type label has to stay legible against. */
    public const PAPER = '#fdfcf9';

    /** The two inks a head band can take. */
    public const LIGHT_INK = '#fdfcf9';

    public const DARK_INK = '#1c1917';

    /** WCAG AA for the small, bold, wide-tracked type label. */
    private const READABLE_RATIO = 4.5;

    /** A picked colour as #rrggbb, or null for anything that is not one. */
    public static function normalise(?string $value): ?string
    {
        $value = strtolower(trim((string) $value));

        if (preg_match('/^#?([0-9a-f]{3})$/', $value, $m)) {
            [$r, $g, $b] = str_split($m[1]);

            return "#{$r}{$r}{$g}{$g}{$b}{$b}";
        }

        return preg_match('/^#?([0-9a-f]{6})$/', $value, $m) ? "#{$m[1]}" : null;
    }

    /** The ink that reads over these colours: whichever contrasts further. */
    public static function ink(string ...$colours): string
    {
        $colours = array_values(array_filter(array_map(self::normalise(...), $colours)));

        if ($colours === []) {
            return self::LIGHT_INK;
        }

        // A gradient is only as readable as its worst stop, so the ink is
        // chosen against the mean of them rather than against the first.
        $mean = array_sum(array_map(self::luminance(...), $colours)) / count($colours);

        return self::contrast($mean, self::luminance(self::LIGHT_INK)) >= self::contrast($mean, self::luminance(self::DARK_INK))
            ? self::LIGHT_INK
            : self::DARK_INK;
    }

    /**
     * The same colour, darkened until it reads on the card's cream body. A
     * pale yellow type label is the designer's choice and stays theirs on the
     * head band; in six-point tracked capitals it has to be legible.
     */
    public static function onPaper(string $colour): string
    {
        $colour = self::normalise($colour);

        if ($colour === null) {
            return self::DARK_INK;
        }

        $paper = self::luminance(self::PAPER);

        // 16 steps of 6% is enough to take any colour to black; stopping at the
        // first readable one keeps as much of the designer's hue as it can.
        for ($step = 0; $step <= 16; $step++) {
            $tried = self::darken($colour, $step * 0.06);

            if (self::contrast($paper, self::luminance($tried)) >= self::READABLE_RATIO) {
                return $tried;
            }
        }

        return self::DARK_INK;
    }

    /**
     * The ink the other way round, for a halo behind text on a band whose two
     * stops disagree about which ink reads. The arrow already does this on the
     * card's right edge, for the same reason.
     */
    public static function halo(string ...$colours): string
    {
        return self::ink(...$colours) === self::LIGHT_INK ? self::DARK_INK : self::LIGHT_INK;
    }

    /**
     * The head band's background. One colour is flat; two are a slight
     * gradient, which is what a character card asks for and what a split card
     * gets when its halves are different types — top colour at the top, the
     * way the halves sit.
     */
    public static function band(?string $from, ?string $to = null, string $angle = 'to bottom'): ?string
    {
        $from = self::normalise($from);
        $to = self::normalise($to);

        if ($from === null && $to === null) {
            return null;
        }

        $from ??= $to;
        $to ??= $from;

        return $from === $to ? $from : "linear-gradient({$angle}, {$from}, {$to})";
    }

    /**
     * The cost chip that sits in a coloured head: a darker shade of the head's
     * own colour, so the chip belongs to the card rather than to whatever the
     * head used to be. Its ink comes from ink() like any other band.
     */
    public static function chip(string $colour): string
    {
        return self::darken($colour, 0.25);
    }

    /** Mixed toward black by $amount, 0 to 1. */
    public static function darken(string $colour, float $amount): string
    {
        [$r, $g, $b] = self::rgb($colour);
        $keep = max(0.0, 1 - $amount);

        return sprintf('#%02x%02x%02x', (int) round($r * $keep), (int) round($g * $keep), (int) round($b * $keep));
    }

    /** WCAG relative luminance, 0 (black) to 1 (white). */
    public static function luminance(string $colour): float
    {
        [$r, $g, $b] = array_map(function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($colour));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** The WCAG contrast ratio between two luminances, 1 to 21. */
    public static function contrast(float $a, float $b): float
    {
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /** @return array{int, int, int} */
    private static function rgb(string $colour): array
    {
        $hex = ltrim(self::normalise($colour) ?? self::DARK_INK, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
