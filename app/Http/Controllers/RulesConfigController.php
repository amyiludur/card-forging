<?php

namespace App\Http\Controllers;

use App\Models\RulesConfig;
use App\Support\Markup;
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

            $config->update([
                'value' => ['v' => $this->cast($row['value'], $config->value_type)],
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
            'int' => (int) $value,
            'range' => array_map('intval', is_array($value) ? $value : explode(',', (string) $value)),
            // A map keeps its keys: {"signature": 20, "domain": 20}.
            'map' => array_map(fn ($v) => is_numeric($v) ? (int) $v : $v, (array) $value),
            default => (string) $value,
        };
    }
}
