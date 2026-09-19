<?php

namespace App\Support;

use App\Models\RulesConfig;

/**
 * The plain-text markup used in card and rules text.
 *
 * Two forms, both deliberately simple so the designer can type them by hand:
 *
 *   {omen} {gold} {damage} {dread} {health}   icons
 *   {config:startingOmen}                     a value from the rules config
 *
 * A newline is a line break on the card. Nothing else about the text is
 * formatting: there is no bold, no lists, no markdown.
 *
 * A config reference renders the current number, so changing a tunable number
 * in one place updates every card and every paragraph of the rulebook.
 */
class Markup
{
    /**
     * Icon token => the character it falls back to.
     *
     * These are what {@see toPlain()} writes, so a plain-text export or a diff
     * of the design folder still reads as text. On screen and on the printed
     * card the same tokens render as {@see Icons} SVG instead.
     */
    public const ICONS = [
        'omen' => '◆',
        'gold' => '●',
        'damage' => '✦',
        'dread' => '▲',
        'health' => '♥',
    ];

    public function __construct(private array $config = [])
    {
    }

    public static function make(): self
    {
        return new self(RulesConfig::map());
    }

    /**
     * Render markup to HTML. $autoIcons additionally turns "2 omen" into
     * "2 {omen}" at render time, without touching the stored text.
     */
    public function toHtml(string $text, bool $autoIcons = false): string
    {
        if ($autoIcons) {
            $text = $this->autoIconise($text);
        }

        $escaped = e($text);

        // A line break the designer typed is a line break on the card. Done on
        // the escaped text and before any token becomes real HTML, so it can
        // never land inside generated markup.
        $escaped = preg_replace('/\r\n|\r|\n/', '<br>', $escaped);

        $escaped = preg_replace_callback('/\{config:([A-Za-z0-9_]+)\}/', function (array $m): string {
            $value = $this->configValue($m[1]);

            return $value === null
                ? '<span class="markup-missing">?'.e($m[1]).'</span>'
                : '<span class="markup-config">'.e($value).'</span>';
        }, $escaped);

        return preg_replace_callback('/\{([a-z]+)\}/', function (array $m): string {
            if (! isset(self::ICONS[$m[1]])) {
                return $m[0];
            }

            // Inline SVG, so the printed sheet keeps its icons when Chromium
            // renders it from file://. The character is the fallback.
            $body = Icons::has($m[1])
                ? Icons::svg($m[1], 'icon')
                : self::ICONS[$m[1]];

            return '<span class="markup-icon markup-icon-'.e($m[1]).'" title="'.e($m[1]).'">'.$body.'</span>';
        }, $escaped);
    }

    /** Render markup to plain text, for exports and diffs. */
    public function toPlain(string $text): string
    {
        $text = preg_replace_callback(
            '/\{config:([A-Za-z0-9_]+)\}/',
            fn (array $m): string => $this->configValue($m[1]) ?? $m[0],
            $text
        );

        return preg_replace_callback(
            '/\{([a-z]+)\}/',
            fn (array $m): string => self::ICONS[$m[1]] ?? $m[0],
            $text
        );
    }

    /** Config keys a piece of text depends on, so the UI can warn before a rename. */
    public function references(string $text): array
    {
        preg_match_all('/\{config:([A-Za-z0-9_]+)\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    private function autoIconise(string $text): string
    {
        return preg_replace_callback(
            '/\b(\d+)\s+(omen|gold|damage)\b/i',
            fn (array $m): string => $m[1].' {'.strtolower($m[2]).'}',
            $text
        );
    }

    private function configValue(string $key): ?string
    {
        if (! array_key_exists($key, $this->config)) {
            return null;
        }

        $value = $this->config[$key];

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            // A list is a range ("0 to 2"); a map prints its parts ("20 signature, 20 domain").
            return array_is_list($value)
                ? implode(' to ', $value)
                : implode(', ', array_map(fn ($v, $k) => "{$v} {$k}", $value, array_keys($value)));
        }

        return $value === null ? '—' : (string) $value;
    }
}
