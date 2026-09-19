<?php

namespace App\Http\Middleware;

use App\Models\Character;
use App\Models\Module;
use App\Models\RulesConfig;
use App\Models\Scenario;
use App\Support\Markup;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            // The sidebar needs the scenario list on every page.
            'nav' => [
                'scenarios' => fn () => Scenario::orderBy('name')->get(['slug', 'name'])->all(),
                'modules' => fn () => Module::orderBy('name')->get(['slug', 'name'])->all(),
                'characters' => fn () => Character::orderBy('sort')->orderBy('name')->get(['slug', 'name'])->all(),
            ],
            // Shared so the browser can render {config:...} and icon markup live,
            // the same way the print sheet does on the server.
            'markup' => [
                'icons' => Markup::ICONS,
                'config' => fn () => RulesConfig::orderBy('sort')->get()
                    ->mapWithKeys(fn (RulesConfig $c) => [$c->key => [
                        'label' => $c->label,
                        'value' => $c->raw_value,
                        'is_placeholder' => $c->is_placeholder,
                    ]]),
            ],
        ];
    }
}
