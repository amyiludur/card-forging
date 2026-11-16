<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\Tale;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Every piece of Tale text is card markup, wherever it shows: icons,
 * {perPlayer}, keywords and tunable numbers. A step is the one Tale text with
 * a scenario behind it, so it quotes that scenario's Dread rule and numbers;
 * the overview and the modes have none, and print `?dreadRule`.
 */
class TaleTokensTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);
    }

    /** The path an icon draws, which is what proves the icon is on the page. */
    private function iconPath(string $name): string
    {
        preg_match('/ d="([^"]+)"/', Icons::svg($name), $match);

        return $match[1];
    }

    private function kraken(): Scenario
    {
        return Scenario::where('slug', 'kraken')->firstOrFail();
    }

    private function book(string $part = 'story'): string
    {
        return $this->get("/print/tale/drowned-coast/book?part={$part}")->assertOk()->getContent();
    }

    public function test_the_book_draws_per_player_and_icons_in_every_part(): void
    {
        $this->tale->update(['overview' => 'Start with 2{perPlayer} ships.']);
        $this->tale->steps()->create(['scenario_id' => $this->kraken()->id, 'intro' => 'Gain 1 {omen}.']);
        $this->tale->modes()->create(['name' => 'Story', 'text' => 'Heal 1{perPlayer}.']);

        $html = $this->book();

        $this->assertTrue(str_contains($html, $this->iconPath('perPlayer')), 'The {perPlayer} icon is not drawn.');
        $this->assertTrue(str_contains($html, $this->iconPath('omen')), 'The omen icon is not drawn.');
        $this->assertFalse(str_contains($html, '{perPlayer}'), 'A {perPlayer} token is printed as typed.');
        $this->assertFalse(str_contains($html, '{omen}'), 'An {omen} token is printed as typed.');
    }

    public function test_a_step_quotes_its_own_scenarios_dread(): void
    {
        $this->tale->update(['overview' => 'Beware: {dreadRule}']);
        $this->tale->steps()->create([
            'scenario_id' => $this->kraken()->id,
            'intro' => 'When Dread strikes: {dreadRule}',
            'notes' => 'Dread starts at {dreadAmount}.',
        ]);

        $html = $this->book();
        $rule = e($this->kraken()->dread_effect);

        $this->assertTrue(str_contains($html, 'When Dread strikes: '.$rule), "The step does not quote the Kraken's Dread rule.");
        $this->assertFalse(str_contains($html, '?dreadAmount'), 'The step does not quote the Kraken\'s starting Dread.');
        $this->assertTrue(str_contains($html, 'Beware: <span class="markup-missing'), 'The overview has no scenario, so its {dreadRule} should be reported.');
    }

    public function test_log_entry_names_render_their_tokens_on_the_sheet(): void
    {
        $this->tale->logEntries()->create(['name' => 'Damage kept {damage}', 'kind' => 'number', 'scope' => 'party']);

        $html = $this->book('log');

        $this->assertTrue(str_contains($html, $this->iconPath('damage')), 'The entry name does not draw its icon.');
        $this->assertFalse(str_contains($html, '{damage}'), 'The entry name prints its token as typed.');
    }

    public function test_the_pocket_page_renders_tale_tokens(): void
    {
        $this->tale->modes()->create(['name' => 'Story', 'text' => 'Heal 1{perPlayer}.']);
        $this->tale->logEntries()->create(['name' => 'Damage kept {damage}', 'kind' => 'number', 'scope' => 'party']);

        $html = $this->get('/pocket')->assertOk()->getContent();

        $this->assertFalse(str_contains($html, 'Heal 1{perPlayer}'), 'The pocket page prints a mode\'s token as typed.');
        $this->assertFalse(str_contains($html, 'Damage kept {damage}'), 'The pocket page prints an entry\'s token as typed.');
    }

    public function test_the_tale_page_sends_each_step_its_scenarios_tokens(): void
    {
        $this->tale->steps()->create(['scenario_id' => $this->kraken()->id]);

        $this->get('/tales/drowned-coast')->assertInertia(fn (Assert $page) => $page
            ->where('steps.0.dread_rule', $this->kraken()->dread_effect)
            ->where('steps.0.dread_amount', $this->kraken()->startingDread()->markup())
            ->has('steps.0.omen_amount')
        );
    }
}
