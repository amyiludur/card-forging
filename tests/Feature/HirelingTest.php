<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Support\DomainPool;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The Hireling, design v3.1: a fourth player card type that stays in play with
 * a number of uses and a sacrifice value.
 *
 * The rules behind those two numbers are the designer's placeholders, so the
 * tool holds them, prints them and reports what does not line up. It settles
 * nothing: a Hireling with no uses stays a Hireling with no uses.
 */
class HirelingTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/hirelings-'.uniqid());
        $this->importDesignWithoutDomains();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    /** A pool with one Hireling in it and one card that is not. */
    private function crew(): Domain
    {
        $domain = Domain::create([
            'slug' => 'crew', 'name' => 'Crew', 'set_icon' => 'CR', 'sort' => 0,
        ]);

        $domain->cards()->create([
            'slug' => 'harbour-watch', 'name' => 'Harbour Watch', 'qty' => 2,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'hireling',
            'gold_cost' => 2, 'omen_icons' => 0, 'start_zone' => 'deck',
            'uses' => 3, 'sacrifice_value' => 2,
            'text' => 'Exhaust: prevent up to 2 damage to a player.',
            'traits' => ['Human'], 'sort' => 0,
        ]);

        $domain->cards()->create([
            'slug' => 'rallying-cry', 'name' => 'Rallying Cry', 'qty' => 1,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'deck', 'sort' => 1,
        ]);

        return $domain;
    }

    private function read(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_hireling_is_a_player_card_type_with_an_icon_and_a_printed_name(): void
    {
        $this->assertContains('hireling', PlayerCard::TYPES);
        $this->assertTrue(Icons::has('hireling'));
        // The two numbers get their own marks on the card face.
        $this->assertTrue(Icons::has('uses'));
        $this->assertTrue(Icons::has('sacrifice'));
    }

    public function test_the_design_folder_carries_the_in_play_limit(): void
    {
        $config = RulesConfig::where('key', 'maxHirelingsInPlay')->firstOrFail();

        $this->assertSame(3, $config->raw_value);
        // The Hireling rules are placeholders in the brief, this number among them.
        $this->assertTrue($config->is_placeholder);
        $this->assertSame('players', $config->group);
    }

    public function test_a_hireling_writes_its_two_numbers_and_reads_them_back(): void
    {
        $this->crew();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $card = $this->read('players/domains/crew.json')['cards'][0];

        $this->assertSame('harbour-watch', $card['id']);
        $this->assertSame(3, $card['uses']);
        $this->assertSame(2, $card['sacrificeValue']);

        Domain::query()->get()->each->delete();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $back = PlayerCard::where('slug', 'harbour-watch')->firstOrFail();

        $this->assertTrue($back->isHireling());
        $this->assertSame(3, $back->uses);
        $this->assertSame(2, $back->sacrifice_value);
    }

    public function test_a_card_that_is_not_a_hireling_grows_no_hireling_keys(): void
    {
        $this->crew();

        $this->artisan('design:export', ['--path' => $this->path]);

        $action = $this->read('players/domains/crew.json')['cards'][1];

        $this->assertSame('rallying-cry', $action['id']);
        $this->assertArrayNotHasKey('uses', $action);
        $this->assertArrayNotHasKey('sacrificeValue', $action);
    }

    public function test_the_character_files_still_export_byte_for_byte(): void
    {
        // Two new fields on player_cards must not move a character file, which
        // is the designer's own and is diffed against what they handed over.
        $this->artisan('design:export', ['--path' => $this->path]);

        foreach ($this->characterSlugs() as $slug) {
            $this->assertSame(
                rtrim(file_get_contents(base_path("design/players/{$slug}.json"))),
                rtrim(file_get_contents("{$this->path}/players/{$slug}.json")),
                "design/players/{$slug}.json did not survive the round trip",
            );
        }
    }

    public function test_the_editor_saves_a_hireling_with_its_numbers(): void
    {
        $domain = $this->crew();

        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Powder Monkey',
            'qty' => 1,
            'role' => PlayerCard::ROLE_DOMAIN,
            'origin' => 'domain',
            'type' => 'hireling',
            'gold_cost' => 1,
            'omen_icons' => 0,
            'uses' => 2,
            'sacrifice_value' => 1,
            'start_zone' => 'deck',
            'text' => 'Exhaust: deal 1 {damage}.',
        ])->assertRedirect("/domains/{$domain->slug}");

        $card = PlayerCard::where('slug', 'powder-monkey')->firstOrFail();

        $this->assertSame(2, $card->uses);
        $this->assertSame(1, $card->sacrifice_value);
    }

    public function test_a_hireling_may_be_saved_half_written(): void
    {
        $domain = $this->crew();

        // The numbers are placeholders the designer has not set yet, so the
        // editor takes the card and the page says what is missing.
        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Deckhand', 'qty' => 1, 'role' => PlayerCard::ROLE_DOMAIN,
            'origin' => 'domain', 'type' => 'hireling', 'gold_cost' => 1,
            'omen_icons' => 0, 'uses' => null, 'sacrifice_value' => null, 'start_zone' => 'deck',
        ])->assertRedirect("/domains/{$domain->slug}");

        $this->assertNull(PlayerCard::where('slug', 'deckhand')->firstOrFail()->uses);
    }

    public function test_the_editor_offers_the_type_and_rejects_a_nonsense_number(): void
    {
        $domain = $this->crew();

        $this->get("/domains/{$domain->slug}/cards/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('PlayerCards/Form')
                ->where('options.types', PlayerCard::TYPES)
                ->where('options.hirelingType', 'hireling')
            );

        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Bad Hire', 'qty' => 1, 'role' => PlayerCard::ROLE_DOMAIN,
            'origin' => 'domain', 'type' => 'hireling', 'gold_cost' => 0,
            'omen_icons' => 0, 'uses' => -1, 'start_zone' => 'deck',
        ])->assertSessionHasErrors('uses');
    }

    public function test_a_pool_reports_its_hirelings_against_the_in_play_limit(): void
    {
        $hirelings = DomainPool::for($this->crew())->stats()['hirelings'];

        // Counted by copy, like everything else: two Harbour Watches.
        $this->assertSame(2, $hirelings['total']);
        $this->assertSame(3, $hirelings['max_in_play']);
        $this->assertSame([['value' => 3, 'count' => 2]], $hirelings['uses']);
        $this->assertSame([['value' => 2, 'count' => 2]], $hirelings['sacrifice']);
    }

    public function test_a_pool_holding_more_hirelings_than_can_be_in_play_is_not_a_warning(): void
    {
        $domain = $this->crew();

        // The limit is on the table, not on the pool. Eight Hirelings in a pool
        // is a choice, the way a pool bigger than the slot count is.
        $domain->cards()->where('slug', 'harbour-watch')->update(['qty' => 8]);

        $warnings = DomainPool::for($domain->fresh())->warnings();

        $this->assertSame([], array_values(array_filter(
            $warnings,
            fn (string $w) => str_contains($w, 'in play'),
        )));
    }

    public function test_it_reports_a_hireling_with_no_term_and_a_stray_number_elsewhere(): void
    {
        $domain = $this->crew();

        $domain->cards()->where('slug', 'harbour-watch')
            ->update(['uses' => null, 'sacrifice_value' => null]);
        // A design file can say anything; this is read as written and reported.
        $domain->cards()->where('slug', 'rallying-cry')->update(['uses' => 2]);

        $warnings = DomainPool::for($domain->fresh())->warnings();

        $this->assertContains(
            'Harbour Watch is a Hireling with no uses, so nothing says how long its term runs.',
            $warnings,
        );
        $this->assertContains(
            'Harbour Watch is a Hireling with no sacrifice value, so nothing says what sending it away prevents.',
            $warnings,
        );
        $this->assertContains(
            'Rallying Cry carries uses, which only a Hireling has. It is typed action.',
            $warnings,
        );

        // Reported, never corrected: the numbers are still exactly as written.
        $this->assertSame(2, PlayerCard::where('slug', 'rallying-cry')->firstOrFail()->uses);
    }

    public function test_a_hireling_prints_its_term_and_what_sacrificing_it_prevents(): void
    {
        $this->crew();

        $html = $this->get('/print/domain/crew/sheet?deck=player')->assertOk()->getContent();

        $this->assertStringContainsString('<div class="uses">3'.Icons::svg('uses', 'icon pip-mark').'</div>', $html);
        $this->assertStringContainsString('<span class="sacrifice">'.Icons::svg('sacrifice').' 2</span>', $html);
        $this->assertStringContainsString('Hireling', $html);

        // And a card that is not one carries neither mark.
        $this->assertSame(2, substr_count($html, 'class="uses"'));
        $this->assertSame(2, substr_count($html, 'class="sacrifice"'));
    }

    public function test_a_hireling_missing_its_numbers_prints_a_question_mark(): void
    {
        $domain = $this->crew();
        $domain->cards()->where('slug', 'harbour-watch')
            ->update(['uses' => null, 'sacrifice_value' => null]);

        $html = $this->get('/print/domain/crew/sheet?deck=player')->assertOk()->getContent();

        // Better a card that says it is unfinished than one that says zero.
        $this->assertStringContainsString('<div class="uses">?'.Icons::svg('uses', 'icon pip-mark').'</div>', $html);
    }
}
