<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RulesConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'key', 'label', 'group', 'value_type', 'value', 'description', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'value' => 'array',
        'is_placeholder' => 'boolean',
    ];

    /**
     * Values are stored as JSON so booleans, nulls and ranges survive a round trip.
     * The raw scalar (or array, for a range) is what rules text and cards reference.
     */
    public function getRawValueAttribute(): mixed
    {
        return $this->value['v'] ?? null;
    }

    public static function map(): array
    {
        return static::query()->get()->mapWithKeys(fn (self $c) => [$c->key => $c->raw_value])->all();
    }
}
