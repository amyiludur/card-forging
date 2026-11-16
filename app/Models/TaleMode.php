<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A difficulty mode: a name and the designer's text for what happens between
 * scenarios in it. The tool applies none of it.
 */
class TaleMode extends Model
{
    protected $fillable = ['tale_id', 'name', 'text', 'is_placeholder', 'sort'];

    protected $casts = [
        'is_placeholder' => 'boolean',
    ];

    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }
}
