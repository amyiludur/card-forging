<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardType extends Model
{
    use HasFactory;

    protected $fillable = ['slug', 'name', 'description', 'sort'];

    public function faces(): HasMany
    {
        return $this->hasMany(EntityCardFace::class);
    }
}
