<?php

namespace Tests\Feature;

use App\Models\Tale;
use App\Models\TaleLogEntry;
use App\Models\TaleMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Tale's difficulty modes and its log entries: both the designer's own, both
 * placeholders until marked otherwise, and the tool caps neither.
 */
class TaleModeAndLogTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);
    }

    public function test_a_mode_is_added_edited_and_removed(): void
    {
        $this->post('/tales/drowned-coast/modes', ['name' => 'Hard', 'text' => 'Damage carries over.'])
            ->assertRedirect('/tales/drowned-coast?tab=modes');

        $mode = TaleMode::firstOrFail();
        $this->assertTrue($mode->is_placeholder);
        $this->assertSame('Damage carries over.', $mode->text);

        $this->put("/tale-modes/{$mode->id}", ['name' => 'Hard', 'text' => 'Keep all damage.', 'is_placeholder' => false])
            ->assertRedirect('/tales/drowned-coast?tab=modes');
        $this->assertSame('Keep all damage.', $mode->fresh()->text);
        $this->assertFalse($mode->fresh()->is_placeholder);

        $this->delete("/tale-modes/{$mode->id}")->assertRedirect('/tales/drowned-coast?tab=modes');
        $this->assertSame(0, TaleMode::count());
    }

    public function test_the_tool_does_not_cap_how_many_modes_a_tale_has(): void
    {
        foreach (['Story', 'Standard', 'Hard', 'Heroic'] as $name) {
            $this->post('/tales/drowned-coast/modes', ['name' => $name]);
        }

        $this->assertSame(['Story', 'Standard', 'Hard', 'Heroic'], $this->tale->modes()->pluck('name')->all());
    }

    public function test_modes_are_reordered_one_place_at_a_time(): void
    {
        foreach (['Hard', 'Story'] as $name) {
            $this->post('/tales/drowned-coast/modes', ['name' => $name]);
        }
        $story = TaleMode::where('name', 'Story')->first();

        $this->post("/tale-modes/{$story->id}/move", ['direction' => 'up'])->assertRedirect('/tales/drowned-coast?tab=modes');

        $this->assertSame(['Story', 'Hard'], $this->tale->modes()->pluck('name')->all());
    }

    public function test_log_entries_are_reordered_one_place_at_a_time(): void
    {
        foreach (['Ship damage', 'Damage kept', 'Rewards'] as $name) {
            $this->post('/tales/drowned-coast/log-entries', ['name' => $name, 'kind' => 'number', 'scope' => 'party']);
        }
        $first = TaleLogEntry::where('name', 'Ship damage')->first();

        $this->post("/tale-log-entries/{$first->id}/move", ['direction' => 'down'])->assertRedirect('/tales/drowned-coast?tab=log');

        $this->assertSame(['Damage kept', 'Ship damage', 'Rewards'], $this->tale->logEntries()->pluck('name')->all());
    }

    public function test_a_log_entry_has_a_kind_and_a_scope(): void
    {
        $this->post('/tales/drowned-coast/log-entries', ['name' => 'Damage kept', 'kind' => 'number', 'scope' => 'character'])
            ->assertRedirect('/tales/drowned-coast?tab=log');

        $entry = TaleLogEntry::firstOrFail();
        $this->assertSame('number', $entry->kind);
        $this->assertSame('character', $entry->scope);
        $this->assertTrue($entry->is_placeholder);

        $this->put("/tale-log-entries/{$entry->id}", ['name' => 'Lighthouse fell', 'kind' => 'check', 'scope' => 'party']);
        $this->assertSame('check', $entry->fresh()->kind);

        $this->delete("/tale-log-entries/{$entry->id}");
        $this->assertSame(0, TaleLogEntry::count());
    }

    public function test_a_log_entry_takes_only_the_known_kinds_and_scopes(): void
    {
        $this->post('/tales/drowned-coast/log-entries', ['name' => 'Dice', 'kind' => 'dice', 'scope' => 'party'])
            ->assertSessionHasErrors('kind');
        $this->post('/tales/drowned-coast/log-entries', ['name' => 'Seat', 'kind' => 'number', 'scope' => 'seat'])
            ->assertSessionHasErrors('scope');
        $this->post('/tales/drowned-coast/log-entries', ['name' => '', 'kind' => 'number', 'scope' => 'party'])
            ->assertSessionHasErrors('name');

        $this->assertSame(0, TaleLogEntry::count());
    }
}
