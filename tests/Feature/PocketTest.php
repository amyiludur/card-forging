<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\RuleDocument;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\ScenarioTownPick;
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

    public function test_a_tale_has_a_pocket_section_with_its_story_modes_log_and_cards(): void
    {
        $tale = \App\Models\Tale::create([
            'slug' => 'drowned-coast', 'name' => 'The Drowned Coast', 'set_icon' => 'DC',
            'overview' => 'Storms have kept the fleet in harbour.',
        ]);
        $tale->steps()->create(['scenario_id' => Scenario::where('slug', 'kraken')->value('id'), 'intro' => 'The fleet sails.']);
        $tale->modes()->create(['name' => 'Hard', 'text' => 'Damage carries over.']);
        $tale->logEntries()->create(['name' => 'Ship damage', 'kind' => 'number', 'scope' => 'party']);
        $tale->cards()->create(['slug' => 'the-lighthouse', 'name' => 'The Lighthouse']);
        PlayerCard::create([
            'tale_id' => $tale->id, 'slug' => 'sea-glass-charm', 'name' => 'Sea-glass Charm',
            'role' => 'reward', 'origin' => 'reward', 'type' => 'item',
        ]);
        $module = \App\Models\Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $tale->id]);
        EntityCard::create(['module_id' => $module->id, 'name' => 'Bone Surf', 'qty' => 1, 'layout' => 'single', 'omen_cost' => 1, 'arrow' => 'top']);

        $html = $this->get('/pocket')->assertOk()->getContent();

        // str_contains rather than assertStringContainsString: the page is every
        // card there is, and a failure diff of it takes minutes to print.
        foreach (['id="tale-drowned-coast"', 'Storms have kept the fleet', 'The fleet sails.', 'Damage carries over.', 'Ship damage', 'The Lighthouse', 'Sea-glass Charm', 'Bone Surf'] as $text) {
            $this->assertTrue(str_contains($html, $text), "The pocket page does not say \"{$text}\".");
        }
        $this->assertFalse(str_contains($html, 'id="module-tide-of-bones"'), 'A Tale module is listed under its Tale, not as a module of its own.');

        // A placeholder mode is flagged beside its name with the page's own
        // flag. The card's .placeholder-flag is absolutely positioned, so it
        // would land in a corner of the page instead.
        $this->assertTrue(str_contains($html, '<strong>Hard</strong> <span class="flag">placeholder</span>'), 'The placeholder mode is not flagged beside its name.');
    }

    public function test_dealt_board_cards_are_their_own_group(): void
    {
        $card = Scenario::where('slug', 'kraken')->firstOrFail()->boardCards()->whereNull('added_by_beat_id')->orderBy('sort')->firstOrFail();
        $card->update(['start' => BoardCard::START_DEALT]);

        $html = $this->get('/pocket')->assertOk()->getContent();
        $kraken = strpos($html, 'id="scenario-kraken"');
        $heading = strpos($html, 'Board cards — dealt to the players', $kraken);

        $this->assertNotFalse($heading, 'The Kraken has no group of dealt board cards.');
        $this->assertNotFalse(strpos($html, e($card->name), $heading), "{$card->name} is not listed under the dealt group.");
    }

    public function test_each_deal_group_has_its_own_heading(): void
    {
        [$townsfolk, $curse] = Scenario::where('slug', 'kraken')->firstOrFail()->boardCards()->whereNull('added_by_beat_id')->orderBy('sort')->take(2)->get()->all();
        $townsfolk->update(['start' => BoardCard::START_DEALT, 'deal_group' => 'Townsfolk']);
        $curse->update(['start' => BoardCard::START_DEALT, 'deal_group' => 'Curses']);

        $html = $this->get('/pocket')->assertOk()->getContent();
        $kraken = strpos($html, 'id="scenario-kraken"');

        foreach ([['Townsfolk', $townsfolk], ['Curses', $curse]] as [$group, $card]) {
            $heading = strpos($html, "Board cards — {$group}, dealt to the players", $kraken);

            $this->assertNotFalse($heading, "The Kraken has no {$group} heading.");
            $this->assertNotFalse(strpos($html, e($card->name), $heading), "{$card->name} is not listed after the {$group} heading.");
        }
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
            // A common district is listed once on its own and again in each
            // town that picks it, with that town's line.
            + ScenarioTownPick::count()
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
