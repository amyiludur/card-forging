<?php

namespace App\Models\Concerns;

use App\Support\CardNames;

/**
 * A card other cards can name with {card:…}. Renaming it rewrites those
 * references so they keep naming it — see {@see CardNames::renamed()}.
 */
trait NamedByCards
{
    protected static function bootNamedByCards(): void
    {
        static::updated(function (self $card): void {
            if ($card->wasChanged('name')) {
                CardNames::renamed((string) $card->getOriginal('name'), (string) $card->name, $card->cardOwnerSlug());
            }
        });
    }

    /** Whoever the card belongs to, as a reference writes it: {card:owner/key}. */
    public function cardOwnerSlug(): ?string
    {
        foreach (['scenario', 'module', 'character', 'domain', 'tale'] as $relation) {
            if (method_exists($this, $relation) && ($owner = $this->{$relation}) !== null) {
                return $owner->slug;
            }
        }

        return null;
    }
}
