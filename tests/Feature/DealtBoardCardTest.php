<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A board card that is dealt to the players rather than put out on the board:
 * the Meddler in the Dark's townsfolk, one to each player at random and the
 * rest kept aside. `start` is the one fact the tool keeps about it.
 */
class DealtBoardCardTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/design-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function exported(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/data/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    /** One of the Kraken's setup cards, whichever comes first. */
    private function krakenSetupCard(): BoardCard
    {
        return Scenario::where('slug', 'kraken')->firstOrFail()
            ->boardCards()->whereNull('added_by_beat_id')->orderBy('sort')->firstOrFail();
    }

    public function test_a_board_card_starts_on_the_board_by_default(): void
    {
        $this->artisan('design:import');

        $card = Scenario::where('slug', 'kraken')->firstOrFail()->boardCards()->create(['name' => 'Wreckage', 'qty' => 1]);

        $this->assertSame('board', $card->fresh()->start);
    }

    public function test_export_writes_start_only_on_a_dealt_card(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $card->update(['start' => 'dealt']);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $kraken = $this->exported('kraken.json');
        $dealt = collect($kraken['boardSetup'])->firstWhere('name', $card->name);

        $this->assertSame('dealt', $dealt['start']);

        foreach ($kraken['boardSetup'] as $entry) {
            if ($entry['name'] !== $card->name) {
                $this->assertArrayNotHasKey('start', $entry, "{$entry['name']} is not dealt");
            }
        }
        foreach ($kraken['boardAddedByBeats'] as $entry) {
            $this->assertArrayNotHasKey('start', $entry);
        }
    }

    public function test_an_undealt_folder_grows_no_start_key(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        foreach (['kraken.json', 'march-of-dead-legion.json'] as $file) {
            $this->assertFalse(str_contains(file_get_contents("{$this->path}/data/{$file}"), '"start"'), "{$file} grew a start key");
            $this->assertFalse(str_contains(file_get_contents("{$this->path}/data/{$file}"), '"dealGroup"'), "{$file} grew a dealGroup key");
        }
    }

    public function test_export_writes_the_deal_group_beside_start(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $card->update(['start' => 'dealt', 'deal_group' => 'Townsfolk']);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $entry = collect($this->exported('kraken.json')['boardSetup'])->firstWhere('name', $card->name);

        $this->assertSame('dealt', $entry['start']);
        $this->assertSame('Townsfolk', $entry['dealGroup']);
    }

    public function test_import_reads_the_deal_group(): void
    {
        File::copyDirectory(base_path('design'), $this->path);

        $file = "{$this->path}/data/kraken.json";
        $kraken = json_decode(file_get_contents($file), true);
        $kraken['boardSetup'][0]['start'] = 'dealt';
        $kraken['boardSetup'][0]['dealGroup'] = '  Townsfolk ';
        // A group on a card that is not dealt means nothing, so it is not kept.
        $kraken['boardSetup'][1]['dealGroup'] = 'Curses';
        file_put_contents($file, json_encode($kraken, JSON_PRETTY_PRINT));

        // Dropped, but said: the next export would otherwise delete what was typed without a word.
        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain("board card \"{$kraken['boardSetup'][1]['name']}\" has the deal group \"Curses\" but is not dealt")
            ->assertSuccessful();

        $this->assertSame('Townsfolk', BoardCard::where('name', $kraken['boardSetup'][0]['name'])->firstOrFail()->deal_group);
        $this->assertNull(BoardCard::where('name', $kraken['boardSetup'][1]['name'])->firstOrFail()->deal_group);
    }

    public function test_the_editor_saves_a_deal_group(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'dealt', 'deal_group' => 'Townsfolk']))->assertRedirect();

        $this->assertSame('Townsfolk', $card->fresh()->deal_group);
    }

    public function test_a_card_put_back_on_the_board_loses_its_group(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $card->update(['start' => 'dealt', 'deal_group' => 'Townsfolk']);

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'board', 'deal_group' => 'Townsfolk']))->assertRedirect();

        $this->assertNull($card->fresh()->deal_group);
    }

    public function test_the_playtest_page_receives_the_deal_group(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $card->update(['start' => 'dealt', 'deal_group' => 'Townsfolk']);

        $this->get('/scenarios/kraken/play')->assertInertia(function (Assert $page) use ($card) {
            $cards = collect($page->toArray()['props']['boardCards'])->keyBy('id');

            $this->assertSame('Townsfolk', $cards[$card->id]['deal_group']);
        });
    }

    public function test_import_reads_dealt_and_warns_on_nonsense(): void
    {
        File::copyDirectory(base_path('design'), $this->path);

        $file = "{$this->path}/data/kraken.json";
        $kraken = json_decode(file_get_contents($file), true);
        $kraken['boardSetup'][0]['start'] = 'dealt';
        $kraken['boardSetup'][1]['start'] = 'sideways';
        file_put_contents($file, json_encode($kraken, JSON_PRETTY_PRINT));

        $dealtName = $kraken['boardSetup'][0]['name'];
        $oddName = $kraken['boardSetup'][1]['name'];

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain("board card \"{$oddName}\" starts \"sideways\"")
            ->assertSuccessful();

        $this->assertSame('dealt', BoardCard::where('name', $dealtName)->firstOrFail()->start);
        $this->assertSame('board', BoardCard::where('name', $oddName)->firstOrFail()->start);
    }

    /** What the board card form sends back for a card, plus whatever changes. */
    private function form(BoardCard $card, array $changes): array
    {
        return [
            'name' => $card->name,
            'qty' => $card->qty,
            'health' => $card->health,
            'traits' => $card->traits ?? [],
            'text' => $card->text,
            'added_by_beat_id' => $card->added_by_beat_id,
            'is_placeholder' => $card->is_placeholder,
            ...$changes,
        ];
    }

    public function test_the_editor_saves_dealt(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'dealt']))->assertRedirect();

        $this->assertSame('dealt', $card->fresh()->start);
    }

    public function test_a_beat_card_is_never_dealt(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $beat = Scenario::where('slug', 'kraken')->firstOrFail()->storyBeats()->firstOrFail();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'dealt', 'added_by_beat_id' => $beat->id]))->assertRedirect();

        $card->refresh();
        $this->assertSame('board', $card->start);
        $this->assertSame($beat->id, $card->added_by_beat_id);
    }

    public function test_a_module_board_card_is_never_dealt(): void
    {
        $this->artisan('design:import');

        $this->post('/modules/what-lurks-below/board-cards', [
            'name' => 'Driftwood',
            'qty' => 1,
            'start' => 'dealt',
            'is_placeholder' => true,
        ])->assertRedirect();

        $this->assertSame('board', BoardCard::where('name', 'Driftwood')->firstOrFail()->start);
    }

    public function test_editing_a_module_board_card_never_deals_it(): void
    {
        $this->artisan('design:import');
        $card = BoardCard::whereNotNull('module_id')->firstOrFail();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'dealt']))->assertRedirect();

        $this->assertSame('board', $card->fresh()->start);
    }

    public function test_nonsense_start_is_rejected(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['start' => 'sideways']))->assertSessionHasErrors('start');

        $this->assertSame('board', $card->fresh()->start);
    }

    public function test_the_playtest_page_receives_start(): void
    {
        $this->artisan('design:import');
        $card = $this->krakenSetupCard();
        $card->update(['start' => 'dealt']);

        $this->get('/scenarios/kraken/play')->assertInertia(function (Assert $page) use ($card) {
            $cards = collect($page->toArray()['props']['boardCards'])->keyBy('id');

            $this->assertSame('dealt', $cards[$card->id]['start']);
        });
    }
}
