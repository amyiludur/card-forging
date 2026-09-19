<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A domain is the shared half of a deck: 20 signature cards come from the
 * character, the other 20 from domains. Its cards belong to it rather than to
 * any one character, the way a module's cards belong to the module.
 *
 * How many domains a character draws from is not decided, so nothing here
 * assumes one. A domain is a pool of whatever size the designer writes.
 */
class Domain extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'title', 'status', 'identity', 'set_icon',
        'is_neutral', 'notes', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'notes' => 'array',
        'is_neutral' => 'boolean',
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

    /** The pool itself, as opposed to the upgrades set aside for the Smithy. */
    public function poolCards(): HasMany
    {
        return $this->cards()->where('role', PlayerCard::ROLE_DOMAIN);
    }

    public function upgrades(): HasMany
    {
        return $this->cards()->where('role', PlayerCard::ROLE_UPGRADE);
    }

    public function characters(): BelongsToMany
    {
        return $this->belongsToMany(Character::class)->withPivot('sort')->orderBy('name');
    }

    /** Counted by copy, because that is what fills a slot. */
    public function poolSize(): int
    {
        if ($this->relationLoaded('cards')) {
            return (int) $this->cards->where('role', PlayerCard::ROLE_DOMAIN)->sum('qty');
        }

        return (int) $this->poolCards()->sum('qty');
    }

    /**
     * What a card in this pool is by default. The colourless pool makes neutral
     * cards; a card can still say otherwise, and the deck maths reads the card.
     */
    public function defaultOrigin(): string
    {
        return $this->is_neutral ? 'neutral' : 'domain';
    }
}
