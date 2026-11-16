<?php

namespace Tests\Feature;

use App\Models\Scenario;
use App\Models\Tale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Tale editor: a list, a form, and a page whose Story tab reads the Tale
 * top to bottom the way the box would be read.
 */
class TaleEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function create(array $data = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/tales', $data + ['name' => 'The Drowned Coast', 'set_icon' => 'DC']);
    }

    public function test_a_tale_is_created_as_a_placeholder(): void
    {
        $this->create()->assertRedirect('/tales/the-drowned-coast');

        $tale = Tale::where('slug', 'the-drowned-coast')->firstOrFail();

        $this->assertSame('The Drowned Coast', $tale->name);
        $this->assertSame('DC', $tale->set_icon);
        $this->assertTrue($tale->is_placeholder);
    }

    public function test_a_second_tale_with_the_same_name_gets_a_numbered_slug(): void
    {
        $this->create();
        $this->create()->assertRedirect('/tales/the-drowned-coast-2');

        $this->assertSame(2, Tale::count());
    }

    public function test_a_tale_is_edited_and_its_story_shown(): void
    {
        $this->create();
        $tale = Tale::first();
        $tale->steps()->create([
            'scenario_id' => Scenario::where('slug', 'kraken')->value('id'),
            'intro' => 'The fleet sails at dawn.',
            'outro' => 'The sea goes quiet.',
        ]);

        $this->put('/tales/the-drowned-coast', ['name' => 'The Drowned Coast', 'overview' => 'Storms, and worse.'])
            ->assertRedirect('/tales/the-drowned-coast');

        $this->assertSame('Storms, and worse.', $tale->fresh()->overview);

        $this->get('/tales/the-drowned-coast')->assertInertia(fn (Assert $page) => $page
            ->component('Tales/Show')
            ->where('tale.overview', 'Storms, and worse.')
            ->has('steps', 1)
            ->where('steps.0.scenario.slug', 'kraken')
            ->where('steps.0.intro', 'The fleet sails at dawn.')
            ->has('scenarios')
            ->has('sharedModules')
            ->where('logKinds', ['number', 'check', 'note', 'rewards'])
            ->where('logScopes', ['party', 'character'])
        );
    }

    public function test_the_list_and_the_sidebar_show_every_tale(): void
    {
        $this->create();
        $this->create(['name' => 'The Long Winter']);

        $this->get('/tales')->assertInertia(fn (Assert $page) => $page
            ->component('Tales/Index')
            ->has('tales', 2)
            ->has('nav.tales', 2)
        );
    }

    public function test_deleting_a_tale_returns_to_the_list(): void
    {
        $this->create();

        $this->delete('/tales/the-drowned-coast')->assertRedirect('/tales');

        $this->assertSame(0, Tale::count());
        $this->assertTrue(Scenario::where('slug', 'kraken')->exists());
    }

    public function test_a_tale_needs_a_name(): void
    {
        $this->post('/tales', ['name' => ''])->assertSessionHasErrors('name');
    }
}
