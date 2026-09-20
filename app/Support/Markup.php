<?php

namespace App\Support;

use App\Models\Keyword;
use App\Models\RulesConfig;

/**
 * The plain-text markup used in card and rules text.
 *
 * Four forms, all deliberately simple so the designer can type them by hand:
 *
 *   {omen} {gold} {damage} {dread} {health}   icons
 *   {unique} {fired} {bottom-draw}            keywords the designer defined
 *   {config:startingOmen}                     a value from the rules config
 *   {dreadRule}                               the scenario's own Dread rule
 *
 * A newline is a line break on the card. Nothing else about the text is
 * formatting: there is no bold, no lists, no markdown.
 *
 * A config reference renders the current number, so changing a tunable number
 * in one place updates every card and every paragraph of the rulebook.
 *
 * {dreadRule} does the same for a whole sentence: a card that belongs to a
 * scenario can print that scenario's Dread effect rather than repeat it, so
 * editing the scenario edits every card that quotes it.
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

    /**
     * A token is lowercase letters, digits and hyphens, so a keyword can be
     * {bottom-draw} as well as {unique}.
     */
    private const TOKEN = '/\{([a-z][a-z0-9-]*)\}/';

    /**
     * The scenario's Dread rule. camelCase, so it is not a token in the sense
     * above and can never collide with a keyword the designer names: like
     * {config:...} it points at a field, not at a symbol on the card.
     */
    private const DREAD_RULE = '/\{dreadRule\}/';

    /**
     * @param  array  $config  key => value, the tunable numbers
     * @param  array  $keywords  token => the keyword's name, icon and definition
     * @param  string|null  $dreadRule  the Dread effect {dreadRule} writes out
     */
    public function __construct(
        private array $config = [],
        private array $keywords = [],
        private ?string $dreadRule = null,
    ) {
    }

    public static function make(): self
    {
        return new self(RulesConfig::map(), Keyword::markupMap());
    }

    /**
     * The same markup, reading one scenario's Dread rule.
     *
     * Set per card rather than per page, because a card belongs to a scenario
     * or to a module and never both: a module card is played with whichever
     * scenario the table chose, so it has no one rule to print.
     */
    public function withDreadRule(?string $rule): self
    {
        return new self($this->config, $this->keywords, $rule);
    }

    /**
     * Render markup to HTML. $autoIcons additionally turns "2 omen" into
     * "2 {omen}" at render time, without touching the stored text.
     */
    public function toHtml(string $text, bool $autoIcons = false): string
    {
        // The Dread rule goes in as the designer wrote it, before anything else
        // runs, so its own icons, keywords and numbers render on the card
        // exactly as they do on the scenario page.
        $text = $this->expandDreadRule($text);

        if ($autoIcons) {
            $text = $this->autoIconise($text);
        }

        $escaped = e($text);

        // A line break the designer typed is a line break on the card. Done on
        // the escaped text and before any token becomes real HTML, so it can
        // never land inside generated markup.
        $escaped = preg_replace('/\r\n|\r|\n/', '<br>', $escaped);

        // Whatever {dreadRule} is left is one nothing filled: a card with no
        // scenario, or a Dread rule that named itself. Marked here, while the
        // only markup in the string is those <br>s, for the same reason.
        $escaped = preg_replace(
            self::DREAD_RULE,
            '<span class="markup-missing">?dreadRule</span>',
            $escaped
        );

        $escaped = preg_replace_callback('/\{config:([A-Za-z0-9_]+)\}/', function (array $m): string {
            $value = $this->configValue($m[1]);

            return $value === null
                ? '<span class="markup-missing">?'.e($m[1]).'</span>'
                : '<span class="markup-config">'.e($value).'</span>';
        }, $escaped);

        return preg_replace_callback(self::TOKEN, function (array $m): string {
            if (isset(self::ICONS[$m[1]])) {
                // Inline SVG, so the printed sheet keeps its icons when Chromium
                // renders it from file://. The character is the fallback.
                $body = Icons::has($m[1])
                    ? Icons::svg($m[1], 'icon')
                    : self::ICONS[$m[1]];

                return '<span class="markup-icon markup-icon-'.e($m[1]).'" title="'.e($m[1]).'">'.$body.'</span>';
            }

            if (isset($this->keywords[$m[1]])) {
                return $this->keywordHtml($m[1], $this->keywords[$m[1]]);
            }

            // An unknown token is left as typed: a token is only wrong once the
            // designer says what it means.
            return $m[0];
        }, $escaped);
    }

    /**
     * One keyword: its icon, then its name in the small caps card games print
     * keywords in. Mirrored by renderMarkup() in resources/js/markup.js, so the
     * editor, the preview and the print sheet draw the same thing.
     */
    private function keywordHtml(string $token, array $keyword): string
    {
        $icon = $keyword['icon'] ?? null;
        $svg = $icon !== null && Icons::has($icon) ? Icons::svg($icon, 'icon') : '';

        // The name is the fallback as well as the usual case: a keyword set to
        // print its icon alone still has to show something when it has none.
        $name = ($keyword['show_name'] ?? true) || $svg === ''
            ? e($keyword['name'])
            : '';

        $body = $svg !== '' && $name !== '' ? $svg.'&nbsp;'.$name : $svg.$name;

        $title = ($keyword['description'] ?? null)
            ? $keyword['name'].' — '.$keyword['description']
            : $keyword['name'];

        $classes = 'markup-keyword markup-keyword-'.e($token)
            .(($keyword['is_placeholder'] ?? false) ? ' markup-keyword-placeholder' : '');

        return '<span class="'.$classes.'" title="'.e($title).'">'.$body.'</span>';
    }

    /** Render markup to plain text, for exports and diffs. */
    public function toPlain(string $text): string
    {
        // As in toHtml(), except that an unfilled {dreadRule} stays as typed:
        // there is no red span in a design-folder diff, and the designer's own
        // words survive the way an unknown token does.
        $text = $this->expandDreadRule($text);

        $text = preg_replace_callback(
            '/\{config:([A-Za-z0-9_]+)\}/',
            fn (array $m): string => $this->configValue($m[1]) ?? $m[0],
            $text
        );

        return preg_replace_callback(
            self::TOKEN,
            fn (array $m): string => self::ICONS[$m[1]] ?? $this->keywords[$m[1]]['plain'] ?? $m[0],
            $text
        );
    }

    /** Keyword tokens a piece of text uses, so the UI can say what a rename costs. */
    public function keywordReferences(string $text): array
    {
        preg_match_all(self::TOKEN, $text, $matches);

        return array_values(array_unique(array_filter(
            $matches[1],
            fn (string $token): bool => isset($this->keywords[$token])
        )));
    }

    /** Config keys a piece of text depends on, so the UI can warn before a rename. */
    public function references(string $text): array
    {
        preg_match_all('/\{config:([A-Za-z0-9_]+)\}/', $text, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Write the scenario's Dread rule into the text.
     *
     * Substituted, not rendered and spliced in: the rule becomes part of the
     * card's text and every later pass treats it as such. The replacement is
     * never rescanned, so a rule that itself says {dreadRule} is reported
     * rather than expanded, and there is no loop to guard against.
     */
    private function expandDreadRule(string $text): string
    {
        $rule = trim((string) $this->dreadRule);

        // A callback, so a rule containing $ or \ is written out as typed.
        return $rule === ''
            ? $text
            : preg_replace_callback(self::DREAD_RULE, fn (): string => $rule, $text);
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
