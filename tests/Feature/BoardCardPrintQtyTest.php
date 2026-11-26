<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A board card's setup qty and its print qty are two numbers. Setup may put
 * none out — a card another card brings into play — and the print run is its
 * own number when set, or what setup puts out (at least one) when not.
 */
class BoardCardPrintQtyTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/design-'.uniqid());
        $this->artisan('design:import');
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

    private function krakenSetupCard(): BoardCard
    {
        return Scenario::where('slug', 'kraken')->firstOrFail()
            ->boardCards()->whereNull('added_by_beat_id')->orderBy('sort')->firstOrFail();
    }

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

    private function boardCount(): int
    {
        return $this->get('/print/kraken')->viewData('page')['props']['counts']['board'];
    }

    public function test_print_copies_follow_setup_when_not_set(): void
    {
        $card = new BoardCard(['qty' => 3]);
        $this->assertSame(3, $card->printCopies());

        $card->qty = 0;
        $this->assertSame(1, $card->printCopies(), 'a card none of goes out still prints one');

        $card->print_qty = 4;
        $this->assertSame(4, $card->printCopies());
    }

    public function test_the_editor_saves_a_setup_qty_of_zero_and_a_print_qty(): void
    {
        $card = $this->krakenSetupCard();

        $this->put("/board-cards/{$card->id}", $this->form($card, ['qty' => 0, 'print_qty' => 5]))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $card->refresh();
        $this->assertSame(0, $card->qty);
        $this->assertSame(5, $card->print_qty);
    }

    public function test_an_empty_print_qty_is_null_and_zero_is_rejected(): void
    {
        $card = $this->krakenSetupCard();
        $card->update(['print_qty' => 5]);

        $this->put("/board-cards/{$card->id}", $this->form($card, ['print_qty' => '']))->assertSessionHasNoErrors();
        $this->assertNull($card->fresh()->print_qty);

        $this->put("/board-cards/{$card->id}", $this->form($card, ['print_qty' => 0]))->assertSessionHasErrors('print_qty');
        $this->put("/board-cards/{$card->id}", $this->form($card, ['qty' => -1]))->assertSessionHasErrors('qty');
    }

    public function test_a_module_board_card_can_be_added_with_none_at_setup(): void
    {
        $this->post('/modules/what-lurks-below/board-cards', [
            'name' => 'Driftwood',
            'qty' => 0,
            'is_placeholder' => true,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame(0, BoardCard::where('name', 'Driftwood')->firstOrFail()->qty);
    }

    public function test_the_print_sheet_prints_the_print_qty(): void
    {
        $card = $this->krakenSetupCard();
        $before = $this->boardCount();

        $card->update(['qty' => 0, 'print_qty' => $card->printCopies() + 3]);

        $this->assertSame($before + 3, $this->boardCount());
    }

    public function test_the_print_sheet_prints_one_of_a_card_none_go_out_of(): void
    {
        $card = $this->krakenSetupCard();
        $before = $this->boardCount();
        $copies = $card->printCopies();

        $card->update(['qty' => 0, 'print_qty' => null]);

        $this->assertSame($before - $copies + 1, $this->boardCount());
    }

    public function test_export_writes_print_qty_only_when_set_and_round_trips(): void
    {
        $card = $this->krakenSetupCard();
        $card->update(['qty' => 0, 'print_qty' => 4]);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $kraken = $this->exported('kraken.json');
        $written = collect($kraken['boardSetup'])->firstWhere('name', $card->name);
        $this->assertSame(0, $written['qty']);
        $this->assertSame(4, $written['printQty']);

        foreach ([...$kraken['boardSetup'], ...$kraken['boardAddedByBeats']] as $entry) {
            if ($entry['name'] !== $card->name) {
                $this->assertArrayNotHasKey('printQty', $entry, "{$entry['name']} has no print qty of its own");
            }
        }

        $card->update(['qty' => 2, 'print_qty' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $card->refresh();
        $this->assertSame(0, $card->qty);
        $this->assertSame(4, $card->print_qty);
    }

    public function test_a_module_board_card_with_none_at_setup_round_trips(): void
    {
        $card = BoardCard::whereNotNull('module_id')->firstOrFail();
        $card->update(['qty' => 0, 'print_qty' => 3]);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $card->update(['qty' => 1, 'print_qty' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $card->refresh();
        $this->assertSame(0, $card->qty, 'a 0 must be written, or the import reads it as 1');
        $this->assertSame(3, $card->print_qty);
    }

    public function test_the_playtest_page_receives_both_numbers(): void
    {
        $card = $this->krakenSetupCard();
        $card->update(['qty' => 0, 'print_qty' => 2]);

        $this->get('/scenarios/kraken/play')->assertInertia(fn (Assert $page) => $page
            ->where('boardCards', fn ($cards) => collect($cards)->contains(
                fn ($c) => $c['id'] === $card->id && $c['qty'] === 0 && $c['print_qty'] === 2
            )));
    }
}
