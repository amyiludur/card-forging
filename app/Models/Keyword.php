<?php

namespace App\Models;

use App\Support\Markup;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A keyword the designer defined: one word of rules text, typed as {unique}.
 *
 * It renders as its name (in small caps, the way card games print a keyword),
 * with an optional icon in front of it, and it carries its own definition so
 * the editor can show what it means without anyone remembering.
 */
class Keyword extends Model
{
    use HasFactory;

    /** A token is what fits between the braces: lowercase, digits, hyphens. */
    public const TOKEN_PATTERN = '/^[a-z][a-z0-9-]*$/';

    protected $fillable = [
        'token', 'name', 'icon', 'show_name', 'plain', 'description', 'is_placeholder', 'sort',
    ];

    protected $casts = [
        'show_name' => 'boolean',
        'is_placeholder' => 'boolean',
    ];

    /** The plain-text form, for exports and diffs. The name unless overridden. */
    public function getPlainTextAttribute(): string
    {
        return $this->plain !== null && $this->plain !== '' ? $this->plain : $this->name;
    }

    /** True when this token is one of the built-in icons, which always win. */
    public static function isReserved(string $token): bool
    {
        return array_key_exists($token, Markup::ICONS);
    }

    /**
     * token => everything either half of the markup needs to draw it. Shared
     * with the browser through Inertia, so the editor, the preview and the
     * print sheet draw one keyword the same way.
     */
    public static function markupMap(): array
    {
        return static::query()->orderBy('sort')->orderBy('name')->get()
            ->mapWithKeys(fn (self $k) => [$k->token => [
                'name' => $k->name,
                'icon' => $k->icon,
                'show_name' => $k->show_name,
                'plain' => $k->plain_text,
                'description' => $k->description,
                'is_placeholder' => $k->is_placeholder,
            ]])
            ->all();
    }
}
