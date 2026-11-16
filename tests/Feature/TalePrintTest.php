<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\PrintPoolItem;
use App\Models\Scenario;
use App\Models\Tale;
use App\Support\PrintCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Tale prints as a box: its own cards, its rewards and its modules on the
 * card sheet, and its story with the log sheet after it on a page of text.
 */
class TalePrintTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create([
            'slug' => 'drowned-coast', 'name' => 'The Drowned Coast', 'set_icon' => 'DC',
            'overview' => 'Storms have kept the fleet in harbour.',
        ]);
    }

    private function fill(): void
    {
        $this->tale->cards()->create(['slug' => 'the-lighthouse', 'name' => 'The Lighthouse', 'text' => 'Lit.']);
        PlayerCard::create([
            'tale_id' => $this->tale->id, 'slug' => 'sea-glass-charm', 'name' => 'Sea-glass Charm',
            'role' => 'reward', 'origin' => 'reward', 'type' => 'item',
        ]);
        $module = Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $this->tale->id, 'set_icon' => 'TB']);
        EntityCard::create(['module_id' => $module->id, 'name' => 'Bone Surf', 'qty' => 1, 'layout' => 'single', 'omen_cost' => 1, 'arrow' => 'top']);

        foreach (['kraken' => ['The fleet sails.', 'Quiet water.'], 'march-of-dead-legion' => ['The dead march.', 'Silence.']] as $slug => [$intro, $outro]) {
            $this->tale->steps()->create([
                'scenario_id' => Scenario::where('slug', $slug)->value('id'),
                'intro' => $intro, 'outro' => $outro, 'sort' => $this->tale->steps()->count(),
            ]);
        }

        foreach (['Story', 'Hard'] as $i => $mode) {
            $this->tale->modes()->create(['name' => $mode, 'text' => "{$mode} mode text.", 'sort' => $i]);
        }

        foreach ([['Ship damage', 'number', 'party'], ['The lighthouse fell', 'check', 'party'], ['Damage kept', 'number', 'character']] as $i => [$name, $kind, $scope]) {
            $this->tale->logEntries()->create(compact('name', 'kind', 'scope') + ['sort' => $i]);
        }
    }

    public function test_the_tale_sheet_prints_tale_cards_rewards_and_its_modules(): void
    {
        $this->fill();

        $html = $this->get('/print/tale/drowned-coast/sheet?deck=all')->assertOk()->getContent();

        $this->assertStringContainsString('class="card tale-card"', $html);
        $this->assertStringContainsString('The Lighthouse', $html);
        $this->assertStringContainsString('Sea-glass Charm', $html);
        $this->assertStringContainsString('Bone Surf', $html);
        $this->assertStringNotContainsString('Drowned Sailors', $html, 'a shared module is not the Tale\'s to print');

        $this->get('/print/tale/drowned-coast')->assertOk();
    }

    public function test_tale_cards_join_the_print_pool(): void
    {
        $this->fill();
        $id = $this->tale->cards()->value('id');

        $this->assertSame(['tale', $id], PrintCatalogue::parse('tale:'.$id));

        PrintPoolItem::create(['group' => 'tale', 'card_id' => $id, 'qty' => 1]);

        $this->assertStringContainsString('The Lighthouse', $this->get('/print/pool/sheet?deck=all')->getContent());
    }

    public function test_the_log_sheet_is_laid_out_from_the_tales_own_entries(): void
    {
        $this->fill();

        $html = $this->get('/print/tale/drowned-coast/book?part=log')->assertOk()->getContent();

        $this->assertStringContainsString('campaign log', $html);
        foreach (['Ship damage', 'The lighthouse fell', 'The Kraken', 'Story', 'Hard'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertSame(4, substr_count($html, 'Damage kept'), 'one block per character, four blocks');
        $this->assertStringNotContainsString('The fleet sails.', $html, 'the log part prints no story');
    }

    public function test_the_log_sheet_renders_with_nothing_in_it(): void
    {
        $html = $this->get('/print/tale/drowned-coast/book?part=log')->assertOk()->getContent();

        $this->assertStringContainsString('No scenarios in this Tale yet.', $html);
        $this->assertStringContainsString('No log entries yet.', $html);
    }

    public function test_the_tale_book_fetches_nothing(): void
    {
        $this->fill();

        $html = $this->get('/print/tale/drowned-coast/book')->assertOk()->getContent();

        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
    }

    public function test_the_story_prints_in_play_order_before_the_log(): void
    {
        $this->fill();

        $html = $this->get('/print/tale/drowned-coast/book')->assertOk()->getContent();

        $overview = strpos($html, 'Storms have kept the fleet');
        $first = strpos($html, 'The fleet sails.');
        $second = strpos($html, 'The dead march.');
        $mode = strpos($html, 'Hard mode text.');
        // The heading, not the <title>, which names the log too.
        $log = strpos($html, 'campaign log</h1>');

        $this->assertNotFalse($overview);
        $this->assertTrue($overview < $first && $first < $second && $second < $mode && $mode < $log);
    }
}
