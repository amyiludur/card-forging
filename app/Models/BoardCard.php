<?php

namespace App\Models;

use App\Models\Concerns\HasArt;
use App\Models\Concerns\NamedByCards;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardCard extends Model
{
    use HasArt, HasFactory, NamedByCards;

    protected $fillable = [
        'scenario_id', 'module_id', 'name', 'qty', 'print_qty', 'start', 'deal_group', 'health', 'traits', 'text', 'flavour',
        'added_by_beat_id', 'is_placeholder', 'sort',
    ];

    /** Put out on the entity board at setup, as every board card was before. */
    public const START_BOARD = 'board';

    /** Dealt one to each player at random at setup; the rest are kept aside. */
    public const START_DEALT = 'dealt';

    public const STARTS = [self::START_BOARD, self::START_DEALT];

    protected $attributes = [
        'start' => self::START_BOARD,
    ];

    protected $casts = [
        'traits' => 'array',
        'is_placeholder' => 'boolean',
        'qty' => 'integer',
        'print_qty' => 'integer',
    ];

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /** Where this card came from, for the set icon and the card list. */
    public function origin(): ?string
    {
        return $this->module?->name ?? $this->scenario?->name;
    }

    /**
     * How many copies go on a print sheet. Setup may put none out — a card
     * another card brings into play — so the print run is its own number when
     * set, and otherwise what setup puts out, never fewer than one.
     */
    public function printCopies(): int
    {
        return $this->print_qty ?? max(1, (int) $this->qty);
    }

    public function isDealt(): bool
    {
        return $this->start === self::START_DEALT;
    }

    public function addedByBeat(): BelongsTo
    {
        return $this->belongsTo(StoryBeat::class, 'added_by_beat_id');
    }
}
