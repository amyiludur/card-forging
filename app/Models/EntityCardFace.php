<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityCardFace extends Model
{
    use HasFactory;

    public const HALVES = ['single', 'top', 'bottom'];

    protected $fillable = ['entity_card_id', 'half', 'card_type_id', 'text', 'sort'];

    public function card(): BelongsTo
    {
        return $this->belongsTo(EntityCard::class, 'entity_card_id');
    }

    public function cardType(): BelongsTo
    {
        return $this->belongsTo(CardType::class);
    }
}
