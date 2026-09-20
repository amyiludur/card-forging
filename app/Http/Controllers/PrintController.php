<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Domain;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Support\CardPresenter;
use App\Support\DeckBuild;
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
            'layout' => $options->layout(),
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
            'layout' => $options->layout(),
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
            'pages' => $options->paginate($cards),
            'cardCount' => $cards->count(),
        ];
    }

    /**
     * A built deck: a character, a domain and the cards taken out of it. The
     * whole build travels in the query string, so a printed deck is exactly the
     * one the builder was showing.
     */
    public function deckOptions(Request $request): Response
    {
        $options = PrintOptions::fromRequest($request, 'player');
        $build = $this->deckBuild($request);

        return Inertia::render('Print/Options', [
            'scenario' => ['slug' => 'deck', 'name' => $this->deckName($build)],
            'kind' => 'deck',
            // Carried through every link and every reload, or the sheet would
            // print a different deck from the one being looked at.
            'context' => [
                'character' => $build->character?->slug,
                'domain' => $build->domain?->slug,
                'take' => $build->taking(),
            ],
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'decks' => PrintOptions::DECK_DECKS,
            'layout' => $options->layout(),
            'counts' => [
                'entity' => $build->deck()->count(),
                'board' => 0,
                'beats' => 0,
            ],
        ]);
    }

    public function deckSheet(Request $request): HttpResponse
    {
        return response(View::make('print.sheet', $this->deckSheetData($request))->render());
    }

    public function deckPdf(Request $request)
    {
        $build = $this->deckBuild($request);

        return $this->renderPdf(
            $this->deckSheetData($request),
            ($build->character?->slug ?? 'deck').'-'.($build->domain?->slug ?? 'no-domain').'.pdf'
        );
    }

    private function deckName(DeckBuild $build): string
    {
        return ($build->character?->name ?? 'Deck').' — '.($build->domain?->name ?? 'no domain');
    }

    private function deckBuild(Request $request): DeckBuild
    {
        $take = [];

        foreach ((array) $request->input('take', []) as $slug => $count) {
            if (is_string($slug) && is_numeric($count) && (int) $count > 0) {
                $take[$slug] = min((int) $count, 99);
            }
        }

        return DeckBuild::for(
            Character::with('cards')->where('slug', $request->string('character'))->first(),
            Domain::with('cards')->where('slug', $request->string('domain'))->first(),
            $take,
        );
    }

    private function deckSheetData(Request $request): array
    {
        $options = PrintOptions::fromRequest($request, 'player');
        $presenter = CardPresenter::make($options->autoIcons);
        $build = $this->deckBuild($request);

        $cards = collect();

        if ($build->character !== null && in_array($options->deck, ['character', 'all'], true)) {
            $cards->push(['kind' => 'character'] + $presenter->character($build->character));
        }

        // The deck is already one entry per copy: that is what a deck is.
        if (in_array($options->deck, ['player', 'all'], true)) {
            foreach ($build->deck() as $card) {
                $cards->push(['kind' => 'player'] + $presenter->playerCard($card));
            }
        }

        if (in_array($options->deck, ['extras', 'all'], true)) {
            foreach ($build->kit()->concat($build->upgrades()) as $card) {
                $cards->push(['kind' => 'player'] + $presenter->playerCard($card));
            }
        }

        return [
            'scenario' => (object) ['name' => $this->deckName($build)],
            'options' => $options,
            'pages' => $options->paginate($cards),
            'cardCount' => $cards->count(),
        ];
    }

    /** A domain prints as its own pool, set icon and all. */
    public function domainOptions(Request $request, Domain $domain): Response
    {
        $options = PrintOptions::fromRequest($request, 'player');

        return Inertia::render('Print/Options', [
            'scenario' => ['slug' => $domain->slug, 'name' => $domain->name],
            'kind' => 'domain',
            'options' => $options->toArray(),
            'cardSizes' => PrintOptions::CARD_SIZES,
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'decks' => PrintOptions::DOMAIN_DECKS,
            'layout' => $options->layout(),
            'counts' => [
                'entity' => (int) $domain->cards()->sum('qty'),
                'board' => 0,
                'beats' => 0,
            ],
        ]);
    }

    public function domainSheet(Request $request, Domain $domain): HttpResponse
    {
        return response(View::make('print.sheet', $this->domainSheetData($request, $domain))->render());
    }

    public function domainPdf(Request $request, Domain $domain)
    {
        return $this->renderPdf(
            $this->domainSheetData($request, $domain),
            $domain->slug.'-'.PrintOptions::fromRequest($request, 'player')->deck.'.pdf'
        );
    }

    private function domainSheetData(Request $request, Domain $domain): array
    {
        $options = PrintOptions::fromRequest($request, 'player');
        $presenter = CardPresenter::make($options->autoIcons);

        $domain->load('cards');
        $domain->cards->each->setRelation('domain', $domain);

        // The pool is what fills a slot; upgrades are set aside for the Smithy.
        $roles = match ($options->deck) {
            'upgrade' => [PlayerCard::ROLE_UPGRADE],
            'player' => [PlayerCard::ROLE_DOMAIN],
            default => [PlayerCard::ROLE_DOMAIN, PlayerCard::ROLE_UPGRADE],
        };

        $cards = collect();

        foreach ($domain->cards->whereIn('role', $roles) as $card) {
            // One printed card per copy: they are all things to cut out.
            for ($i = 0; $i < $card->qty; $i++) {
                $cards->push(['kind' => 'player'] + $presenter->playerCard($card));
            }
        }

        return [
            'scenario' => $domain,
            'options' => $options,
            'pages' => $options->paginate($cards),
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
            'layout' => $options->layout(),
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
            'pages' => $options->paginate($cards),
            'cardCount' => $cards->count(),
        ];
    }

    private function sheetData(Request $request, Scenario $scenario): array
    {
        $options = PrintOptions::fromRequest($request);
        $presenter = CardPresenter::make($options->autoIcons);

        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat', 'boardCards.addedByBeat', 'storyBeats']);

        // The cards already know their scenario — it is the one being printed —
        // so it is handed to them rather than queried back per card. Same
        // pattern as a character's cards on the sheets above, and it is what
        // {dreadRule} reads.
        $scenario->entityCards->each->setRelation('scenario', $scenario);
        $scenario->boardCards->each->setRelation('scenario', $scenario);
        $scenario->storyBeats->each->setRelation('scenario', $scenario);

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

        return [
            'scenario' => $scenario,
            'options' => $options,
            'pages' => $options->paginate($cards),
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
