<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\RulesConfig;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A scenario's own starting omen: the omen in the pool at setup.
 *
 * Optional. A scenario that sets none takes the shared `startingOmen` tunable
 * number, which is what every scenario used before it could set its own.
 * {omenAmount} quotes whichever applies, the way {dreadAmount} quotes the Dread.
 */
class StartingOmenTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');

        // One kind of separator: PHP's recursive mkdir() on Windows trips over
        // storage_path()'s mix of \ and /, which is not what this is testing.
        $this->path = str_replace('\\', '/', storage_path('framework/testing/starting-omen-'.uniqid()));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function kraken(): Scenario
    {
        return Scenario::where('slug', 'kraken')->firstOrFail();
    }

    private function shared(int|string $value): void
    {
        RulesConfig::where('key', 'startingOmen')->firstOrFail()->update(['value' => ['v' => $value]]);
    }

    private function onShared(): void
    {
        $this->kraken()->update(['starting_omen' => null, 'starting_omen_equation' => null]);
    }

    /** The fields the scenario form always sends, plus whatever a test adds. */
    private function save(array $fields): TestResponse
    {
        return $this->put('/scenarios/kraken', [
            'slug' => 'kraken',
            'name' => $this->kraken()->name,
            'entity_type' => 'creature',
            'starting_dread' => 2,
            'printed_arrows' => true,
            ...$fields,
        ]);
    }

    private function exported(string $target): array
    {
        $this->artisan("design:export --path={$target}")->assertExitCode(0);

        return json_decode(file_get_contents($target.'/data/kraken.json'), true);
    }

    public function test_a_scenario_file_can_carry_its_own_starting_omen(): void
    {
        $kraken = $this->kraken();

        $this->assertTrue($kraken->hasOwnStartingOmen());
        $this->assertSame('2 + 2perPlayer', $kraken->starting_omen_equation);
        $this->assertSame('2 + 2{perPlayer}', $kraken->startingOmen()->markup());
    }

    public function test_the_form_saves_a_number_and_an_equation(): void
    {
        $this->save(['starting_omen' => 5, 'starting_omen_equation' => null])->assertRedirect();

        $this->assertSame(5, $this->kraken()->starting_omen);
        $this->assertNull($this->kraken()->starting_omen_equation);

        $this->save(['starting_omen' => 5, 'starting_omen_equation' => '1 + 1perPlayer'])->assertRedirect();

        $this->assertSame('1 + 1perPlayer', $this->kraken()->starting_omen_equation);
        // The number is kept beside it, for the toggle to put back.
        $this->assertSame(5, $this->kraken()->starting_omen);
    }

    public function test_leaving_it_empty_falls_back_to_the_shared_number(): void
    {
        $this->shared(3);

        $this->save(['starting_omen' => '', 'starting_omen_equation' => ''])->assertRedirect();

        $kraken = $this->kraken();
        $this->assertNull($kraken->starting_omen);
        $this->assertNull($kraken->starting_omen_equation);
        $this->assertFalse($kraken->hasOwnStartingOmen());
        $this->assertSame('3', $kraken->startingOmen()->markup());
    }

    public function test_an_equation_it_cannot_read_is_refused(): void
    {
        $this->save(['starting_omen_equation' => '6 / perPlayer'])
            ->assertSessionHasErrors('starting_omen_equation');
    }

    public function test_the_form_is_given_the_scenarios_own_and_the_shared_number(): void
    {
        $this->shared(3);

        $this->get('/scenarios/kraken/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scenario.starting_omen_equation', '2 + 2perPlayer')
                ->where('sharedStartingOmen', '3')
            );

        $this->get('/scenarios/create')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('sharedStartingOmen', '3'));
    }

    public function test_a_card_quotes_the_scenarios_own_starting_omen(): void
    {
        $this->kraken()->update(['starting_omen' => 6, 'starting_omen_equation' => null]);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'Set the pool to {omenAmount}.']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Set the pool to 6.', $html);
        $this->assertStringNotContainsString('{omenAmount}', $html);
    }

    public function test_a_card_quotes_the_shared_number_when_its_scenario_sets_none(): void
    {
        $this->shared(4);
        $this->onShared();

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'Set the pool to {omenAmount}.']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Set the pool to 4.', $html);
    }

    public function test_a_module_card_has_no_one_scenario_so_it_reports_the_token(): void
    {
        $module = Module::where('slug', 'what-lurks-below')->firstOrFail();
        $module->entityCards()->firstOrFail()->faces()->first()->update(['text' => '{omenAmount}']);

        $html = $this->get("/print/module/{$module->slug}/sheet?deck=entity")->assertOk()->getContent();

        $this->assertStringContainsString('?omenAmount', $html);
    }

    public function test_the_setup_card_prints_it_in_the_corner_as_an_equation(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=setup')->assertOk()->getContent();

        // The equation, with the count drawn as the icon rather than worked out.
        $this->assertStringContainsString('<div class="health scaled">2 + 2<span class="markup-icon markup-icon-perPlayer"', $html);
    }

    public function test_the_browser_gets_the_same_number_the_print_sheet_used(): void
    {
        $this->kraken()->update(['starting_omen' => 6, 'starting_omen_equation' => null]);

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cards.0.omen_amount', '6')
                ->where('beats.0.omen_amount', '6')
                ->where('setupCard.starting_omen', 6)
                ->where('scenario.omen_amount', '6')
                ->where('scenario.starting_omen_shared', false)
            );
    }

    public function test_the_overview_says_when_the_number_is_the_shared_one(): void
    {
        $this->shared(3);
        $this->onShared();

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scenario.starting_omen', 3)
                ->where('scenario.starting_omen_shared', true)
            );
    }

    public function test_the_playtest_table_seeds_the_pool_from_the_scenario(): void
    {
        $this->get('/scenarios/kraken/play')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scenario.starting_omen_equation', '2 + 2perPlayer')
                ->missing('config.startingOmen')
            );

        $this->shared(3);
        $this->onShared();

        $this->get('/scenarios/kraken/play')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('scenario.starting_omen', 3)
                ->where('scenario.starting_omen_equation', null)
            );
    }

    public function test_the_design_folder_writes_it_only_when_the_scenario_sets_one(): void
    {
        $target = $this->path;

        $this->assertSame('2 + 2perPlayer', $this->exported($target)['startingOmen']);

        $this->kraken()->update(['starting_omen' => 5, 'starting_omen_equation' => null]);
        $this->assertSame(5, $this->exported($target)['startingOmen']);

        $this->onShared();
        $this->assertArrayNotHasKey('startingOmen', $this->exported($target));
    }

    public function test_importing_a_file_without_the_key_clears_it(): void
    {
        // The folder is the source of truth: a scenario whose file has dropped
        // its starting omen goes back to the shared number.
        $target = $this->path;
        $this->onShared();
        $this->exported($target);

        $this->kraken()->update(['starting_omen' => 5]);
        $this->artisan("design:import --path={$target}")->assertExitCode(0);

        $this->assertFalse($this->kraken()->hasOwnStartingOmen());
    }
}
