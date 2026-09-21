<?php

use App\Http\Controllers\BoardCardController;
use App\Http\Controllers\CardTypeController;
use App\Http\Controllers\CharacterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeckBuilderController;
use App\Http\Controllers\DeckController;
use App\Http\Controllers\DesignFolderController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\EntityCardController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PlayerCardController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\PrintPresetController;
use App\Http\Controllers\RuleDocumentController;
use App\Http\Controllers\RulesConfigController;
use App\Http\Controllers\SavedDeckController;
use App\Http\Controllers\ScenarioController;
use App\Http\Controllers\StoryBeatController;
use App\Http\Controllers\TownActionController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

Route::get('scenarios', [ScenarioController::class, 'index'])->name('scenarios.index');
Route::get('scenarios/create', [ScenarioController::class, 'create'])->name('scenarios.create');
Route::post('scenarios', [ScenarioController::class, 'store'])->name('scenarios.store');
Route::get('scenarios/{scenario}', [ScenarioController::class, 'show'])->name('scenarios.show');
Route::get('scenarios/{scenario}/edit', [ScenarioController::class, 'edit'])->name('scenarios.edit');
Route::put('scenarios/{scenario}', [ScenarioController::class, 'update'])->name('scenarios.update');
Route::delete('scenarios/{scenario}', [ScenarioController::class, 'destroy'])->name('scenarios.destroy');

Route::get('scenarios/{scenario}/deck', [DeckController::class, 'assembly'])->name('scenarios.deck');
Route::get('scenarios/{scenario}/storyline', [DeckController::class, 'storyline'])->name('scenarios.storyline');
Route::get('scenarios/{scenario}/play', [DeckController::class, 'play'])->name('scenarios.play');

Route::get('modules', [ModuleController::class, 'index'])->name('modules.index');
Route::get('modules/create', [ModuleController::class, 'create'])->name('modules.create');
Route::post('modules', [ModuleController::class, 'store'])->name('modules.store');
Route::get('modules/{module}', [ModuleController::class, 'show'])->name('modules.show');
Route::get('modules/{module}/edit', [ModuleController::class, 'edit'])->name('modules.edit');
Route::put('modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
Route::delete('modules/{module}', [ModuleController::class, 'destroy'])->name('modules.destroy');

// A deck is a character plus a domain, so it belongs to neither: the whole
// choice lives in the query string.
Route::get('decks', [DeckBuilderController::class, 'index'])->name('decks.index');

// A deck's query string saved under a name, offered on the deck builder.
Route::post('saved-decks', [SavedDeckController::class, 'store'])->name('saved-decks.store');
Route::delete('saved-decks/{savedDeck}', [SavedDeckController::class, 'destroy'])->name('saved-decks.destroy');

Route::get('domains', [DomainController::class, 'index'])->name('domains.index');
Route::get('domains/create', [DomainController::class, 'create'])->name('domains.create');
Route::post('domains', [DomainController::class, 'store'])->name('domains.store');
Route::get('domains/{domain}', [DomainController::class, 'show'])->name('domains.show');
Route::get('domains/{domain}/edit', [DomainController::class, 'edit'])->name('domains.edit');
Route::put('domains/{domain}', [DomainController::class, 'update'])->name('domains.update');
Route::delete('domains/{domain}', [DomainController::class, 'destroy'])->name('domains.destroy');

Route::get('characters', [CharacterController::class, 'index'])->name('characters.index');
Route::get('characters/create', [CharacterController::class, 'create'])->name('characters.create');
Route::post('characters', [CharacterController::class, 'store'])->name('characters.store');
Route::get('characters/{character}', [CharacterController::class, 'show'])->name('characters.show');
Route::get('characters/{character}/edit', [CharacterController::class, 'edit'])->name('characters.edit');
Route::put('characters/{character}', [CharacterController::class, 'update'])->name('characters.update');
Route::delete('characters/{character}', [CharacterController::class, 'destroy'])->name('characters.destroy');

Route::get('characters/{character}/cards/create', [PlayerCardController::class, 'create'])->name('player-cards.create');
Route::post('characters/{character}/cards', [PlayerCardController::class, 'store'])->name('player-cards.store');
// A domain's cards go through the same editor: only the owner differs.
Route::get('domains/{domain}/cards/create', [PlayerCardController::class, 'createForDomain'])->name('domain-cards.create');
Route::post('domains/{domain}/cards', [PlayerCardController::class, 'storeForDomain'])->name('domain-cards.store');

Route::get('player-cards/{playerCard}/edit', [PlayerCardController::class, 'edit'])->name('player-cards.edit');
Route::put('player-cards/{playerCard}', [PlayerCardController::class, 'update'])->name('player-cards.update');
Route::post('player-cards/{playerCard}/duplicate', [PlayerCardController::class, 'duplicate'])->name('player-cards.duplicate');
Route::delete('player-cards/{playerCard}', [PlayerCardController::class, 'destroy'])->name('player-cards.destroy');

Route::get('cards', [EntityCardController::class, 'index'])->name('cards.index');
Route::get('cards/create', [EntityCardController::class, 'create'])->name('cards.create');
Route::post('cards', [EntityCardController::class, 'store'])->name('cards.store');
Route::get('cards/{card}/edit', [EntityCardController::class, 'edit'])->name('cards.edit');
Route::put('cards/{card}', [EntityCardController::class, 'update'])->name('cards.update');
Route::post('cards/{card}/duplicate', [EntityCardController::class, 'duplicate'])->name('cards.duplicate');
Route::delete('cards/{card}', [EntityCardController::class, 'destroy'])->name('cards.destroy');

Route::post('scenarios/{scenario}/beats', [StoryBeatController::class, 'store'])->name('beats.store');
Route::put('beats/{beat}', [StoryBeatController::class, 'update'])->name('beats.update');
Route::delete('beats/{beat}', [StoryBeatController::class, 'destroy'])->name('beats.destroy');

Route::post('scenarios/{scenario}/board-cards', [BoardCardController::class, 'store'])->name('board-cards.store');
Route::post('modules/{module}/board-cards', [BoardCardController::class, 'storeForModule'])->name('modules.board-cards.store');
Route::put('board-cards/{boardCard}', [BoardCardController::class, 'update'])->name('board-cards.update');
Route::delete('board-cards/{boardCard}', [BoardCardController::class, 'destroy'])->name('board-cards.destroy');

Route::post('scenarios/{scenario}/town-actions', [TownActionController::class, 'store'])->name('town-actions.store');
Route::put('town-actions/{townAction}', [TownActionController::class, 'update'])->name('town-actions.update');
Route::delete('town-actions/{townAction}', [TownActionController::class, 'destroy'])->name('town-actions.destroy');

// The design folder from a button: the same design:import and design:export
// the designer has always run in a terminal, plus the commit that followed.
Route::get('design', [DesignFolderController::class, 'index'])->name('design.index');
Route::post('design/import', [DesignFolderController::class, 'import'])->name('design.import');
Route::post('design/export', [DesignFolderController::class, 'export'])->name('design.export');
Route::post('design/publish', [DesignFolderController::class, 'publish'])->name('design.publish');

Route::get('rules/config', [RulesConfigController::class, 'index'])->name('rules.config');
Route::put('rules/config', [RulesConfigController::class, 'update'])->name('rules.config.update');

// Before rules/{document}, or a keyword page would be read as a rules page.
Route::get('rules/card-types', [CardTypeController::class, 'index'])->name('card-types.index');
Route::post('rules/card-types', [CardTypeController::class, 'store'])->name('card-types.store');
Route::put('rules/card-types/{cardType}', [CardTypeController::class, 'update'])->name('card-types.update');
Route::delete('rules/card-types/{cardType}', [CardTypeController::class, 'destroy'])->name('card-types.destroy');

// A type of one scenario's own is added where the designer is when they want
// one: on the scenario's own page.
Route::post('scenarios/{scenario}/card-types', [CardTypeController::class, 'storeForScenario'])->name('scenarios.card-types.store');

Route::get('rules/keywords', [KeywordController::class, 'index'])->name('keywords.index');
Route::post('rules/keywords', [KeywordController::class, 'store'])->name('keywords.store');
Route::put('rules/keywords/{keyword}', [KeywordController::class, 'update'])->name('keywords.update');
Route::delete('rules/keywords/{keyword}', [KeywordController::class, 'destroy'])->name('keywords.destroy');

Route::get('rules', [RuleDocumentController::class, 'index'])->name('rules.index');
Route::post('rules', [RuleDocumentController::class, 'store'])->name('rules.store');
Route::get('rules/{document}', [RuleDocumentController::class, 'show'])->name('rules.show');
Route::put('rules/{document}', [RuleDocumentController::class, 'update'])->name('rules.update');
Route::post('rules/{document}/restore/{version}', [RuleDocumentController::class, 'restore'])->name('rules.restore');

// A print setup saved under a name, offered on every print options page below.
Route::post('print-presets', [PrintPresetController::class, 'store'])->name('print-presets.store');
Route::delete('print-presets/{preset}', [PrintPresetController::class, 'destroy'])->name('print-presets.destroy');

Route::get('print/character/{character}', [PrintController::class, 'characterOptions'])->name('print.character.options');
Route::get('print/character/{character}/sheet', [PrintController::class, 'characterSheet'])->name('print.character.sheet');
Route::get('print/character/{character}/pdf', [PrintController::class, 'characterPdf'])->name('print.character.pdf');

// A built deck carries its whole build in the query string.
Route::get('print/deck', [PrintController::class, 'deckOptions'])->name('print.deck.options');
Route::get('print/deck/sheet', [PrintController::class, 'deckSheet'])->name('print.deck.sheet');
Route::get('print/deck/pdf', [PrintController::class, 'deckPdf'])->name('print.deck.pdf');

Route::get('print/domain/{domain}', [PrintController::class, 'domainOptions'])->name('print.domain.options');
Route::get('print/domain/{domain}/sheet', [PrintController::class, 'domainSheet'])->name('print.domain.sheet');
Route::get('print/domain/{domain}/pdf', [PrintController::class, 'domainPdf'])->name('print.domain.pdf');

Route::get('print/module/{module}', [PrintController::class, 'moduleOptions'])->name('print.module.options');
Route::get('print/module/{module}/sheet', [PrintController::class, 'moduleSheet'])->name('print.module.sheet');
Route::get('print/module/{module}/pdf', [PrintController::class, 'modulePdf'])->name('print.module.pdf');

Route::get('print/{scenario}', [PrintController::class, 'options'])->name('print.options');
Route::get('print/{scenario}/sheet', [PrintController::class, 'sheet'])->name('print.sheet');
Route::get('print/{scenario}/pdf', [PrintController::class, 'pdf'])->name('print.pdf');
