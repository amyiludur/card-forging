<?php

namespace App\Http\Controllers\Concerns;

use App\Models\PrintPoolItem;
use App\Models\PrintPreset;
use App\Support\PdfRenderer;
use App\Support\PrintCatalogue;
use App\Support\PrintOptions;
use App\Support\PrintSelection;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * What every print page shares once its cards are laid out as items: the
 * sheet, the options page and the PDF. A scenario, a character, a built deck
 * and the print pool differ only in where their items come from.
 */
trait PrintsItems
{
    /**
     * One row of the print run. The key is what the picker ticks and what the
     * query string carries: the group is part of it because an entity card 12
     * and a player card 12 are two different cards in two different tables.
     */
    protected function item(string $group, int $id, ?string $name, ?int $qty, bool $placeholder, Closure $card): array
    {
        return [
            'group' => $group,
            'key' => $group.':'.$id,
            // The same card in the print pool, where a player card is a player
            // card whichever of its owner's lists it came out of.
            'pool_key' => PrintCatalogue::poolKey($group, $id),
            'name' => $name ?: 'Untitled',
            'qty' => $this->copies($qty),
            'is_placeholder' => $placeholder,
            'card' => $card,
        ];
    }

    /** A card with no quantity is still one card to cut out. */
    protected function copies(?int $qty): int
    {
        return max(1, (int) $qty);
    }

    /**
     * The sheet: the chosen deck, minus whatever the picker left out, one
     * printed card per copy — or more than one, when the run asks for extra
     * copies of a card. What was left out is counted and reported on the
     * sheet rather than silently missing; it is what the picker unticked, not
     * affected by a card printing more copies than its own quantity.
     *
     * An offset then starts the run part way in, for a run that stopped after
     * the first few cards: those are counted as already printed, not as left
     * out, and the blank cells of `skip` still come first on the sheet.
     */
    protected function sheetFor(Request $request, PrintOptions $options, Collection $items, mixed $subject, array $extra = []): array
    {
        $selection = PrintSelection::fromRequest($request);

        $inDeck = $items->filter(fn (array $item) => $options->wants($item['group']));
        $chosen = $inDeck->filter(fn (array $item) => $selection->includes($item['key']));

        $cards = collect();
        $position = 0;
        $offset = $selection->offset;

        foreach ($chosen as $item) {
            $copies = $selection->quantityFor($item['key'], $item['qty']);

            // Counted rather than rendered: a card the offset skips entirely
            // never runs its markup.
            if ($position + $copies <= $offset) {
                $position += $copies;

                continue;
            }

            $card = ($item['card'])();

            for ($i = 0; $i < $copies; $i++, $position++) {
                if ($position >= $offset) {
                    $cards->push($card);
                }
            }
        }

        return array_merge([
            'scenario' => $subject,
            'options' => $options,
            'pages' => $options->paginate($cards),
            'cardCount' => $cards->count(),
            'runTotal' => $position,
            'offset' => min($offset, $position),
            'omitted' => $inDeck->reject(fn (array $item) => $selection->includes($item['key']))->sum('qty'),
        ], $extra);
    }

    /** The options page: the same items, as a list to tick rather than to print. */
    protected function optionsPage(Request $request, PrintOptions $options, Collection $items, array $payload): Response
    {
        $selection = PrintSelection::fromRequest($request);

        return Inertia::render('Print/Options', array_merge([
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'layout' => $options->layout(),
            // The card picker: every card this page could print, with the
            // closure that renders it dropped — the browser only needs names.
            'items' => $items->map(fn (array $item) => Arr::except($item, 'card'))->values()->all(),
            'selection' => $selection->toArray(),
            'counts' => $this->counts($items),
            // Every print options page offers the same saved setups: a sticker
            // sheet lined up once is not just this scenario's to reuse.
            'presets' => $this->presets(),
            // So the picker can say which of its cards are already in the pool.
            'poolKeys' => PrintPoolItem::all()->map->key()->values()->all(),
        ], $payload));
    }

    protected function presets(): array
    {
        return PrintPreset::orderBy('name')->get()
            ->map(fn (PrintPreset $preset) => [
                'id' => $preset->id,
                'name' => $preset->name,
                'options' => $preset->options,
            ])
            ->all();
    }

    /** Printed cards per group, and the lot: what each deck choice would print. */
    protected function counts(Collection $items): array
    {
        $counts = ['all' => $items->sum('qty')];

        foreach ($items->groupBy('group') as $group => $rows) {
            $counts[$group] = $rows->sum('qty');
        }

        return $counts;
    }

    protected function renderPdf(array $data, string $name)
    {
        $pdf = (new PdfRenderer)->render(View::make('print.sheet', $data)->render());

        if ($pdf['path'] === null) {
            return back()->with('error', $pdf['error']);
        }

        return response()->download($pdf['path'], $name)->deleteFileAfterSend(true);
    }
}
