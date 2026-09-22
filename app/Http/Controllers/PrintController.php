<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PrintsItems;
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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Inertia\Response;

/**
 * Every print page works the same way: whatever is being printed is first laid
 * out as a list of items — one per card row, each in the group named after the
 * deck it belongs to — and the sheet and the card picker both read that one
 * list. So what the picker offers can never be a different set from what comes
 * out of the printer.
 *
 * A card is presented through a closure, because the options page needs a list
 * of names and the sheet needs the rendered card: the markup only runs for the
 * cards actually going on a sheet.
 */
class PrintController extends Controller
{
    use PrintsItems;

    public function options(Request $request, Scenario $scenario): Response
    {
        $options = PrintOptions::fromRequest($request);

        return $this->optionsPage($request, $options, $this->scenarioItems($scenario, $options), [
            'scenario' => ['slug' => $scenario->slug, 'name' => $scenario->name],
            'decks' => PrintOptions::SCENARIO_DECKS,
        ]);
    }

    public function sheet(Request $request, Scenario $scenario): HttpResponse
    {
        return response(View::make('print.sheet', $this->sheetData($request, $scenario))->render());
    }

    public function pdf(Request $request, Scenario $scenario)
    {
        return $this->renderPdf(
            $this->sheetData($request, $scenario),
            $scenario->slug.'-'.PrintOptions::fromRequest($request)->deck.'.pdf'
        );
    }

    private function sheetData(Request $request, Scenario $scenario): array
    {
        $options = PrintOptions::fromRequest($request);

        return $this->sheetFor($request, $options, $this->scenarioItems($scenario, $options), $scenario);
    }

    /**
     * A scenario is five piles of cards, not one: the setup card that lays the
     * table out, the entity deck it plays against, the board cards it is played
     * on, the beats it moves through and the town the players go back to
     * between rounds. All five are printable, separately or together.
     */
    private function scenarioItems(Scenario $scenario, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $scenario->load([
            'entityCards.faces.cardType', 'entityCards.addedByBeat',
            'boardCards.addedByBeat', 'storyBeats', 'townActions',
        ]);

        // The cards already know their scenario — it is the one being printed —
        // so it is handed to them rather than queried back per card. Same
        // pattern as a character's cards on the sheets below, and it is what
        // {dreadRule} reads.
        $scenario->entityCards->each->setRelation('scenario', $scenario);
        $scenario->boardCards->each->setRelation('scenario', $scenario);
        $scenario->storyBeats->each->setRelation('scenario', $scenario);
        $scenario->townActions->each->setRelation('scenario', $scenario);

        $items = collect();

        // One card, and only when the designer has written the steps: a
        // scenario whose setup is not written yet prints no setup card rather
        // than a blank one. Report, don't correct — the scenario's own page is
        // where the gap is named.
        if ($scenario->hasSetup()) {
            $items->push($this->item('setup', $scenario->id, $scenario->name.' setup', 1, false,
                fn () => ['kind' => 'setup'] + $presenter->setupCard($scenario)));
        }

        foreach ($scenario->entityCards as $card) {
            $items->push($this->item('entity', $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'entity'] + $presenter->entityCard($card)));
        }

        foreach ($scenario->boardCards as $card) {
            $items->push($this->item('board', $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'board'] + $presenter->boardCard($card)));
        }

        foreach ($scenario->storyBeats as $beat) {
            $items->push($this->item('beats', $beat->id, $beat->order.'. '.$beat->name, 1, false,
                fn () => ['kind' => 'beat'] + $presenter->storyBeat($beat)));
        }

        foreach ($scenario->townActions as $action) {
            $items->push($this->item('town', $action->id, $action->name, 1, false,
                fn () => ['kind' => 'town'] + $presenter->townAction($action)));
        }

        return $items;
    }

    /** A character prints its own deck: the 20, the kit and the upgrades. */
    public function characterOptions(Request $request, Character $character): Response
    {
        $options = PrintOptions::fromRequest($request, 'player');

        return $this->optionsPage($request, $options, $this->characterItems($character, $options), [
            'scenario' => ['slug' => $character->slug, 'name' => $character->name],
            'kind' => 'character',
            'decks' => PrintOptions::CHARACTER_DECKS,
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

        return $this->sheetFor($request, $options, $this->characterItems($character, $options), $character);
    }

    private function characterItems(Character $character, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $character->load('cards');
        $character->cards->each->setRelation('character', $character);

        $items = collect([
            $this->item('character', $character->id, $character->name, 1, $character->is_placeholder,
                fn () => ['kind' => 'character'] + $presenter->character($character)),
        ]);

        // One printed card per copy, upgrades and kit included: they are all
        // things the designer has to cut out.
        foreach ($character->cards as $card) {
            $items->push($this->item('player', $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'player'] + $presenter->playerCard($card)));
        }

        return $items;
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

        return $this->optionsPage($request, $options, $this->deckItems($build, $options), [
            'scenario' => ['slug' => 'deck', 'name' => $this->deckName($build)],
            'kind' => 'deck',
            // Carried through every link and every reload, or the sheet would
            // print a different deck from the one being looked at.
            'context' => [
                'character' => $build->character?->slug,
                'domain' => $build->domain?->slug,
                'take' => $build->taking(),
            ],
            'decks' => PrintOptions::DECK_DECKS,
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
        $build = $this->deckBuild($request);

        return $this->sheetFor(
            $request,
            $options,
            $this->deckItems($build, $options),
            (object) ['name' => $this->deckName($build)],
        );
    }

    private function deckItems(DeckBuild $build, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $items = collect();

        if ($build->character !== null) {
            $character = $build->character;

            $items->push($this->item('character', $character->id, $character->name, 1, $character->is_placeholder,
                fn () => ['kind' => 'character'] + $presenter->character($character)));
        }

        // The deck and the extras are already one entry per copy: that is what
        // a deck is. The picker wants one row per card, so they are counted
        // back up rather than listed twice.
        $items = $items
            ->concat($this->playerItems($build->deck(), 'player', $presenter))
            ->concat($this->playerItems($build->kit()->concat($build->upgrades()), 'extras', $presenter));

        return $items;
    }

    /** A run of printed copies, back to one item per card with its count. */
    private function playerItems(Collection $cards, string $group, CardPresenter $presenter): Collection
    {
        return $cards
            ->groupBy(fn (PlayerCard $card) => $card->id)
            ->map(function (Collection $copies) use ($group, $presenter) {
                $card = $copies->first();

                return $this->item($group, $card->id, $card->name, $copies->count(), $card->is_placeholder,
                    fn () => ['kind' => 'player'] + $presenter->playerCard($card));
            })
            ->values();
    }

    /** A domain prints as its own pool, set icon and all. */
    public function domainOptions(Request $request, Domain $domain): Response
    {
        $options = PrintOptions::fromRequest($request, 'player');

        return $this->optionsPage($request, $options, $this->domainItems($domain, $options), [
            'scenario' => ['slug' => $domain->slug, 'name' => $domain->name],
            'kind' => 'domain',
            'decks' => PrintOptions::DOMAIN_DECKS,
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

        return $this->sheetFor($request, $options, $this->domainItems($domain, $options), $domain);
    }

    private function domainItems(Domain $domain, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $domain->load('cards');
        $domain->cards->each->setRelation('domain', $domain);

        $items = collect();

        // The pool is what fills a slot; upgrades are set aside for the Smithy.
        foreach ($domain->cards as $card) {
            $group = $card->role === PlayerCard::ROLE_UPGRADE ? 'upgrade' : 'player';

            $items->push($this->item($group, $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'player'] + $presenter->playerCard($card)));
        }

        return $items;
    }

    /** A module prints as its own deck, set icon and all. */
    public function moduleOptions(Request $request, Module $module): Response
    {
        $options = PrintOptions::fromRequest($request);

        return $this->optionsPage($request, $options, $this->moduleItems($module, $options), [
            'scenario' => ['slug' => $module->slug, 'name' => $module->name],
            'isModule' => true,
            'decks' => ['entity' => 'Module cards', 'board' => 'Module board cards', 'all' => 'Everything'],
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

    private function moduleSheetData(Request $request, Module $module): array
    {
        $options = PrintOptions::fromRequest($request);

        return $this->sheetFor($request, $options, $this->moduleItems($module, $options), $module);
    }

    private function moduleItems(Module $module, PrintOptions $options): Collection
    {
        $presenter = CardPresenter::make($options->autoIcons);

        $module->load(['entityCards.faces.cardType', 'entityCards.module', 'boardCards.module']);

        $items = collect();

        foreach ($module->entityCards as $card) {
            $items->push($this->item('entity', $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'entity'] + $presenter->entityCard($card)));
        }

        foreach ($module->boardCards as $card) {
            $items->push($this->item('board', $card->id, $card->name, $card->qty, $card->is_placeholder,
                fn () => ['kind' => 'board'] + $presenter->boardCard($card)));
        }

        return $items;
    }
}
