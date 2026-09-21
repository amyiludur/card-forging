<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Domain;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $scenarios = Scenario::withCount(['entityCards', 'boardCards', 'storyBeats'])->orderBy('name')->get();

        return Inertia::render('Dashboard', [
            'scenarios' => $scenarios->map(fn (Scenario $s) => [
                'slug' => $s->slug,
                'name' => $s->name,
                'entity_type' => $s->entity_type,
                'status' => $s->status,
                'overview' => $s->overview,
                'deck_size' => $s->deckSize(),
                'entity_cards_count' => $s->entity_cards_count,
                'board_cards_count' => $s->board_cards_count,
                'story_beats_count' => $s->story_beats_count,
            ]),
            'characters' => Character::orderBy('sort')->orderBy('name')->get()
                ->map(fn (Character $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'identity' => $c->identity,
                    'health' => $c->health,
                    'health_equation' => $c->health_equation,
                    'hand_size' => $c->hand_size,
                    'hand_size_equation' => $c->hand_size_equation,
                    'gold_per_round' => $c->gold_per_round,
                    'gold_per_round_equation' => $c->gold_per_round_equation,
                    'signature_count' => $c->signatureCount(),
                ]),
            'domains' => Domain::with('cards')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Domain $d) => [
                    'slug' => $d->slug,
                    'name' => $d->name,
                    'identity' => $d->identity,
                    'is_neutral' => $d->is_neutral,
                    'pool_size' => $d->poolSize(),
                ]),
            'stats' => [
                'placeholder_values' => RulesConfig::where('is_placeholder', true)->count(),
                'config_values' => RulesConfig::count(),
                // Entity and player cards are separate decks; counting them as
                // one number would hide which side still needs work.
                'placeholder_cards' => EntityCard::where('is_placeholder', true)->count(),
                'placeholder_player_cards' => PlayerCard::where('is_placeholder', true)->count(),
                'characters' => Character::count(),
                'domains' => Domain::count(),
                'rule_documents' => RuleDocument::count(),
            ],
        ]);
    }
}
