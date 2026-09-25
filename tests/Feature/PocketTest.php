<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The pocket page: every rule and every card in one file, read on a phone with
 * nothing running. What matters about it is that it is whole — it reaches for
 * nothing — and that it holds the same rules and cards the print pages do.
 */
class PocketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_it_carries_every_rules_document_and_every_card(): void
    {
        $html = $this->get('/pocket')->assertOk()->getContent();

        foreach (RuleDocument::all() as $document) {
            $this->assertStringContainsString('id="'.$document->slug.'-doc"', $html);
        }

        foreach (Scenario::all() as $scenario) {
            $this->assertStringContainsString('id="scenario-'.$scenario->slug.'"', $html);
        }

        foreach (Character::all() as $character) {
            $this->assertStringContainsString('id="character-'.$character->slug.'"', $html);
        }

        // Every printable card in the design folder has a slot of its own: the
        // page is the whole card list, not a selection of it. Copies a deck
        // calls for are said on the slot rather than drawn twice.
        $printable = EntityCard::count()
            + BoardCard::count()
            + StoryBeat::count()
            + TownAction::count()
            + PlayerCard::count()
            + Character::count()
            + Scenario::all()->filter(fn (Scenario $scenario) => $scenario->hasSetup())->count();

        $this->assertSame($printable, substr_count($html, 'class="slot"'));
    }

    public function test_the_page_reaches_for_nothing(): void
    {
        $html = $this->get('/pocket')->assertOk()->getContent();

        // It is read with no server to fetch from and possibly no network at
        // all, so every last thing it draws has to already be in the file.
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringNotContainsString('<script src', $html);
        $this->assertStringContainsString('<svg class="icon"', $html);

        // The only web address in it is the SVG namespace, which is a name and
        // not somewhere the page goes.
        preg_match_all('#https?://[^"\'\s]+#', $html, $matches);
        $this->assertSame(['http://www.w3.org/2000/svg'], array_values(array_unique($matches[0])));
    }

    public function test_a_card_is_found_by_the_words_on_its_face(): void
    {
        $html = $this->get('/pocket')->assertOk()->getContent();

        $card = EntityCard::with('faces')->get()
            ->first(fn (EntityCard $card) => trim((string) $card->faces->first()?->text) !== '');

        $this->assertNotNull($card, 'the design folder has no entity card with any text on it');

        // The search text a slot carries is lowercased and stripped of markup,
        // so a word the card shows finds it.
        $word = collect(preg_split('/\W+/', strtolower((string) $card->faces->first()->text)))
            ->first(fn (string $part) => strlen($part) > 4);

        $this->assertNotNull($word);
        $this->assertMatchesRegularExpression('/data-find="[^"]*'.preg_quote($word, '/').'/', $html);
        $this->assertStringContainsString('data-find="'.strtolower($card->name), $html);
    }

    public function test_a_placeholder_is_flagged_rather_than_filled_in(): void
    {
        EntityCard::query()->first()->update(['is_placeholder' => true]);

        $this->get('/pocket')
            ->assertOk()
            ->assertSee('PLACEHOLDER')
            // The two appendices flag their own, and say what they are.
            ->assertSee('Tunable numbers')
            ->assertSee('Keywords');
    }

    public function test_the_command_writes_the_same_page_the_route_serves(): void
    {
        $path = storage_path('app/pocket/test-build.html');
        File::delete($path);

        $this->artisan('pocket:build', ['--out' => $path])
            ->expectsOutputToContain('card')
            ->assertSuccessful();

        $this->assertFileExists($path);

        // A time stamp apart, the file and the route are the same page: the
        // published copy cannot quietly differ from what the editor shows.
        $stamp = '/\d{1,2} \w{3} \d{4}, \d{2}:\d{2} UTC/';
        $this->assertSame(
            preg_replace($stamp, 'then', $this->get('/pocket')->getContent()),
            preg_replace($stamp, 'then', File::get($path)),
        );

        File::delete($path);
    }
}
