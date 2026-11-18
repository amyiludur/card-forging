<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Support\CardArt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Art and flavour on the printed card. The sheet is rendered from file:// and
 * fetches nothing, so a picture is inlined once however many cards use it;
 * flavour comes after the rules text on every card kind that has any.
 */
class CardArtPrintTest extends TestCase
{
    use RefreshDatabase;

    private string $design;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');

        // After the import: the cards come from the real folder, the pictures
        // go into a temporary one.
        $this->design = sys_get_temp_dir().'/cardforge-print-'.uniqid();
        File::ensureDirectoryExists($this->design);
        config(['cardforge.design_path' => $this->design]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->design);

        parent::tearDown();
    }

    private function picture(string $name = 'Tide'): string
    {
        return CardArt::store(UploadedFile::fake()->image('x.png', 30, 40), 'board', $name);
    }

    /** @return \Illuminate\Support\Collection<int, BoardCard> the Kraken's board cards */
    private function board()
    {
        return BoardCard::where('scenario_id', Scenario::where('slug', 'kraken')->value('id'))->orderBy('sort')->get();
    }

    private function boardSheet(): string
    {
        return $this->get('/print/kraken/sheet?deck=board')->assertOk()->getContent();
    }

    public function test_a_picture_prints_as_one_data_uri_however_many_copies(): void
    {
        $art = $this->picture();
        [$first, $second] = $this->board()->take(2)->all();

        $first->update(['art' => $art, 'qty' => 3]);
        $second->update(['art' => $art, 'qty' => 1]);

        $html = $this->boardSheet();

        $this->assertSame(1, substr_count($html, 'data:image/png;base64,'));
        $this->assertSame(4, substr_count($html, 'card-art '.CardArt::cssClass($art)));
    }

    public function test_the_sheet_still_loads_nothing_from_outside(): void
    {
        $this->board()->first()->update(['art' => $this->picture()]);

        $html = $this->boardSheet();

        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringNotContainsString('url("/art/', $html);
        $this->assertStringNotContainsString('url(/art/', $html);
    }

    public function test_every_card_without_art_draws_a_blank_forty_percent_window(): void
    {
        $board = $this->boardSheet();

        $this->assertStringContainsString('class="card-art" style="height: 40%;', $board);
        $this->assertStringNotContainsString('data:image', $board);

        $entity = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('class="entity-art', $entity);
        $this->assertStringContainsString('flex-basis: 40%', $entity);
    }

    public function test_a_size_of_zero_hides_a_portrait_cards_window(): void
    {
        BoardCard::query()->update(['art_size' => 0]);

        $this->assertStringNotContainsString('class="card-art', $this->boardSheet());
    }

    public function test_a_size_of_zero_hides_even_an_entity_cards_window(): void
    {
        \App\Models\EntityCard::query()->update(['art_size' => 0]);

        $entity = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="entity-art', $entity);
    }

    public function test_a_missing_picture_prints_a_blank_window_and_is_named(): void
    {
        $card = $this->board()->first();
        $card->update(['art' => $this->picture()]);
        File::delete($this->design.'/'.$card->art);

        $html = $this->boardSheet();

        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringContainsString('class="card-art', $html);
        $this->assertMatchesRegularExpression('/art missing for:[^<]*'.preg_quote(e($card->name), '/').'/', $html);
    }

    public function test_flavour_prints_after_the_rules_text(): void
    {
        $card = $this->board()->first();
        $card->update(['flavour' => 'Salt and silence.']);

        $html = $this->boardSheet();
        $face = substr($html, strpos($html, e($card->name)));

        $this->assertStringContainsString('class="flavour"', $face);
        $this->assertGreaterThan(strpos($face, 'class="effect"'), strpos($face, 'class="flavour"'));
    }

    public function test_beat_flavour_moved_below_the_rules_text(): void
    {
        $beat = StoryBeat::whereNotNull('flavour')->firstOrFail();

        $html = $this->get("/print/{$beat->scenario->slug}/sheet?deck=beats")->assertOk()->getContent();
        $face = substr($html, strpos($html, e($beat->name)));

        $this->assertGreaterThan(strpos($face, 'beat-block'), strpos($face, 'class="flavour"'));
        $this->assertStringNotContainsString('beat-flavour', $html);
    }

    public function test_tale_card_flavour_prints_below_its_text(): void
    {
        $tale = Tale::firstOrFail();
        TaleCard::create([
            'tale_id' => $tale->id, 'slug' => 'omen', 'name' => 'The Omen', 'qty' => 1,
            'text' => 'Draw a card.', 'flavour' => 'It came at dusk.',
        ]);

        $html = $this->get("/print/tale/{$tale->slug}/sheet?deck=tale")->assertOk()->getContent();
        $face = substr($html, strpos($html, 'The Omen'));

        $this->assertGreaterThan(strpos($face, 'class="effect"'), strpos($face, 'class="flavour"'));
    }

    public function test_the_pocket_page_draws_no_art(): void
    {
        $this->board()->first()->update(['art' => $this->picture()]);

        $html = $this->get('/pocket')->assertOk()->getContent();

        $this->assertFalse(str_contains($html, 'class="card-art'), 'The pocket page drew an art window.');
        $this->assertFalse(str_contains($html, 'data:image'), 'The pocket page inlined a picture.');
        $this->assertFalse(str_contains($html, 'class="entity-art'), 'The pocket page drew an entity art window.');
    }
}
