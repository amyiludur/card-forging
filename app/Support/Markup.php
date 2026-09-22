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
 *   {perPlayer}                               the sixth icon, "per player"
 *   {unique} {fired} {bottom-draw}            keywords the designer defined
 *   {config:startingOmen}                     a value from the rules config
 *   {dreadRule}                               the scenario's own Dread rule
 *   {dreadAmount}                             the Dread that scenario starts on
 *   {this}                                    the name of the card it is on
 *
 * A newline is a line break on the card. Nothing else about the text is
 * formatting: there is no bold, no lists, no markdown.
 *
 * A config reference renders the current number, so changing a tunable number
 * in one place updates every card and every paragraph of the rulebook.
 *
 * {dreadRule} does the same for a whole sentence: a card that belongs to a
 * scenario can print that scenario's Dread effect rather than repeat it, so
 * editing the scenario edits every card that quotes it. {dreadAmount} is its
 * pair for the number the dial starts on — and, like every other number on a
 * printed card, it prints the designer's equation rather than working it out,
 * because a card cannot know how many people are at the table.
 *
 * {this} is the card's own name, so text can say "Discard {this}" and keep
 * saying the right thing when the card is renamed or copied.
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
        // The one that stands for a count rather than a thing, so its fallback
        // is the words rather than a symbol: "1 + 1 per player" is how a plain
        // text export has to read.
        'perPlayer' => 'per player',
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
     * The Dread that scenario starts on. camelCase for the same reason as
     * {dreadRule}, and written into the text the same way: the value is the
     * designer's number or their equation, so once it is in the text it
     * renders like anything else they typed — an equation's player count
     * draws as the {perPlayer} icon rather than as a word.
     */
    private const DREAD_AMOUNT = '/\{dreadAmount\}/';

    /**
     * {perPlayer}, the icon an equation's player count draws as.
     *
     * camelCase, so like {dreadRule} it needs its own pattern: the token regex
     * above is lowercase only, which is exactly what stops a keyword the
     * designer names from ever colliding with it.
     */
    private const PER_PLAYER = '/\{perPlayer\}/';

    /**
     * The name of the card the text is on. Lowercase, so unlike {dreadRule}
     * it does share the keyword namespace: {@see reservedTokens()} is what
     * stops a keyword from ever being called "this".
     */
    public const THIS = 'this';

    private const THIS_PATTERN = '/\{this\}/';

    /**
     * @param  array  $config  key => value, the tunable numbers
     * @param  array  $keywords  token => the keyword's name, icon and definition
     * @param  string|null  $dreadRule  the Dread effect {dreadRule} writes out
     * @param  string|null  $dreadAmount  the starting Dread {dreadAmount} writes out
     */
    public function __construct(
        private array $config = [],
        private array $keywords = [],
        private ?string $dreadRule = null,
        private ?string $dreadAmount = null,
        private ?string $cardName = null,
    ) {
    }

    public static function make(): self
    {
        return new self(RulesConfig::map(), Keyword::markupMap());
    }

    /**
     * The same markup, reading one scenario's Dread: the rule {dreadRule}
     * writes out and the number {dreadAmount} does.
     *
     * Both at once, so neither can be set without the other. Set per card
     * rather than per page, because a card belongs to a scenario or to a
     * module and never both: a module card is played with whichever scenario
     * the table chose, so it has no one rule and no one number to print.
     *
     * @param  string|null  $amount  the starting Dread as card text, so an
     *                               equation arrives with its player count as
     *                               the {perPlayer} token: PlayerScaled::markup()
     */
    public function withDread(?string $rule, ?string $amount): self
    {
        return new self($this->config, $this->keywords, $rule, $amount, $this->cardName);
    }

    /**
     * The same markup, on one card: {this} writes out that card's name.
     *
     * Set per card, like the Dread pair, so a page listing many cards gives
     * each its own name. Null is text that is on no card — a rules page, a
     * scenario's Dread effect on its own — where {this} is reported.
     */
    public function withName(?string $name): self
    {
        return new self($this->config, $this->keywords, $this->dreadRule, $this->dreadAmount, $name);
    }

    /**
     * Tokens a keyword may not take: the icons, which always win, and {this},
     * which always means the card's name.
     *
     * @return list<string>
     */
    public static function reservedTokens(): array
    {
        return [...array_keys(self::ICONS), self::THIS];
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
        // After the rule, so a Dread effect that quotes the number gets it.
        $text = $this->expandDreadAmount($text);
        // After both, so a Dread effect saying {this} names each card it is
        // quoted on.
        $text = $this->expandName($text);

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

        // Same for the number: a module card has no scenario to read one off.
        $escaped = preg_replace(
            self::DREAD_AMOUNT,
            '<span class="markup-missing">?dreadAmount</span>',
            $escaped
        );

        // And for the name: text that is on no card, or a card with no name
        // yet. Before the token pass, so it never reads as an unknown token.
        $escaped = preg_replace(
            self::THIS_PATTERN,
            '<span class="markup-missing">?this</span>',
            $escaped
        );

        // The sixth icon, drawn here rather than in the token pass below
        // because its name is camelCase and that pass is lowercase only.
        $escaped = preg_replace_callback(
            self::PER_PLAYER,
            fn (): string => $this->iconHtml('perPlayer'),
            $escaped
        );

        $escaped = preg_replace_callback('/\{config:([A-Za-z0-9_]+)\}/', function (array $m): string {
            $value = $this->configValue($m[1]);

            return $value === null
                ? '<span class="markup-missing">?'.e($m[1]).'</span>'
                // A tunable number written as an equation prints its player
                // count as the icon, the same as one typed into card text:
                // {config:startingOmen} reads "1 {perPlayer}", not the word.
                : '<span class="markup-config">'.$this->scaledText($value).'</span>';
        }, $escaped);

        return preg_replace_callback(self::TOKEN, function (array $m): string {
            if (isset(self::ICONS[$m[1]])) {
                return $this->iconHtml($m[1]);
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
     * A config value, escaped, with any mention of the player count drawn as
     * the icon. The token has no HTML-special characters, so substituting it
     * after escaping cannot disturb the escaping.
     */
    private function scaledText(string $value): string
    {
        return preg_replace_callback(
            self::PER_PLAYER,
            fn (): string => $this->iconHtml('perPlayer'),
            e(PlayerScaled::make(null, $value)->markup())
        );
    }

    /**
     * One icon token. Inline SVG, so the printed sheet keeps its icons when
     * Chromium renders it from file://; the {@see self::ICONS} fallback is what
     * shows when an icon of that name has not been generated.
     */
    private function iconHtml(string $name): string
    {
        $body = Icons::has($name) ? Icons::svg($name, 'icon') : e(self::ICONS[$name]);

        return '<span class="markup-icon markup-icon-'.e($name).'" title="'.e($name).'">'.$body.'</span>';
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
        // As in toHtml(), except that an unfilled {dreadRule}, {dreadAmount} or
        // {this} stays as typed: there is no red span in a design-folder diff, and the
        // designer's own words survive the way an unknown token does.
        $text = $this->expandDreadRule($text);
        $text = $this->expandDreadAmount($text);
        $text = $this->expandName($text);

        $text = preg_replace_callback(
            self::PER_PLAYER,
            fn (): string => self::ICONS['perPlayer'],
            $text
        );

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

    /**
     * Write the scenario's starting Dread into the text.
     *
     * The same substitution {@see expandDreadRule()} does, and for the same
     * reason: what goes in is the designer's own number or equation, so the
     * passes after this one draw it exactly as they would draw it typed into
     * the card by hand. Never rescanned, so a value naming the token is
     * reported rather than looped on — which no equation can be, but the rule
     * holds either way.
     */
    private function expandDreadAmount(string $text): string
    {
        $amount = trim((string) $this->dreadAmount);

        return $amount === ''
            ? $text
            : preg_replace_callback(self::DREAD_AMOUNT, fn (): string => $amount, $text);
    }

    /**
     * Write the card's name into the text.
     *
     * The same substitution the Dread pair gets: the name becomes part of the
     * text, is escaped with it, and is never rescanned for {this}.
     */
    private function expandName(string $text): string
    {
        $name = trim((string) $this->cardName);

        return $name === ''
            ? $text
            : preg_replace_callback(self::THIS_PATTERN, fn (): string => $name, $text);
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
