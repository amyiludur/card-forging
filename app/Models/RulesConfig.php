<?php

namespace App\Models;

use App\Support\PlayerScaled;
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

    /**
     * What shape a value is, from the value itself. The one place that decides,
     * so importing a design folder and saving the editor form agree.
     *
     * A string that names perPlayer is an equation; any other string is just a
     * string, which is how "deck-bottom" and "top" stay what they are.
     */
    public static function typeFor(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'bool',
            // A list is a range ([0, 2]); an object is a map ({signature: 20}).
            is_array($value) => array_is_list($value) ? 'range' : 'map',
            is_int($value) => 'int',
            is_null($value) => 'int',
            is_string($value) && PlayerScaled::mentionsPerPlayer($value) => 'equation',
            default => 'string',
        };
    }

    /** A number that may count the players, for the types that can hold one. */
    public function scaled(): PlayerScaled
    {
        return $this->value_type === 'equation'
            ? PlayerScaled::make(null, (string) $this->raw_value)
            : PlayerScaled::make(is_int($this->raw_value) ? $this->raw_value : null);
    }

    public static function map(): array
    {
        return static::query()->get()->mapWithKeys(fn (self $c) => [$c->key => $c->raw_value])->all();
    }
}
