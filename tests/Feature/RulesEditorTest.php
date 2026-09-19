<?php

namespace Tests\Feature;

use App\Models\RuleDocument;
use App\Models\RulesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulesEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_it_saves_tunable_numbers_in_their_own_types(): void
    {
        $starting = RulesConfig::where('key', 'startingOmen')->firstOrFail();
        $carries = RulesConfig::where('key', 'goldCarriesOver')->firstOrFail();
        $range = RulesConfig::where('key', 'omenPerCardPlayedRange')->firstOrFail();
        // v3 added the first object-valued number: a deck's two halves.
        $deckSize = RulesConfig::where('key', 'deckSize')->firstOrFail();
        $guideline = RulesConfig::where('key', 'moduleCardCountGuideline')->firstOrFail();

        $this->put('/rules/config', [
            'values' => [
                ['id' => $starting->id, 'value' => '7', 'is_placeholder' => false],
                ['id' => $carries->id, 'value' => true, 'is_placeholder' => true],
                ['id' => $range->id, 'value' => '1, 3', 'is_placeholder' => true],
                ['id' => $deckSize->id, 'value' => ['signature' => '18', 'domain' => '22'], 'is_placeholder' => true],
                ['id' => $guideline->id, 'value' => '', 'is_placeholder' => true],
            ],
        ])->assertRedirect();

        $this->assertSame(7, $starting->refresh()->raw_value);
        $this->assertFalse($starting->is_placeholder);
        $this->assertTrue($carries->refresh()->raw_value);
        $this->assertSame([1, 3], $range->refresh()->raw_value);
        // A map keeps its keys rather than collapsing into a list.
        $this->assertSame(['signature' => 18, 'domain' => 22], $deckSize->refresh()->raw_value);
        $this->assertNull($guideline->refresh()->raw_value);
    }

    public function test_a_number_change_flows_through_to_printed_card_text(): void
    {
        $config = RulesConfig::where('key', 'startingOmen')->firstOrFail();

        \App\Models\EntityCard::where('name', 'Salt Wind')->firstOrFail()
            ->faces()->first()->update(['text' => 'Add {config:startingOmen} {omen}.']);

        $this->assertStringContainsString('>4</span>', $this->get('/print/kraken/sheet?deck=entity')->getContent());

        $this->put('/rules/config', ['values' => [['id' => $config->id, 'value' => '9', 'is_placeholder' => true]]]);

        $this->assertStringContainsString('>9</span>', $this->get('/print/kraken/sheet?deck=entity')->getContent());
    }

    public function test_editing_a_document_keeps_the_previous_version(): void
    {
        $document = RuleDocument::where('slug', '02-turn-structure')->firstOrFail();
        $original = $document->body;

        $this->put("/rules/{$document->slug}", ['title' => 'Turn Structure', 'body' => '# Turn Structure

Rewritten.'])
            ->assertRedirect();

        $document->refresh();

        $this->assertStringContainsString('Rewritten.', $document->body);
        $this->assertSame($original, $document->versions()->first()->body);
    }

    public function test_saving_the_same_text_does_not_pile_up_versions(): void
    {
        $document = RuleDocument::where('slug', '02-turn-structure')->firstOrFail();

        $this->put("/rules/{$document->slug}", ['title' => $document->title, 'body' => $document->body]);

        $this->assertSame(0, $document->versions()->count());
    }

    public function test_it_restores_an_earlier_version(): void
    {
        $document = RuleDocument::where('slug', '02-turn-structure')->firstOrFail();
        $original = $document->body;

        $this->put("/rules/{$document->slug}", ['title' => $document->title, 'body' => 'A mistake.']);
        $version = $document->refresh()->versions()->firstOrFail();

        $this->post("/rules/{$document->slug}/restore/{$version->id}")->assertRedirect();

        $this->assertSame($original, $document->refresh()->body);
        $this->assertSame('A mistake.', $document->versions()->first()->body);
    }

    public function test_it_will_not_restore_a_version_from_another_document(): void
    {
        $a = RuleDocument::where('slug', '01-core-rules')->firstOrFail();
        $b = RuleDocument::where('slug', '02-turn-structure')->firstOrFail();

        $b->snapshot('test');
        $version = $b->versions()->firstOrFail();

        $this->post("/rules/{$a->slug}/restore/{$version->id}")->assertNotFound();
    }

    public function test_it_reports_which_numbers_a_document_reads(): void
    {
        $document = RuleDocument::where('slug', '01-core-rules')->firstOrFail();
        $document->update(['body' => 'The pool starts at {config:startingOmen} and gold is {config:baseGoldPerRound}.']);

        $this->get("/rules/{$document->slug}")
            ->assertInertia(fn ($page) => $page->where('document.references', ['startingOmen', 'baseGoldPerRound']));
    }

    public function test_it_creates_a_new_document(): void
    {
        $this->post('/rules', ['title' => '06 Characters', 'slug' => '06-characters'])->assertRedirect();

        $this->assertDatabaseHas('rule_documents', ['slug' => '06-characters']);
    }
}
