<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A deck build saved under a name: the character slug, the domain slug and
 * the take map, the same shape the deck builder already carries in its query
 * string. Loading one is applying that query straight back.
 */
class SavedDeck extends Model
{
    protected $fillable = ['name', 'build'];

    protected $casts = [
        'build' => 'array',
    ];
}
