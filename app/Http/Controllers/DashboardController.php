<?php

namespace App\Http\Controllers;

use App\Models\EntityCard;
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
            'stats' => [
                'placeholder_values' => RulesConfig::where('is_placeholder', true)->count(),
                'config_values' => RulesConfig::count(),
                'placeholder_cards' => EntityCard::where('is_placeholder', true)->count(),
                'rule_documents' => RuleDocument::count(),
            ],
        ]);
    }
}
