<?php

namespace App\Http\Controllers;

use App\Models\RulesConfig;
use App\Support\Markup;
use App\Support\PlayerScaled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RulesConfigController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rules/Config', [
            'groups' => RulesConfig::orderBy('sort')->get()
                ->groupBy('group')
                ->map(fn ($items) => $items->map(fn (RulesConfig $c) => [
                    'id' => $c->id,
                    'key' => $c->key,
                    'label' => $c->label,
                    'value' => $c->raw_value,
                    'value_type' => $c->value_type,
                    'description' => $c->description,
                    'is_placeholder' => $c->is_placeholder,
                    // Why an equation cannot be read, so the editor can say so
                    // rather than quietly storing something that prints as a
                    // red missing value.
                    'equation_error' => $c->value_type === 'equation'
                        ? PlayerScaled::validate((string) $c->raw_value)
                        : null,
                ])->values()),
            'icons' => Markup::ICONS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'values' => ['required', 'array'],
            'values.*.id' => ['required', 'exists:rules_configs,id'],
            'values.*.value' => ['present'],
            'values.*.is_placeholder' => ['boolean'],
        ]);

        foreach ($data['values'] as $row) {
            $config = RulesConfig::findOrFail($row['id']);

            $value = $this->cast($row['value'], $config->value_type);

            $config->update([
                'value' => ['v' => $value],
                // A number the designer has just written as an equation stops
                // being an int, and an equation edited back to a number stops
                // being an equation. RulesConfig::typeFor() is the one place
                // that decides, so this and design:import never disagree.
                'value_type' => in_array($config->value_type, ['int', 'equation'], true)
                    ? RulesConfig::typeFor($value)
                    : $config->value_type,
                'is_placeholder' => $row['is_placeholder'] ?? $config->is_placeholder,
            ]);
        }

        return back()->with('success', 'Tunable numbers saved. Rules text using {config:…} updates with them.');
    }

    private function cast(mixed $value, string $type): mixed
    {
        if ($value === '' || $value === null) {
            return $type === 'bool' ? false : null;
        }

        return match ($type) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOL),
            // An int field the designer has typed an equation into is now an
            // equation; one holding digits is still a number. Reported, not
            // corrected: whatever else they type is kept as typed and shown
            // back to them with what is wrong with it.
            'int', 'equation' => is_numeric($value) ? (int) $value : (string) $value,
            'range' => array_map('intval', is_array($value) ? $value : explode(',', (string) $value)),
            // A map keeps its keys: {"signature": 20, "domain": 20}.
            'map' => array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, (array) $value),
            default => (string) $value,
        };
    }
}
