<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PrintsItems;
use App\Models\PrintPoolItem;
use App\Support\CardPresenter;
use App\Support\PrintCatalogue;
use App\Support\PrintOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * The print pool: cards gathered from any scenario, module, character or
 * domain onto one print run. It prints through the same options page and the
 * same sheet as every other print page — only where the items come from is
 * different — so a sticker sheet lined up for a scenario lines up here too.
 *
 * The pool is a list of keys, not of cards. Adding a card never copies it, so
 * a card edited after it was added prints as it now reads.
 */
class PrintPoolController extends Controller
{
    use PrintsItems;

    public function options(Request $request): Response
    {
        $options = PrintOptions::fromRequest($request, 'all');
        $catalogue = new PrintCatalogue;

        return $this->optionsPage($request, $options, $this->poolItems($catalogue, $options), [
            'scenario' => ['slug' => 'pool', 'name' => 'Print pool'],
            'kind' => 'pool',
            'decks' => PrintCatalogue::GROUPS + ['all' => 'Everything'],
            // Everything that could be added, names only: the pool page is
            // where a card from anywhere is picked.
            'catalogue' => $catalogue->entries()->values()->all(),
        ]);
    }

    public function sheet(Request $request): HttpResponse
    {
        return response(View::make('print.sheet', $this->poolSheetData($request))->render());
    }

    public function pdf(Request $request)
    {
        return $this->renderPdf($this->poolSheetData($request), 'print-pool.pdf');
    }

    private function poolSheetData(Request $request): array
    {
        $options = PrintOptions::fromRequest($request, 'all');

        return $this->sheetFor(
            $request,
            $options,
            $this->poolItems(new PrintCatalogue, $options),
            (object) ['name' => 'Print pool'],
            // The backs of a pool mix every deck there is, so they carry no
            // one deck's name.
            ['backName' => ''],
        );
    }

    /**
     * The pool as print items, in the order the cards were added. A card that
     * has been deleted since has nothing to print, so its row goes with it.
     */
    private function poolItems(PrintCatalogue $catalogue, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $rows = PrintPoolItem::orderBy('position')->orderBy('id')->get();
        $cards = $catalogue->find($rows->map(fn (PrintPoolItem $row) => [$row->group, $row->card_id]));

        $gone = $rows->reject(fn (PrintPoolItem $row) => $cards->has($row->key()));

        if ($gone->isNotEmpty()) {
            PrintPoolItem::whereKey($gone->modelKeys())->delete();
        }

        return $rows
            ->filter(fn (PrintPoolItem $row) => $cards->has($row->key()))
            ->map(function (PrintPoolItem $row) use ($cards, $catalogue, $presenter) {
                $model = $cards[$row->key()];
                $about = $catalogue->describe($row->group, $model);

                return array_merge(
                    $this->item($row->group, $row->card_id, $about['name'], $row->qty ?? $about['qty'], $about['is_placeholder'],
                        fn () => $catalogue->present($row->group, $model, $presenter)),
                    [
                        'pool_id' => $row->id,
                        'source' => $about['source'],
                        'card_qty' => $about['qty'],
                    ],
                );
            })
            ->values();
    }

    /**
     * Add cards to the pool, from the pool's own list or from any print page's
     * picker. `items` is a list of `{key, qty}`; a qty left out prints the
     * card's own quantity. A card already in the pool stays as it is.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'items' => ['required', 'array', 'max:2000'],
            'items.*.key' => ['required', 'string', 'max:40'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $wanted = [];

        foreach ($request->input('items') as $item) {
            $parsed = PrintCatalogue::parse((string) $item['key']);

            if ($parsed !== null) {
                $wanted[$parsed[0].':'.$parsed[1]] = ['key' => $parsed, 'qty' => $item['qty'] ?? null];
            }
        }

        // Only cards that exist: a stale key from an old tab adds nothing.
        $found = (new PrintCatalogue)->find(array_column($wanted, 'key'));
        $already = PrintPoolItem::all()->map->key()->flip();
        $position = (int) PrintPoolItem::max('position');

        $added = 0;
        $present = 0;

        foreach ($wanted as $key => $want) {
            if (! $found->has($key)) {
                continue;
            }

            if ($already->has($key)) {
                $present++;

                continue;
            }

            PrintPoolItem::create([
                'group' => $want['key'][0],
                'card_id' => $want['key'][1],
                'qty' => $want['qty'],
                'position' => ++$position,
            ]);

            $added++;
        }

        $message = $added > 0
            ? 'Added '.$added.' '.Str::plural('card', $added).' to the print pool.'
            : 'Nothing added to the print pool.';

        if ($present > 0) {
            $message .= ' '.$present.' '.($present === 1 ? 'was' : 'were').' already there.';
        }

        return back()->with('success', $message);
    }

    /** How many copies of one pool card to print; empty goes back to the card's own. */
    public function update(Request $request, PrintPoolItem $item): RedirectResponse
    {
        $data = $request->validate([
            'qty' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $item->update(['qty' => $data['qty'] ?? null]);

        return back();
    }

    public function destroy(PrintPoolItem $item): RedirectResponse
    {
        $item->delete();

        return back();
    }

    public function clear(): RedirectResponse
    {
        PrintPoolItem::query()->delete();

        return back()->with('success', 'Emptied the print pool.');
    }
}
