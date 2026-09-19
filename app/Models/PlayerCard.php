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

    /** A card in a shared domain pool rather than in one character's own 20. */
    public const ROLE_DOMAIN = 'domain';

    public const ROLE_UPGRADE = 'upgrade';

    public const ROLES = [self::ROLE_KIT, self::ROLE_SIGNATURE, self::ROLE_DOMAIN, self::ROLE_UPGRADE];

    /** The three lists a character file is written in. */
    public const CHARACTER_ROLES = [self::ROLE_KIT, self::ROLE_SIGNATURE, self::ROLE_UPGRADE];

    /** The two a domain file is written in. */
    public const DOMAIN_ROLES = [self::ROLE_DOMAIN, self::ROLE_UPGRADE];

    /** Neutral cards fill domain slots without adding to the 40. */
    public const ORIGINS = ['signature', 'domain', 'neutral'];

    /**
     * A Hireling is the v3.1 fourth type: it stays in play with uses and a
     * sacrifice value rather than resolving and going to the discard pile.
     */
    public const TYPE_HIRELING = 'hireling';

    public const TYPES = ['action', 'item', 'response', self::TYPE_HIRELING];

    public const START_ZONES = ['deck', 'shop', 'play', 'upgrade'];

    protected $fillable = [
        'character_id', 'domain_id', 'slug', 'name', 'qty', 'role', 'origin', 'type',
        'gold_cost', 'omen_icons', 'uses', 'sacrifice_value', 'shop_cost', 'start_zone',
        'text', 'traits', 'keywords', 'upgrades_to', 'upgrade_of', 'is_placeholder', 'sort',
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

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * A card belongs to a character or to a domain, never both — the same rule
     * an entity card follows for a scenario and a module. Everything that has
     * to look at a card's other cards goes through here, so an upgrade pair
     * inside a domain never reaches for a character's cards.
     */
    public function owner(): Character|Domain|null
    {
        return $this->domain_id !== null
            ? $this->domain
            : $this->character;
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
        // An owner is what scopes a sibling. Without one, "every card whose
        // character is null" would sweep in every domain card in the app.
        if ($this->domain_id === null && $this->character_id === null) {
            return new Collection;
        }

        $owner = $this->domain_id !== null ? 'domain' : 'character';

        if ($this->relationLoaded($owner) && $this->{$owner}?->relationLoaded('cards')) {
            return $this->{$owner}->cards->where('id', '!=', $this->id)->values();
        }

        return static::where("{$owner}_id", $this->{"{$owner}_id"})->whereKeyNot($this->id)->get();
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

    /**
     * Uses and a sacrifice value belong to a Hireling and to nothing else, so
     * everything that reads or writes the pair asks here rather than testing
     * the string in a dozen places.
     */
    public function isHireling(): bool
    {
        return $this->type === self::TYPE_HIRELING;
    }

    /** One of the 40, as opposed to kit in play or an upgrade set aside. */
    public function countsTowardsDeck(): bool
    {
        return in_array($this->role, [self::ROLE_SIGNATURE, self::ROLE_DOMAIN], true);
    }
}
