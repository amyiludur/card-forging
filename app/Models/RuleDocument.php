<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RuleDocument extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'title', 'body', 'sort'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RuleDocumentVersion::class)->latest('id');
    }

    /** Snapshot the current body before it is overwritten. */
    public function snapshot(?string $note = null): void
    {
        $this->versions()->create(['body' => $this->body, 'note' => $note]);
    }
}
