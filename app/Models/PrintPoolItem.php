<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One card in the print pool, by the `group:id` key the print picker uses. A
 * card deleted since it was added has nothing to print, and the pool page
 * drops it — see {@see \App\Support\PrintCatalogue::resolve()}.
 */
class PrintPoolItem extends Model
{
    protected $fillable = ['group', 'card_id', 'qty', 'position'];

    protected $casts = [
        'card_id' => 'integer',
        'qty' => 'integer',
        'position' => 'integer',
    ];

    public function key(): string
    {
        return $this->group.':'.$this->card_id;
    }
}
