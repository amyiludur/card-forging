<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'slug', 'name', 'title', 'story', 'status', 'identity', 'health', 'hand_size',
        'gold_per_round', 'ability_name', 'ability_text', 'notes', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'notes' => 'array',
        'is_placeholder' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function cards(): HasMany
    {
        return $this->hasMany(PlayerCard::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The domains this character draws the other half of its deck from. How
     * many a character gets is the designer's to settle, so this is a list of
     * any length and the character page reports what it adds up to.
     */
    public function domains(): BelongsToMany
    {
        // Both tables carry a sort column, so both are named.
        return $this->belongsToMany(Domain::class)
            ->withPivot('sort')
            ->orderBy('character_domain.sort')
            ->orderBy('domains.name');
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

    /** What the chosen domains bring to the deck, counted by copy. */
    public function domainCount(): int
    {
        return (int) $this->domains->sum(fn (Domain $d) => $d->poolSize());
    }
}
