<?php

namespace App\Rules;

use App\Support\PlayerScaled;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An equation counting the players, as typed into an editor.
 *
 * The message is the one {@see PlayerScaled::validate()} gives, so the designer
 * is told what is actually wrong with what they typed — a stray `/`, a bracket
 * left open — rather than that the field is invalid.
 */
class PerPlayerEquation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || trim((string) $value) === '') {
            return;
        }

        $error = PlayerScaled::validate((string) $value);

        if ($error !== null) {
            $fail($error);
        }
    }
}
