<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Module;
use App\Models\Scenario;
use App\Support\CardPresenter;
use App\Support\PrintOptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

class PrintController extends Controller
{
    public function options(Request $request, Scenario $scenario): Response
    {
        $options = PrintOptions::fromRequest($request);

        return Inertia::render('Print/Options', [
            'scenario' => ['slug' => $scenario->slug, 'name' => $scenario->name],
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'decks' => PrintOptions::SCENARIO_DECKS,
            'layout' => [
                'columns' => $options->columns(),
                'rows' => $options->rows(),
                'per_page' => $options->perPage(),
                'overflows' => $options->overflows(),
            ],
            'counts' => [
                'entity' => (int) $scenario->entityCards()->sum('qty'),
                'board' => (int) $scenario->boardCards()->sum('qty'),
                'beats' => $scenario->storyBeats()->count(),
            ],
        ]);
    }

    public function sheet(Request $request, Scenario $scenario): HttpResponse
    {
        return response(View::make('print.sheet', $this->sheetData($request, $scenario))->render());
    }

    /** A character prints its own deck: the 20, the kit and the upgrades. */
    public function characterOptions(Request $request, Character $character): Response
    {
        $options = PrintOptions::fromRequest($request, 'player');

        return Inertia::render('Print/Options', [
            'scenario' => ['slug' => $character->slug, 'name' => $character->name],
            'kind' => 'character',
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'decks' => PrintOptions::CHARACTER_DECKS,
            'layout' => [
                'columns' => $options->columns(),
                'rows' => $options->rows(),
                'per_page' => $options->perPage(),
                'overflows' => $options->overflows(),
            ],
            'counts' => [
                'entity' => (int) $character->cards()->sum('qty'),
                'board' => 0,
                'beats' => 0,
            ],
        ]);
    }

    public function characterSheet(Request $request, Character $character): HttpResponse
    {
        return response(View::make('print.sheet', $this->characterSheetData($request, $character))->render());
    }

    public function characterPdf(Request $request, Character $character)
    {
        return $this->renderPdf(
            $this->characterSheetData($request, $character),
            $character->slug.'-'.PrintOptions::fromRequest($request, 'player')->deck.'.pdf'
        );
    }

    private function characterSheetData(Request $request, Character $character): array
    {
        $options = PrintOptions::fromRequest($request, 'player');
        $presenter = CardPresenter::make($options->autoIcons);

        $character->load('cards');
        $character->cards->each->setRelation('character', $character);

        $cards = collect();

        if (in_array($options->deck, ['character', 'all'], true)) {
            $cards->push(['kind' => 'character'] + $presenter->character($character));
        }

        if (in_array($options->deck, ['player', 'all'], true)) {
            foreach ($character->cards as $card) {
                // One printed card per copy, upgrades and kit included: they are
                // all things the designer has to cut out.
                for ($i = 0; $i < $card->qty; $i++) {
                    $cards->push(['kind' => 'player'] + $presenter->playerCard($card));
                }
            }
        }

        return [
            'scenario' => $character,
            'options' => $options,
            'pages' => $cards->chunk($options->perPage())->values(),
            'cardCount' => $cards->count(),
        ];
    }

    /** A module prints as its own deck, set icon and all. */
    public function moduleOptions(Request $request, Module $module): Response
    {
        $options = PrintOptions::fromRequest($request);

        return Inertia::render('Print/Options', [
            'scenario' => ['slug' => $module->slug, 'name' => $module->name],
            'isModule' => true,
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'decks' => ['entity' => 'Module cards', 'board' => 'Module board cards', 'all' => 'Everything'],
            'layout' => [
                'columns' => $options->columns(),
                'rows' => $options->rows(),
                'per_page' => $options->perPage(),
                'overflows' => $options->overflows(),
            ],
            'counts' => [
                'entity' => $module->deckSize(),
                'board' => (int) $module->boardCards()->sum('qty'),
                'beats' => 0,
            ],
        ]);
    }

    public function moduleSheet(Request $request, Module $module): HttpResponse
    {
        return response(View::make('print.sheet', $this->moduleSheetData($request, $module))->render());
    }

    public function modulePdf(Request $request, Module $module)
    {
        return $this->renderPdf(
            $this->moduleSheetData($request, $module),
            $module->slug.'-'.PrintOptions::fromRequest($request)->deck.'.pdf'
        );
    }

    public function pdf(Request $request, Scenario $scenario)
    {
        return $this->renderPdf(
            $this->sheetData($request, $scenario),
            $scenario->slug.'-'.PrintOptions::fromRequest($request)->deck.'.pdf'
        );
    }

    private function renderPdf(array $data, string $name)
    {
        $chromium = $this->chromiumBinary();

        if ($chromium === null) {
            return back()->with('error', 'No Chromium binary found. Open the print preview and use the browser\'s "Save as PDF" instead.');
        }

        $work = storage_path('app/print/'.uniqid('sheet_', true));
        @mkdir($work, 0o755, true);

        $html = "{$work}/sheet.html";
        $pdf = "{$work}/sheet.pdf";

        file_put_contents($html, View::make('print.sheet', $data)->render());

        $result = Process::timeout(120)->run([
            $chromium,
            '--headless',
            '--disable-gpu',
            '--no-sandbox',
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            "--print-to-pdf={$pdf}",
            'file://'.$html,
        ]);

        if (! $result->successful() || ! is_file($pdf)) {
            @unlink($html);

            return back()->with('error', 'Chromium could not render the PDF: '.trim($result->errorOutput() ?: 'unknown error'));
        }

        return response()->download($pdf, $name)->deleteFileAfterSend(true);
    }

    private function moduleSheetData(Request $request, Module $module): array
    {
        $options = PrintOptions::fromRequest($request);
        $presenter = CardPresenter::make($options->autoIcons);

        $module->load(['entityCards.faces.cardType', 'entityCards.module', 'boardCards.module']);

        $cards = collect();

        if (in_array($options->deck, ['entity', 'all'], true)) {
            foreach ($module->entityCards as $card) {
                for ($i = 0; $i < $card->qty; $i++) {
                    $cards->push(['kind' => 'entity'] + $presenter->entityCard($card));
                }
            }
        }

        if (in_array($options->deck, ['board', 'all'], true)) {
            foreach ($module->boardCards as $card) {
                for ($i = 0; $i < $card->qty; $i++) {
                    $cards->push(['kind' => 'board'] + $presenter->boardCard($card));
                }
            }
        }

        return [
            'scenario' => $module,
            'options' => $options,
            'pages' => $cards->chunk($options->perPage())->values(),
            'cardCount' => $cards->count(),
        ];
    }

    private function sheetData(Request $request, Scenario $scenario): array
    {
        $options = PrintOptions::fromRequest($request);
        $presenter = CardPresenter::make($options->autoIcons);

        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat', 'boardCards.addedByBeat', 'storyBeats']);

        $cards = collect();

        if (in_array($options->deck, ['entity', 'all'], true)) {
            foreach ($scenario->entityCards as $card) {
                // The printer needs one card per copy, not a quantity field.
                for ($i = 0; $i < $card->qty; $i++) {
                    $cards->push(['kind' => 'entity'] + $presenter->entityCard($card));
                }
            }
        }

        if (in_array($options->deck, ['board', 'all'], true)) {
            foreach ($scenario->boardCards as $card) {
                for ($i = 0; $i < $card->qty; $i++) {
                    $cards->push(['kind' => 'board'] + $presenter->boardCard($card));
                }
            }
        }

        if (in_array($options->deck, ['beats', 'all'], true)) {
            foreach ($scenario->storyBeats as $beat) {
                $cards->push(['kind' => 'beat'] + $presenter->storyBeat($beat));
            }
        }

        $pages = $cards->chunk($options->perPage())->values();

        return [
            'scenario' => $scenario,
            'options' => $options,
            'pages' => $pages,
            'cardCount' => $cards->count(),
        ];
    }

    private function chromiumBinary(): ?string
    {
        $candidates = array_filter([
            env('CHROMIUM_BINARY'),
            '/opt/pw-browsers/chromium',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
        ]);

        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }
}
