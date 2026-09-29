<?php

namespace App\Models;

use App\Support\PlayerScaled;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A player character: the character card itself, plus the cards that belong to
 * it. Health and hand size live here rather than in the rules config, because
 * design v3 made them per character.
 */
class Character extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'title', 'story', 'status', 'identity', 'colour', 'colour_secondary',
        'health', 'health_equation', 'hand_size', 'hand_size_equation',
        'gold_per_round', 'gold_per_round_equation', 'ability_name', 'ability_text', 'extra_abilities', 'notes',
        'is_placeholder', 'sort',
    ];

    protected $casts = [
        'notes' => 'array',
        'extra_abilities' => 'array',
        'is_placeholder' => 'boolean',
    ];

    /**
     * The three numbers that print together on the character card, each of
     * which may be a plain number or an equation counting the players. They are
     * one fact in one place, so they scale the same way as each other.
     */
    public function scaledHealth(): PlayerScaled
    {
        return PlayerScaled::make($this->health, $this->health_equation);
    }

    public function scaledHandSize(): PlayerScaled
    {
        return PlayerScaled::make($this->hand_size, $this->hand_size_equation);
    }

    public function scaledGoldPerRound(): PlayerScaled
    {
        return PlayerScaled::make($this->gold_per_round, $this->gold_per_round_equation);
    }

    /**
     * The abilities after the identity one, each a name and a text. Always a
     * list, never null, so everything that prints them can loop without asking.
     *
     * @return list<array{name: ?string, text: ?string}>
     */
    public function extraAbilities(): array
    {
        return array_values(array_map(fn ($a) => [
            'name' => $a['name'] ?? null,
            'text' => $a['text'] ?? null,
        ], $this->extra_abilities ?? []));
    }

    /**
     * The extra abilities as stored: a row with neither a name nor a text is an
     * empty form row, not an ability, and none at all is null. The editor and
     * the importer both write through this, so they cannot disagree.
     */
    public static function normaliseAbilities(?array $abilities): ?array
    {
        $kept = array_values(array_filter(
            array_map(fn ($a) => [
                'name' => (is_array($a) ? ($a['name'] ?? null) : null) ?: null,
                'text' => (is_array($a) ? ($a['text'] ?? null) : null) ?: null,
            ], $abilities ?? []),
            fn (array $a) => $a['name'] !== null || $a['text'] !== null,
        ));

        return $kept ?: null;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cards(): HasMany
    {
        return $this->hasMany(PlayerCard::class)->orderBy('sort')->orderBy('id');
    }

    /** Starts in play and sits outside the 20 — the Gunslinger's Revolver. */
    public function kit(): HasMany
    {
        return $this->cards()->where('role', PlayerCard::ROLE_KIT);
    }

    /** The 20 signature cards, counted by quantity rather than by row. */
    public function signatureCards(): HasMany
    {
        return $this->cards()->where('role', PlayerCard::ROLE_SIGNATURE);
    }

    /** Set aside outside the 40; the Smithy swaps a card for its upgrade. */
    public function upgrades(): HasMany
    {
        return $this->cards()->where('role', PlayerCard::ROLE_UPGRADE);
    }

    public function signatureCount(): int
    {
        return (int) $this->signatureCards()->sum('qty');
    }

}
