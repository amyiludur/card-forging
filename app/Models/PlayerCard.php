<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A card in a player's deck. Roles map to the three lists a character file is
 * written in; origin says which half of the 40 the card fills.
 */
class PlayerCard extends Model
{
    use HasFactory;

    public const ROLE_KIT = 'kit';

    public const ROLE_SIGNATURE = 'signature';

    public const ROLE_UPGRADE = 'upgrade';

    public const ROLES = [self::ROLE_KIT, self::ROLE_SIGNATURE, self::ROLE_UPGRADE];

    /** Neutral cards fill domain slots without adding to the 40. */
    public const ORIGINS = ['signature', 'domain', 'neutral'];

    public const TYPES = ['action', 'item', 'response'];

    public const START_ZONES = ['deck', 'shop', 'play', 'upgrade'];

    protected $fillable = [
        'character_id', 'slug', 'name', 'qty', 'role', 'origin', 'domain', 'type',
        'gold_cost', 'omen_icons', 'shop_cost', 'start_zone', 'text', 'traits',
        'keywords', 'upgrades_to', 'upgrade_of', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'traits' => 'array',
        'keywords' => 'array',
        'is_placeholder' => 'boolean',
    ];

    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class);
    }

    /** The card this one upgrades into, looked up by slug within the character. */
    public function upgrade(): ?self
    {
        return $this->upgrades_to === null ? null : $this->sibling($this->upgrades_to);
    }

    /** The card this upgrade replaces. */
    public function replaces(): ?self
    {
        return $this->upgrade_of === null ? null : $this->sibling($this->upgrade_of);
    }

    private function sibling(string $slug): ?self
    {
        return static::where('character_id', $this->character_id)->where('slug', $slug)->first();
    }

    /** Part of the 20 a character brings, as opposed to kit or an upgrade. */
    public function countsTowardsDeck(): bool
    {
        return $this->role === self::ROLE_SIGNATURE;
    }
}
