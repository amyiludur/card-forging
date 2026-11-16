<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing the campaign log records — ship damage, a box ticked when the
 * lighthouse falls, the rewards earned. Each Tale has its own, the way the
 * keyword library holds the designer's own words: the kind says how it is
 * written down, never what it means.
 */
class TaleLogEntry extends Model
{
    public const KINDS = ['number', 'check', 'note', 'rewards'];

    /** Once for the whole party, or once for each character. */
    public const SCOPES = ['party', 'character'];

    protected $fillable = ['tale_id', 'name', 'kind', 'scope', 'is_placeholder', 'sort'];

    protected $casts = [
        'is_placeholder' => 'boolean',
    ];

    public function tale(): BelongsTo
    {
        return $this->belongsTo(Tale::class);
    }
}
