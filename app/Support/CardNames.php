<?php

namespace App\Support;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\Domain;
use App\Models\EntityCard;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Models\TownAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * {card:tentacle-lash} — one card naming another.
 *
 * The key is the card's name as a slug, because that is the one thing every
 * card kind has and the one thing a design file identifies a card by: an
 * entity card has no id that survives an import. Two cards can share a name
 * (two characters may each have a Lucky Coin), so a reference can be written
 * with its owner, {card:gunslinger/lucky-coin}, to say which.
 *
 * A reference prints the card's name as it is now. Renaming a card in the
 * editor rewrites every reference to it ({@see renamed()}), so the text keeps
 * naming the same card. An import never does: the folder is the source of
 * truth, so a card renamed by hand in a file leaves its references printing
 * a red ?card:old-name — reported, never quietly pointed somewhere else.
 *
 * Mirrored in the browser by resolveCard() in resources/js/markup.js, which
 * reads {@see map()} through Inertia's shared markup.cards prop.
 */
class CardNames
{
    /** A reference: an optional owner and a slash, then the card's key. */
    public const PATTERN = '/\{card:(?:([a-z0-9-]+)\/)?([a-z0-9-]+)\}/';

    /**
     * Every text column that can hold card markup, so a rename can find the
     * references to rewrite. A column that never says {card:…} costs a LIKE.
     */
    private const TEXT_COLUMNS = [
        'entity_card_faces' => ['text'],
        'entity_cards' => ['notes'],
        'board_cards' => ['text'],
        'town_actions' => ['effect', 'note'],
        'story_beats' => ['on_reach', 'advance', 'on_advance'],
        'player_cards' => ['text'],
        'tale_cards' => ['text'],
        'characters' => ['ability_text', 'extra_abilities'],
        'scenarios' => ['overview', 'setup', 'dread_effect', 'win_text', 'lose_text'],
        'modules' => ['setup'],
        'tales' => ['overview'],
        'tale_steps' => ['intro', 'outro', 'notes'],
        'tale_modes' => ['text'],
        'tale_log_entries' => ['name'],
        'keywords' => ['description', 'reminder'],
        'rule_documents' => ['body'],
    ];

    /** Columns cast to an array, which store JSON rather than the text as typed. */
    private const JSON_COLUMNS = ['characters.extra_abilities'];

    /** Off while design:import runs: a file's text is the designer's, never the tool's to rewrite. */
    private static bool $following = true;

    /** A card's key: its name as a slug. */
    public static function key(?string $name): string
    {
        return Str::slug((string) $name);
    }

    /**
     * Every card by key: what a reference resolves against.
     *
     * @return array<string, list<array{owner: string, owner_name: string, kind: string, name: string}>>
     */
    public static function map(): array
    {
        $map = [];

        // Owners by id, looked up rather than eager loaded: a card's owner key
        // is null on every side but one, and a lookup table costs one query
        // per owner kind however many cards there are.
        $owners = [];
        foreach (['scenario' => Scenario::class, 'module' => Module::class, 'character' => Character::class, 'domain' => Domain::class, 'tale' => Tale::class] as $kind => $class) {
            $owners[$kind] = $class::query()->get(['id', 'slug', 'name'])->keyBy('id');
        }

        $add = function (string $kind, Model $card) use (&$map, $owners): void {
            $key = self::key($card->name);

            if ($key === '') {
                return;
            }

            $owner = null;
            foreach ($owners as $relation => $byId) {
                $id = $card->getAttribute($relation.'_id');
                if ($id !== null && ($owner = $byId->get($id)) !== null) {
                    break;
                }
            }

            $map[$key][] = [
                'owner' => (string) ($owner?->slug ?? ''),
                'owner_name' => (string) ($owner?->name ?? ''),
                'kind' => $kind,
                'name' => (string) $card->name,
            ];
        };

        $kinds = [
            'entity' => [EntityCard::class, 'sort'],
            'board' => [BoardCard::class, 'sort'],
            'town' => [TownAction::class, 'sort'],
            'beat' => [StoryBeat::class, 'order'],
            'player' => [PlayerCard::class, 'sort'],
            'tale' => [TaleCard::class, 'sort'],
        ];

        foreach ($kinds as $kind => [$class, $order]) {
            $class::query()->orderBy($order)->get()->each(fn (Model $card) => $add($kind, $card));
        }

        return $map;
    }

    /**
     * The name a reference prints, or null when no card answers to it. With
     * an owner, only that owner's card will do; without one, the first card
     * of that name — which is the same name whichever it is.
     *
     * @param  array<string, list<array{owner: string, name: string}>>  $map
     */
    public static function resolve(array $map, string $key, ?string $owner = null): ?string
    {
        foreach ($map[$key] ?? [] as $card) {
            if ($owner === null || $owner === '' || $card['owner'] === $owner) {
                return $card['name'];
            }
        }

        return null;
    }

    /**
     * A card was renamed in the editor: point every reference to it at the new
     * name. A reference naming the owner always follows. A bare one follows
     * only when no other card still answers to the old name, because then it
     * could not have meant anything else.
     *
     * Runs after the save, so the card itself already carries the new name.
     */
    public static function renamed(string $oldName, string $newName, ?string $owner): void
    {
        $old = self::key($oldName);
        $new = self::key($newName);

        if (! self::$following || $old === '' || $new === '' || $old === $new) {
            return;
        }

        $bareFollows = self::resolve(self::map(), $old) === null;
        $owner = (string) $owner;

        $rewrite = fn (string $text): string => preg_replace_callback(self::PATTERN, function (array $m) use ($old, $new, $owner, $bareFollows): string {
            if ($m[2] !== $old) {
                return $m[0];
            }

            if ($m[1] !== '') {
                return $m[1] === $owner ? '{card:'.$owner.'/'.$new.'}' : $m[0];
            }

            return $bareFollows ? '{card:'.$new.'}' : $m[0];
        }, $text);

        foreach (self::TEXT_COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->where($column, 'like', '%{card:%'.$old.'}%')->get(['id', $column])
                    ->each(function ($row) use ($table, $column, $rewrite): void {
                        $stored = (string) $row->{$column};
                        $json = in_array("{$table}.{$column}", self::JSON_COLUMNS, true) ? json_decode($stored, true) : null;

                        // A JSON column escapes the owner's slash, so it is
                        // rewritten as the strings it holds, not as raw text.
                        $text = is_array($json)
                            ? json_encode(self::walk($json, $rewrite))
                            : $rewrite($stored);

                        if ($text !== $stored) {
                            DB::table($table)->where('id', $row->id)->update([$column => $text]);
                        }
                    });
            }
        }
    }

    /** Every string in a decoded JSON value, rewritten. */
    private static function walk(mixed $value, callable $rewrite): mixed
    {
        if (is_string($value)) {
            return $rewrite($value);
        }

        return is_array($value) ? array_map(fn ($v) => self::walk($v, $rewrite), $value) : $value;
    }

    /** Run $callback with renames not followed — what design:import does. */
    public static function withoutFollowing(callable $callback): mixed
    {
        $was = self::$following;
        self::$following = false;

        try {
            return $callback();
        } finally {
            self::$following = $was;
        }
    }
}
