<?php

namespace App\Models;

use App\Models\Concerns\HasArt;
use App\Models\Concerns\NamedByCards;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityCard extends Model
{
    use HasArt, HasFactory, NamedByCards;

    public const LAYOUTS = ['single', 'split', 'x-cost'];

    protected $fillable = [
        'scenario_id', 'module_id', 'name', 'qty', 'layout', 'omen_cost', 'omen_is_x', 'omen_x_max',
        'traits', 'added_by_beat_id', 'arrow', 'bottom_copies', 'notes', 'flavour', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'traits' => 'array',
        'omen_is_x' => 'boolean',
        'omen_x_max' => 'integer',
        'bottom_copies' => 'integer',
        'is_placeholder' => 'boolean',
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

    public function faces(): HasMany
    {
        return $this->hasMany(EntityCardFace::class)->orderBy('sort');
    }

    public function addedByBeat(): BelongsTo
    {
        return $this->belongsTo(StoryBeat::class, 'added_by_beat_id');
    }

    public function getOmenLabelAttribute(): string
    {
        return $this->omen_is_x ? 'X' : (string) ($this->omen_cost ?? 0);
    }

    /**
     * The most this card's X can come to, or null. Only an X card has one: a
     * number left on a card that is no longer X is ignored rather than printed.
     */
    public function xMax(): ?int
    {
        return $this->omen_is_x && $this->omen_x_max ? $this->omen_x_max : null;
    }

    public function isSplit(): bool
    {
        return $this->layout === 'split';
    }

    /**
     * The arrow sits on the right edge and points at the top or bottom half of
     * the card to its right, so it says nothing about this card's own halves.
     */
    public function pointsAt(): string
    {
        return $this->arrow ?: 'top';
    }

    /**
     * Whether this card's copies do not all point the same way. `bottom_copies`
     * set is the whole of it: that many copies point at the bottom and the rest
     * at the top, whatever `arrow` says.
     */
    public function hasMixedArrows(): bool
    {
        return $this->bottom_copies !== null;
    }

    /**
     * One arrow per printed copy, top copies first. A split that does not fit
     * the copies — more bottom copies than there are cards — is read as far as
     * it goes and reported by arrowSplitProblem(), never trimmed.
     *
     * @return list<string>
     */
    public function copyArrows(): array
    {
        $copies = max(1, (int) $this->qty);

        if (! $this->hasMixedArrows()) {
            return array_fill(0, $copies, $this->pointsAt());
        }

        $bottom = min($copies, max(0, (int) $this->bottom_copies));

        return [...array_fill(0, $copies - $bottom, 'top'), ...array_fill(0, $bottom, 'bottom')];
    }

    /** @return array{top: int, bottom: int} */
    public function arrowCounts(): array
    {
        $arrows = array_count_values($this->copyArrows());

        return ['top' => $arrows['top'] ?? 0, 'bottom' => $arrows['bottom'] ?? 0];
    }

    /** What is wrong with a mixed split, in words, or null when it adds up. */
    public function arrowSplitProblem(): ?string
    {
        if (! $this->hasMixedArrows()) {
            return null;
        }

        $copies = max(1, (int) $this->qty);
        $bottom = (int) $this->bottom_copies;

        if ($bottom < 1 || $bottom >= $copies) {
            return "says {$bottom} of its {$copies} ".($copies === 1 ? 'copy points' : 'copies point')
                .' at the bottom, so its copies do not actually mix';
        }

        return null;
    }

    /**
     * The card once per printed copy, each carrying its own arrow, so anything
     * that lays copies out — the deck maths, the storyline preview — reads the
     * right arrow off pointsAt() without knowing about the split.
     *
     * @return list<self>
     */
    public function copies(): array
    {
        if (! $this->hasMixedArrows()) {
            return array_fill(0, max(1, (int) $this->qty), $this);
        }

        return array_map(function (string $arrow) {
            $copy = clone $this;
            $copy->arrow = $arrow;
            $copy->bottom_copies = null;

            return $copy;
        }, $this->copyArrows());
    }
}
