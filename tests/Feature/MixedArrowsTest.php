<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Scenario;
use App\Support\DeckAssembly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * An entity card whose copies do not all point the same way: Tentacle Lash ×3
 * as two copies pointing top and one pointing bottom. `bottom_copies` is the
 * one fact the tool keeps; every place that lays copies out reads it.
 */
class MixedArrowsTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->path = storage_path('framework/testing/design-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    /** A fresh card of our own, so the test does not depend on the Kraken's draft. */
    private function mixedCard(int $qty = 3, ?int $bottom = 1): EntityCard
    {
        $scenario = Scenario::where('slug', 'kraken')->firstOrFail();

        $card = $scenario->entityCards()->create([
            'name' => 'Twin Current',
            'qty' => $qty,
            'layout' => 'single',
            'omen_cost' => 1,
            'arrow' => 'top',
            'bottom_copies' => $bottom,
            'sort' => 999,
        ]);
        $card->faces()->create(['half' => 'single', 'text' => 'Pull.', 'sort' => 0]);

        return $card->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Twin Current',
            'qty' => 3,
            'layout' => 'single',
            'omen_cost' => 1,
            'omen_is_x' => false,
            'traits' => [],
            'arrow' => 'top',
            'bottom_copies' => 1,
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => CardType::where('slug', 'attack')->value('id'), 'text' => 'Pull.']],
        ], $overrides);
    }

    public function test_each_copy_carries_its_own_arrow_top_copies_first(): void
    {
        $card = $this->mixedCard(3, 1);

        $this->assertTrue($card->hasMixedArrows());
        $this->assertSame(['top', 'top', 'bottom'], $card->copyArrows());
        $this->assertSame(['top' => 2, 'bottom' => 1], $card->arrowCounts());
        $this->assertNull($card->arrowSplitProblem());
        $this->assertSame(['top', 'top', 'bottom'], array_map(fn (EntityCard $c) => $c->pointsAt(), $card->copies()));
    }

    public function test_a_card_with_one_arrow_is_unchanged(): void
    {
        $card = $this->mixedCard(3, null);
        $card->update(['arrow' => 'bottom']);

        $this->assertFalse($card->hasMixedArrows());
        $this->assertSame(['bottom', 'bottom', 'bottom'], $card->copyArrows());
        $this->assertSame(['top' => 0, 'bottom' => 3], $card->arrowCounts());
    }

    public function test_a_split_that_does_not_fit_its_copies_is_reported_not_trimmed(): void
    {
        $card = $this->mixedCard(2, 3);

        $this->assertSame(3, $card->bottom_copies);
        $this->assertSame(['bottom', 'bottom'], $card->copyArrows());
        $this->assertNotNull($card->arrowSplitProblem());
    }

    public function test_the_editor_saves_a_split(): void
    {
        $card = $this->mixedCard(3, null);

        $this->put("/cards/{$card->id}", $this->payload(['qty' => 4, 'bottom_copies' => 3, 'arrow' => 'bottom']))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $card->refresh();
        $this->assertSame(3, $card->bottom_copies);
        // A mixed card keeps top as its one arrow, for turning the split off.
        $this->assertSame('top', $card->arrow);
        $this->assertSame(['top' => 1, 'bottom' => 3], $card->arrowCounts());
    }

    public function test_the_editor_turns_a_split_off(): void
    {
        $card = $this->mixedCard(3, 1);

        $this->put("/cards/{$card->id}", $this->payload(['bottom_copies' => null, 'arrow' => 'bottom']))
            ->assertSessionHasNoErrors();

        $card->refresh();
        $this->assertNull($card->bottom_copies);
        $this->assertSame('bottom', $card->arrow);
    }

    public function test_the_editor_refuses_a_split_that_does_not_mix(): void
    {
        $card = $this->mixedCard(3, null);

        $this->put("/cards/{$card->id}", $this->payload(['bottom_copies' => 3]))->assertSessionHasErrors('bottom_copies');
        $this->put("/cards/{$card->id}", $this->payload(['bottom_copies' => 0]))->assertSessionHasErrors('bottom_copies');
    }

    public function test_the_print_sheet_prints_each_copy_with_its_own_arrow(): void
    {
        $card = $this->mixedCard(3, 1);

        $html = $this->get("/print/kraken/sheet?deck=entity&only=entity:{$card->id}")->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'arrow-edge arrow-top'));
        $this->assertSame(1, substr_count($html, 'arrow-edge arrow-bottom'));
    }

    public function test_the_deck_counts_arrows_per_copy(): void
    {
        $scenario = Scenario::where('slug', 'kraken')->firstOrFail();
        $before = DeckAssembly::for($scenario, [])->stats()['arrows'];

        $this->mixedCard(3, 1);

        $after = DeckAssembly::for($scenario->fresh(), [])->stats()['arrows'];
        $this->assertSame($before['top'] + 2, $after['top']);
        $this->assertSame($before['bottom'] + 1, $after['bottom']);
    }

    public function test_export_writes_the_split_only_on_a_mixed_card_and_import_reads_it_back(): void
    {
        $this->mixedCard(3, 1);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $kraken = json_decode(file_get_contents("{$this->path}/data/kraken.json"), true, 512, JSON_THROW_ON_ERROR);
        foreach ($kraken['entityDeck'] as $entry) {
            if ($entry['name'] === 'Twin Current') {
                $this->assertSame(['top' => 2, 'bottom' => 1], $entry['arrow']);
            } else {
                $this->assertIsString($entry['arrow'], "{$entry['name']} is not mixed");
            }
        }

        EntityCard::where('name', 'Twin Current')->update(['bottom_copies' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $card = EntityCard::where('name', 'Twin Current')->firstOrFail();
        $this->assertSame(1, $card->bottom_copies);
        $this->assertSame('top', $card->arrow);
    }

    public function test_import_names_a_split_that_does_not_add_up(): void
    {
        $this->mixedCard(3, 1);
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $file = "{$this->path}/data/kraken.json";
        $kraken = json_decode(file_get_contents($file), true);
        foreach ($kraken['entityDeck'] as &$entry) {
            if ($entry['name'] === 'Twin Current') {
                $entry['arrow'] = ['top' => 2, 'bottom' => 2];
            }
        }
        unset($entry);
        file_put_contents($file, json_encode($kraken, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain('Twin Current: arrows 2 top and 2 bottom do not add up to 3 copies')
            ->assertSuccessful();

        $this->assertSame(2, EntityCard::where('name', 'Twin Current')->value('bottom_copies'));
    }

    public function test_the_playtest_table_is_sent_each_copys_arrow(): void
    {
        $card = $this->mixedCard(3, 1);

        $this->get('/scenarios/kraken/play')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('startingCards', fn ($cards) => collect($cards)->firstWhere('id', $card->id)['copy_arrows'] === ['top', 'top', 'bottom']));
    }

    public function test_the_storyline_preview_names_a_copy_by_its_arrow(): void
    {
        $card = $this->mixedCard(3, 1);

        $this->get("/scenarios/kraken/storyline?cards[]={$card->id}:bottom&cards[]={$card->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('row.0.arrow', 'bottom')
                ->where('row.0.card_key', "{$card->id}:bottom")
                ->where('row.1.arrow', 'top')
                // The next card is told what the copy before it points at.
                ->where('row.1.deciding_arrow', 'bottom'));
    }
}
