<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which cards of the chosen deck actually go on the sheet, and how many of
 * each.
 *
 * A print run is rarely the whole deck: one card comes back from the printer
 * smudged, a beat is still being rewritten, a single new card has to join a
 * deck that is already cut out. So the sheet can be told either what to leave
 * out or what is the only thing to print, and everything else follows.
 *
 * A card's own quantity is a fact about the deck, not about one print run —
 * the Kraken's Tide needs 3 Tentacle Lash. A run may still want more copies
 * of one card than the deck calls for (a spare, a replacement for one gone
 * missing), so a run can also say how many of a card to print; left unsaid,
 * a card prints as many copies as its quantity, same as always.
 *
 * A key is `group:id` — `entity:12`, `beat:3`, `town:7` — because ids belong to
 * their own tables and an entity card 12 and a player card 12 are two different
 * cards. All three travel in the query string like every other print setting,
 * so a run chosen once can be bookmarked.
 */
class PrintSelection
{
    /**
     * @param  list<string>  $only  the whole run, when it is given
     * @param  list<string>  $except  cards held back from an otherwise whole run
     * @param  array<string, int>  $quantities  a card's key to how many of it to print, when it differs from the deck's own count
     */
    public function __construct(
        public array $only = [],
        public array $except = [],
        public array $quantities = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::keys($request->input('only')),
            self::keys($request->input('except')),
            self::quantities($request->input('qty')),
        );
    }

    /**
     * A comma-separated list, or a repeated parameter — either is a list of
     * keys. Anything that is not shaped like a key is dropped rather than
     * matched against, so a mangled URL prints the deck instead of nothing.
     *
     * @return list<string>
     */
    private static function keys(mixed $value): array
    {
        $parts = is_array($value)
            ? $value
            : explode(',', (string) $value);

        $keys = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if (preg_match('/^[a-z]+:[0-9]+$/', $part) === 1) {
                $keys[$part] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * Keys mapped to a positive count — `qty[entity:12]=6` — for a run that
     * wants more (or fewer, but never none: that is what the checkbox is for)
     * copies of a card than its own quantity. Anything not shaped like a key,
     * or not a positive number, is dropped rather than matched, same as
     * `only` and `except`.
     *
     * @return array<string, int>
     */
    private static function quantities(mixed $value): array
    {
        $quantities = [];

        foreach ((array) $value as $key => $count) {
            if (is_string($key) && preg_match('/^[a-z]+:[0-9]+$/', $key) === 1 && is_numeric($count) && (int) $count > 0) {
                $quantities[$key] = min((int) $count, 999);
            }
        }

        return $quantities;
    }

    /** How many copies of this card the run prints: the override, or the deck's own count. */
    public function quantityFor(string $key, int $required): int
    {
        return $this->quantities[$key] ?? $required;
    }

    /**
     * Is this card in the run? A list of what to print wins over a list of what
     * to hold back: saying "only these three" is a complete answer, so nothing
     * else needs consulting.
     */
    public function includes(string $key): bool
    {
        if ($this->only !== []) {
            return in_array($key, $this->only, true);
        }

        return ! in_array($key, $this->except, true);
    }

    /** True when nothing has been picked and the whole deck prints. */
    public function isWholeDeck(): bool
    {
        return $this->only === [] && $this->except === [];
    }

    public function toArray(): array
    {
        return [
            'only' => implode(',', $this->only),
            'except' => implode(',', $this->except),
            'qty' => $this->quantities,
        ];
    }
}
