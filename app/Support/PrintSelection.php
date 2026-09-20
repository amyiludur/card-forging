<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Which cards of the chosen deck actually go on the sheet.
 *
 * A print run is rarely the whole deck: one card comes back from the printer
 * smudged, a beat is still being rewritten, a single new card has to join a
 * deck that is already cut out. So the sheet can be told either what to leave
 * out or what is the only thing to print, and everything else follows.
 *
 * A key is `group:id` — `entity:12`, `beat:3`, `town:7` — because ids belong to
 * their own tables and an entity card 12 and a player card 12 are two different
 * cards. Both lists travel in the query string like every other print setting,
 * so a run chosen once can be bookmarked.
 */
class PrintSelection
{
    /**
     * @param  list<string>  $only  the whole run, when it is given
     * @param  list<string>  $except  cards held back from an otherwise whole run
     */
    public function __construct(
        public array $only = [],
        public array $except = [],
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            self::keys($request->input('only')),
            self::keys($request->input('except')),
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
        ];
    }
}
