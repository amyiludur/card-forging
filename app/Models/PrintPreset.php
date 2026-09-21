<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A print setup saved under a name — what `PrintOptions::toArray()` gives, so
 * loading one is exactly the same shape `form` on the options page already is.
 */
class PrintPreset extends Model
{
    protected $fillable = ['name', 'options'];

    protected $casts = [
        'options' => 'array',
    ];
}
