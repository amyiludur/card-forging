<?php

namespace App\Models;

use App\Support\Colour;
use App\Support\Icons;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * What a card does in one word: Attack, Hazard, Summon.
 *
 * A type either belongs to the shared library, which every scenario and module
 * draws on, or to one scenario — the Kraken's own Tide, which only the Kraken's
 * cards can be typed with. Slugs are unique across both, so a design file
 * saying `"type": "tide"` and a `?type=tide` filter can only mean one thing.
 *
 * The colour is the designer's and is printed as it is picked. What a card face
 * derives from it — the ink over the head band, the darker version that stays
 * legible on the cream body — is worked out by App\Support\Colour.
 */
class CardType extends Model
{
    use HasFactory;

    protected $fillable = ['scenario_id', 'slug', 'name', 'description', 'colour', 'icon', 'sort'];

    public function faces(): HasMany
    {
        return $this->hasMany(EntityCardFace::class);
    }

    /** Null for a type in the shared library. */
    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    /** The shared library: the types every scenario and module can use. */
    public function scopeShared(Builder $query): Builder
    {
        return $query->whereNull('scenario_id');
    }

    /**
     * The types a card of this scenario can be typed with: the shared library
     * plus the scenario's own. A module card has no scenario, so it gets the
     * shared library alone.
     */
    public function scopeFor(Builder $query, ?Scenario $scenario): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->whereNull('scenario_id')
            ->when($scenario, fn (Builder $q) => $q->orWhere('scenario_id', $scenario->id)));
    }

    /**
     * The icon drawn beside the name. The five shipped types are named after
     * their icon, so an unset icon falls back to the slug and only a type whose
     * name is not an icon's has to pick one.
     */
    public function getIconNameAttribute(): ?string
    {
        $name = $this->icon ?: $this->slug;

        return Icons::has($name) ? $name : null;
    }

    /** The colour as picked, or null: a type nobody coloured prints the dark head. */
    public function getHexAttribute(): ?string
    {
        return Colour::normalise($this->colour);
    }
}
