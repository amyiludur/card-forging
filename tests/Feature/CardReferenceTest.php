<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\Scenario;
use App\Support\CardNames;
use App\Support\Markup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * {card:…} — one card naming another, and a rename in the editor following
 * through to every reference. Built from cards made here rather than from the
 * design folder, so it tests the tool and not this week's draft.
 */
class CardReferenceTest extends TestCase
{
    use RefreshDatabase;

    private Scenario $deep;

    private Scenario $shore;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deep = Scenario::create(['slug' => 'deep', 'name' => 'The Deep']);
        $this->shore = Scenario::create(['slug' => 'shore', 'name' => 'The Shore']);
    }

    private function card(Scenario $scenario, string $name): EntityCard
    {
        return EntityCard::create(['scenario_id' => $scenario->id, 'name' => $name, 'layout' => 'single', 'arrow' => 'top']);
    }

    private function board(Scenario $scenario, string $name, string $text = ''): BoardCard
    {
        return BoardCard::create(['scenario_id' => $scenario->id, 'name' => $name, 'text' => $text]);
    }

    public function test_a_reference_prints_the_card_s_name(): void
    {
        $this->card($this->deep, 'Tentacle Lash');

        $markup = Markup::make();

        // Marked as a card on screen and on the sheet; plain words in the design folder.
        $this->assertSame('Discard <span class="markup-card">Tentacle Lash</span>.', $markup->toHtml('Discard {card:tentacle-lash}.'));
        $this->assertSame('Discard <span class="markup-card">Tentacle Lash</span>.', $markup->toHtml('Discard {card:deep/tentacle-lash}.'));
        $this->assertSame(
            '<strong><span class="markup-card">Tentacle Lash</span></strong>',
            $markup->toHtml('**{card:tentacle-lash}**')
        );
        $this->assertSame('Discard Tentacle Lash.', $markup->toPlain('Discard {card:tentacle-lash}.'));
    }

    public function test_a_reference_nothing_answers_to_is_reported(): void
    {
        $this->card($this->deep, 'Tentacle Lash');

        $markup = Markup::make();

        $this->assertStringContainsString('<span class="markup-missing">?card:gone</span>', $markup->toHtml('{card:gone}'));
        // The right name with the wrong owner is not that card.
        $this->assertStringContainsString('?card:shore/tentacle-lash', $markup->toHtml('{card:shore/tentacle-lash}'));
        // The design folder keeps it as typed.
        $this->assertSame('{card:gone}', $markup->toPlain('{card:gone}'));
    }

    public function test_renaming_a_card_rewrites_every_reference_to_it(): void
    {
        $lash = $this->card($this->deep, 'Tentacle Lash');
        $other = $this->board($this->shore, 'Wreck', 'Discard {card:tentacle-lash}, or {card:deep/tentacle-lash}.');

        $lash->update(['name' => 'Kraken Lash']);

        $this->assertSame('Discard {card:kraken-lash}, or {card:deep/kraken-lash}.', $other->refresh()->text);
        $this->assertSame(
            'Discard <span class="markup-card">Kraken Lash</span>, or <span class="markup-card">Kraken Lash</span>.',
            Markup::make()->toHtml($other->text)
        );
    }

    public function test_a_rename_leaves_a_reference_to_another_owner_s_card_alone(): void
    {
        $deepWreck = $this->board($this->deep, 'Wreck');
        $this->board($this->shore, 'Wreck');
        $text = $this->card($this->deep, 'Reader');
        $text->faces()->create(['half' => 'single', 'text' => 'One {card:shore/wreck}, one {card:deep/wreck}, any {card:wreck}.', 'sort' => 0]);

        $deepWreck->update(['name' => 'Hulk']);

        // The Shore still has a Wreck, so the bare reference may mean it and
        // is not moved; only the one naming the Deep follows.
        $this->assertSame(
            'One {card:shore/wreck}, one {card:deep/hulk}, any {card:wreck}.',
            $text->faces()->first()->text
        );
    }

    public function test_a_rename_reaches_a_character_s_extra_abilities(): void
    {
        $lash = $this->card($this->deep, 'Tentacle Lash');
        $hero = Character::create([
            'slug' => 'hero', 'name' => 'Hero',
            'extra_abilities' => [['name' => 'Cut', 'text' => 'Cancel {card:deep/tentacle-lash}.']],
        ]);

        $lash->update(['name' => 'Kraken Lash']);

        $this->assertSame('Cancel {card:deep/kraken-lash}.', $hero->refresh()->extra_abilities[0]['text']);
    }

    public function test_an_import_never_rewrites_a_reference(): void
    {
        $lash = $this->card($this->deep, 'Tentacle Lash');
        $other = $this->board($this->shore, 'Wreck', 'Discard {card:tentacle-lash}.');

        CardNames::withoutFollowing(fn () => $lash->update(['name' => 'Kraken Lash']));

        $this->assertSame('Discard {card:tentacle-lash}.', $other->refresh()->text);
        $this->assertStringContainsString('?card:tentacle-lash', Markup::make()->toHtml($other->text));
    }

    public function test_the_editor_is_sent_every_card_by_key(): void
    {
        $this->card($this->deep, 'Tentacle Lash');

        $this->get('/')->assertInertia(fn ($page) => $page
            ->where('markup.cards.tentacle-lash.0.name', 'Tentacle Lash')
            ->where('markup.cards.tentacle-lash.0.owner', 'deep')
            ->etc());
    }

    public function test_a_keyword_cannot_be_called_card(): void
    {
        $this->assertContains('card', Markup::reservedTokens());
    }
}
