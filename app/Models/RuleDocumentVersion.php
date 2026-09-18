<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RuleDocumentVersion extends Model
{
    use HasFactory;

    protected $fillable = ['rule_document_id', 'body', 'note'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(RuleDocument::class, 'rule_document_id');
    }
}
