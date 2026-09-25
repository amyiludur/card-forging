<?php

namespace App\Http\Middleware;

use App\Models\Character;
use App\Models\Domain;
use App\Models\Keyword;
use App\Models\Module;
use App\Models\PrintPoolItem;
use App\Models\RulesConfig;
use App\Models\Scenario;
use App\Support\Icons;
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
                'domains' => fn () => Domain::orderBy('sort')->orderBy('name')->get(['slug', 'name', 'is_neutral'])->all(),
                'printPool' => fn () => PrintPoolItem::count(),
                // So the card zoom can say a card is already in the pool
                // rather than offer to add it twice.
                'printPoolKeys' => fn () => PrintPoolItem::all(['group', 'card_id'])->map->key()->values()->all(),
            ],
            // Shared so the browser can render {config:...} and icon markup live,
            // the same way the print sheet does on the server.
            'markup' => [
                'icons' => Markup::ICONS,
                // The same paths the server draws with, so the editor and the
                // printed card cannot show different icons.
                'paths' => Icons::forBrowser(),
                // The keyword library, so {unique} draws the same in the editor
                // as it does on the printed card.
                'keywords' => fn () => Keyword::markupMap(),
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
