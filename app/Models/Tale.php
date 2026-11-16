<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A campaign box: scenarios played in order, a story running through them, and
 * cards that only come out when the Tale is played. A step names a scenario
 * and never owns it, so the Kraken stays playable on its own.
 *
 * How anything carries over between scenarios is the Tale's own: its log
 * entries are the designer's, and the tool reads none of them.
 */
class Tale extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'status', 'overview', 'set_icon', 'is_placeholder', 'sort'];

    protected $casts = [
        'is_placeholder' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** The scenarios in the order the Tale plays them. */
    public function steps(): HasMany
    {
        return $this->hasMany(TaleStep::class)->orderBy('sort')->orderBy('id');
    }

    public function modes(): HasMany
    {
        return $this->hasMany(TaleMode::class)->orderBy('sort')->orderBy('id');
    }

    public function logEntries(): HasMany
    {
        return $this->hasMany(TaleLogEntry::class)->orderBy('sort')->orderBy('id');
    }

    /** The Tale's own cards: story cards and the box's own mechanic. */
    public function cards(): HasMany
    {
        return $this->hasMany(TaleCard::class)->orderBy('sort')->orderBy('id');
    }

    /** Player cards the Tale owns: rewards, and the upgrades of rewards. */
    public function rewardCards(): HasMany
    {
        return $this->hasMany(PlayerCard::class)->orderBy('sort')->orderBy('id');
    }

    /** Playthroughs of the Tale, newest first. */
    public function runs(): HasMany
    {
        return $this->hasMany(TaleRun::class)->latest()->orderByDesc('id');
    }

    /** The Tale's own modules — its entity-side cards. Hidden from ordinary play. */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('name');
    }
}
