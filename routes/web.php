<?php

use App\Http\Controllers\BoardCardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EntityCardController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\RuleDocumentController;
use App\Http\Controllers\RulesConfigController;
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
Route::put('board-cards/{boardCard}', [BoardCardController::class, 'update'])->name('board-cards.update');
Route::delete('board-cards/{boardCard}', [BoardCardController::class, 'destroy'])->name('board-cards.destroy');

Route::post('scenarios/{scenario}/town-actions', [TownActionController::class, 'store'])->name('town-actions.store');
Route::put('town-actions/{townAction}', [TownActionController::class, 'update'])->name('town-actions.update');
Route::delete('town-actions/{townAction}', [TownActionController::class, 'destroy'])->name('town-actions.destroy');

Route::get('rules/config', [RulesConfigController::class, 'index'])->name('rules.config');
Route::put('rules/config', [RulesConfigController::class, 'update'])->name('rules.config.update');

Route::get('rules', [RuleDocumentController::class, 'index'])->name('rules.index');
Route::post('rules', [RuleDocumentController::class, 'store'])->name('rules.store');
Route::get('rules/{document}', [RuleDocumentController::class, 'show'])->name('rules.show');
Route::put('rules/{document}', [RuleDocumentController::class, 'update'])->name('rules.update');
Route::post('rules/{document}/restore/{version}', [RuleDocumentController::class, 'restore'])->name('rules.restore');

Route::get('print/{scenario}', [PrintController::class, 'options'])->name('print.options');
Route::get('print/{scenario}/sheet', [PrintController::class, 'sheet'])->name('print.sheet');
Route::get('print/{scenario}/pdf', [PrintController::class, 'pdf'])->name('print.pdf');
