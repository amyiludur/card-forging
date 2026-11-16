<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A card of the Tale's own — a story card, or one for the box's own mechanic.
 * Its type line is free text, not a card type row, because it names a thing
 * the Tale invents rather than one of the game's types.
 */
class TaleCard extends Model
{
    protected $fillable = [
        'tale_id', 'slug', 'name', 'qty', 'type_line', 'text', 'flavour', 'traits', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'traits' => 'array',
        'is_placeholder' => 'boolean',
    ];

    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }
}
