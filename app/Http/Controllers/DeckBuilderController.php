<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\SavedDeck;
use App\Support\CardPresenter;
use App\Support\DeckBuild;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Deck building: pick a character, pick a domain, take 20 of its cards. That
 * pairing is what a deck is, and it is made here rather than stored on either
 * side, because neither the character nor the domain owns the other.
 *
 * Everything is in the query string, the way a scenario's chosen modules are,
 * so a deck can be linked, reloaded and printed without a record of its own.
 */
class DeckBuilderController extends Controller
{
    public function index(Request $request): Response
    {
        $character = Character::with('cards')->where('slug', $request->string('character'))->first();
        $domain = Domain::with('cards')->where('slug', $request->string('domain'))->first();

        $build = DeckBuild::for($character, $domain, DeckBuild::takeFromRequest($request));
        $presenter = CardPresenter::make();
        $taking = $build->taking();

        return Inertia::render('Decks/Build', [
            'characters' => Character::with('cards')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Character $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'identity' => $c->identity,
                    'signature_count' => (int) $c->cards->where('role', PlayerCard::ROLE_SIGNATURE)->sum('qty'),
                ])->values(),
            // Any domain goes with any character, so every one is offered.
            'domains' => Domain::with('cards')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Domain $d) => [
                    'slug' => $d->slug,
                    'name' => $d->name,
                    'identity' => $d->identity,
                    'set_icon' => $d->set_icon,
                    'is_neutral' => $d->is_neutral,
                    'pool_size' => $d->poolSize(),
                ])->values(),
            'character' => $character === null ? null : $presenter->character($character),
            'domain' => $domain === null ? null : [
                'slug' => $domain->slug,
                'name' => $domain->name,
                'identity' => $domain->identity,
                'set_icon' => $domain->set_icon,
                'is_neutral' => $domain->is_neutral,
            ],
            // The pool to pick from, each card saying how many this deck takes.
            'pool' => $build->pool()->map(fn (PlayerCard $c) => $presenter->playerCard($c) + [
                'taken' => $taking[$c->slug] ?? 0,
                // The pool's print run, or the copy cap when that is tighter.
                'limit' => $build->limitFor($c),
            ])->values(),
            'take' => $taking,
            // Whether the colourless pool is adding cards to the domain chosen
            // above, so the page can say so beside "Take from ...".
            'has_neutral_pool' => $build->hasNeutralPool(),
            // The character's own half, listed rather than drawn: it is already
            // on the character page and this is about what the two halves make.
            'signature' => $build->signature()->unique('id')->values()
                ->map(fn (PlayerCard $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'qty' => $c->qty,
                    'type' => $c->type,
                    'omen_icons' => $c->omen_icons,
                    'gold_cost' => $c->gold_cost,
                    'start_zone' => $c->start_zone,
                ])->values(),
            'stats' => $build->stats(),
            // Reported, never corrected: the designer decides what is wrong.
            'warnings' => $build->warnings(),
            // Saved builds, offered the same way a print preset is: pick one
            // from the list and it is applied straight to the query string.
            'saved' => $this->saved(),
        ]);
    }

    private function saved(): array
    {
        return SavedDeck::orderBy('name')->get()
            ->map(fn (SavedDeck $deck) => [
                'id' => $deck->id,
                'name' => $deck->name,
                'build' => $deck->build,
            ])
            ->all();
    }
}
