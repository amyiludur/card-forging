<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

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

    /** The character's other cards, from the loaded relation when there is one. */
    public function siblings(): Collection
    {
        if ($this->relationLoaded('character') && $this->character?->relationLoaded('cards')) {
            return $this->character->cards->where('id', '!=', $this->id)->values();
        }

        return static::where('character_id', $this->character_id)->whereKeyNot($this->id)->get();
    }

    private function sibling(string $slug): ?self
    {
        return $this->siblings()->firstWhere('slug', $slug);
    }

    /**
     * An upgrade pair is one fact held at both ends, so writing either end has
     * to write the other. Without this the editor can only ever half-make a
     * link: name an upgrade on a card and the upgrade does not point back.
     *
     * A card upgrades into at most one card and replaces at most one, so any
     * older claim on either end of this card's pair is dropped.
     */
    public function syncUpgradeLinks(): void
    {
        foreach ($this->siblings() as $sibling) {
            $upgradesTo = $sibling->upgrades_to;
            $upgradeOf = $sibling->upgrade_of;

            // Drop whatever this card's pair has just taken over.
            if ($upgradesTo !== null && ($upgradesTo === $this->slug || $upgradesTo === $this->upgrades_to)) {
                $upgradesTo = null;
            }

            if ($upgradeOf !== null && ($upgradeOf === $this->slug || $upgradeOf === $this->upgrade_of)) {
                $upgradeOf = null;
            }

            // Then the two ends of this card's own pair point back at it.
            if ($sibling->slug === $this->upgrades_to) {
                $upgradeOf = $this->slug;
            }

            if ($sibling->slug === $this->upgrade_of) {
                $upgradesTo = $this->slug;
            }

            if ($upgradesTo !== $sibling->upgrades_to || $upgradeOf !== $sibling->upgrade_of) {
                $sibling->update(['upgrades_to' => $upgradesTo, 'upgrade_of' => $upgradeOf]);
            }
        }
    }

    /** Part of the 20 a character brings, as opposed to kit or an upgrade. */
    public function countsTowardsDeck(): bool
    {
        return $this->role === self::ROLE_SIGNATURE;
    }
}
