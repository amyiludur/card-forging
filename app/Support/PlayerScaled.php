<?php

namespace App\Support;

/**
 * A number that may be written as an equation counting the players.
 *
 * Every number the designer can scale — a scenario's starting Dread, a story
 * beat's Dread change, a character's health, hand size and gold, a tunable
 * number — is one of these. It is either a plain number, exactly as it always
 * was, or an equation naming {@see self::IDENTIFIER}:
 *
 *     2                 a plain number, unchanged
 *     1 + 1perPlayer    the Kraken's starting Dread
 *     2 * 1perPlayer    twice a player each
 *     3 + 2(perPlayer)  parentheses and implicit multiplication both work
 *
 * The equation is written as the designer typed it. It is printed on the card
 * as typed, because a printed card cannot know how many people are sitting at
 * the table, and it is only ever worked out into a number where a player count
 * actually exists — the playtest table, which knows who is playing.
 *
 * There is no division. A number of players does not divide into halves, and a
 * rounding rule would be a decision about the game rather than about the tool,
 * so a `/` is reported as something this cannot read rather than guessed at.
 *
 * resources/js/playerScaled.js is the browser half of this. Change one and
 * change the other.
 */
final class PlayerScaled
{
    /** What the player count is called inside an equation. */
    public const IDENTIFIER = 'perPlayer';

    /**
     * One mention of the player count. Deliberately not \b-anchored on the
     * left: the designer writes 1perPlayer, and a digit and a letter are both
     * word characters, so \b would not find it there. Case-insensitive, so
     * perplayer reads the same — the stored equation keeps whatever was typed
     * and only what is drawn is made canonical.
     */
    private const MENTION = '/(?<![A-Za-z_])'.self::IDENTIFIER.'(?![A-Za-z0-9_])/i';

    /**
     * The same, as the markup token that draws the icon. Both are accepted on
     * the way in, so an equation pasted out of card text still reads.
     */
    public const TOKEN = '{perPlayer}';

    private function __construct(
        /** The plain number: what this is worth when no equation is written. */
        public readonly ?int $number,
        /** The equation as typed, or null when this is a plain number. */
        public readonly ?string $equation,
        /** Why the equation cannot be read, or null when it can. */
        public readonly ?string $error,
    ) {
    }

    /**
     * @param  int|string|null  $number  the plain number, used when no equation is written
     * @param  string|null  $equation  the equation the designer typed, if any
     */
    public static function make(int|string|null $number, ?string $equation = null): self
    {
        $written = trim((string) $equation);

        if ($written === '') {
            return new self(is_numeric($number) ? (int) $number : null, null, null);
        }

        return new self(
            is_numeric($number) ? (int) $number : null,
            $written,
            self::validate($written),
        );
    }

    /**
     * One value out of a design file, where a number and an equation share a
     * key: 2 is the number, "1 + 1perPlayer" the equation. Board card health
     * has held either for the same reason since v1.
     */
    public static function fromDesign(mixed $value, int $default): self
    {
        if (is_int($value) || (is_string($value) && ctype_digit(trim($value)))) {
            return self::make((int) $value);
        }

        return is_string($value) && trim($value) !== ''
            ? self::make($default, $value)
            : self::make($default);
    }

    /** True once an equation is written that actually counts the players. */
    public function isScaled(): bool
    {
        return $this->equation !== null && self::mentionsPerPlayer($this->equation);
    }

    /**
     * What this is worth at a table of this many players, or null when there is
     * no answer: an equation that cannot be read, or no number written at all.
     */
    public function at(?int $players): ?int
    {
        if ($this->equation === null) {
            return $this->number;
        }

        if ($this->error !== null || $players === null) {
            return null;
        }

        return self::evaluate($this->equation, $players);
    }

    /**
     * This value as card text: the equation with the player count written as
     * the {perPlayer} token, so it renders through the markup like any other
     * icon and the preview and the print sheet draw the same thing.
     */
    public function markup(): string
    {
        if ($this->equation === null) {
            return (string) ($this->number ?? '');
        }

        return preg_replace(
            '/\{?(?<![A-Za-z_])'.self::IDENTIFIER.'(?![A-Za-z0-9_])\}?/i',
            self::TOKEN,
            $this->equation
        );
    }

    /** The same for a plain-text export or a diff: the equation as typed. */
    public function plain(): string
    {
        return $this->equation ?? (string) ($this->number ?? '');
    }

    /**
     * What a design file should hold: the number when it is one, the equation
     * when there is one. One key either way, so a file nobody has scaled comes
     * back out byte for byte.
     */
    public function forDesign(): int|string|null
    {
        return $this->equation ?? $this->number;
    }

    /** True when a piece of text names the player count. */
    public static function mentionsPerPlayer(string $text): bool
    {
        return preg_match(self::MENTION, $text) === 1;
    }

    /**
     * Why this equation cannot be read, or null when it can.
     *
     * Checked by evaluating it at one player: a table of one is as good as any
     * for finding out whether the thing parses.
     */
    public static function validate(string $equation): ?string
    {
        // Nothing written is not a broken equation, it is no equation: every
        // caller treats an empty field as "this is a plain number", and the
        // browser half says the same.
        if (trim($equation) === '') {
            return null;
        }

        try {
            (new self(null, null, null))->run($equation, 1);

            return null;
        } catch (\InvalidArgumentException $e) {
            return $e->getMessage();
        }
    }

    /** The equation at a table of this many players, or null when it cannot be read. */
    public static function evaluate(string $equation, int $players): ?int
    {
        try {
            return (new self(null, null, null))->run($equation, $players);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The whole of the arithmetic: integers, the player count, + - * and
     * parentheses, with a number next to a bracket or the player count meaning
     * multiplication. A recursive descent parser rather than eval(), because
     * this runs on text the designer types.
     *
     * @throws \InvalidArgumentException when the text is not an equation this reads
     */
    private function run(string $equation, int $players): int
    {
        $tokens = $this->lex($equation);
        $at = 0;

        $value = $this->sum($tokens, $at, $players);

        if ($at < count($tokens)) {
            throw new \InvalidArgumentException("Unexpected '{$tokens[$at]}'.");
        }

        return $value;
    }

    /** @return list<string> */
    private function lex(string $equation): array
    {
        // The token form is accepted as well as the bare word, so an equation
        // copied out of card text still reads.
        $text = str_replace(['{', '}'], '', $equation);

        preg_match_all('/\s*(\d+|'.self::IDENTIFIER.'|[-+*()])|\s*(\S)/i', $text, $matches, PREG_SET_ORDER);

        $tokens = [];

        foreach ($matches as $match) {
            if (($match[2] ?? '') !== '') {
                throw new \InvalidArgumentException("'{$match[2]}' is not something an equation can use.");
            }

            // perplayer and perPlayer are the same word; the canonical spelling
            // is what the parser below compares against.
            $tokens[] = strcasecmp($match[1], self::IDENTIFIER) === 0 ? self::IDENTIFIER : $match[1];
        }

        if ($tokens === []) {
            throw new \InvalidArgumentException('The equation is empty.');
        }

        return $tokens;
    }

    /** @param  list<string>  $tokens */
    private function sum(array $tokens, int &$at, int $players): int
    {
        $value = $this->product($tokens, $at, $players);

        while (($tokens[$at] ?? null) === '+' || ($tokens[$at] ?? null) === '-') {
            $operator = $tokens[$at++];
            $right = $this->product($tokens, $at, $players);
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    /** @param  list<string>  $tokens */
    private function product(array $tokens, int &$at, int $players): int
    {
        $value = $this->term($tokens, $at, $players);

        while (true) {
            if (($tokens[$at] ?? null) === '*') {
                $at++;
                $value *= $this->term($tokens, $at, $players);

                continue;
            }

            // 1perPlayer and 2(1 + perPlayer): a term straight after a term is
            // a multiplication, which is how the designer writes these.
            $next = $tokens[$at] ?? null;

            if ($next !== null && ($next === '(' || $next === self::IDENTIFIER || ctype_digit($next))) {
                $value *= $this->term($tokens, $at, $players);

                continue;
            }

            return $value;
        }
    }

    /** @param  list<string>  $tokens */
    private function term(array $tokens, int &$at, int $players): int
    {
        $token = $tokens[$at] ?? null;

        if ($token === null) {
            throw new \InvalidArgumentException('The equation stops before it says what to do.');
        }

        if ($token === '-') {
            $at++;

            return -$this->term($tokens, $at, $players);
        }

        if ($token === '+') {
            $at++;

            return $this->term($tokens, $at, $players);
        }

        if ($token === '(') {
            $at++;
            $value = $this->sum($tokens, $at, $players);

            if (($tokens[$at] ?? null) !== ')') {
                throw new \InvalidArgumentException('A bracket is left open.');
            }

            $at++;

            return $value;
        }

        if ($token === self::IDENTIFIER) {
            $at++;

            return $players;
        }

        if (ctype_digit($token)) {
            $at++;

            return (int) $token;
        }

        throw new \InvalidArgumentException("Unexpected '{$token}'.");
    }
}
