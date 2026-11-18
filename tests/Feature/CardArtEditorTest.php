<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\TownAction;
use App\Support\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every editor saves a card's picture settings and, where the card has one, its
 * flavour; the presenter hands both to the card faces with the defaults
 * resolved.
 */
class CardArtEditorTest extends TestCase
{
    use RefreshDatabase;

    private const ART = [
        'art' => 'art/board/tide.png',
        'art_size' => 30,
        'art_fit' => 'contain',
        'art_focus' => [20, 80],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function assertArtSaved($model): void
    {
        $fresh = $model->fresh();

        $this->assertSame('art/board/tide.png', $fresh->art, $model::class);
        $this->assertSame(30, $fresh->art_size, $model::class);
        $this->assertSame('contain', $fresh->art_fit, $model::class);
        $this->assertSame([20, 80], $fresh->art_focus, $model::class);
        $this->assertSame('Brine and bone.', $fresh->flavour, $model::class);
    }

    public function test_a_board_card_saves_its_art_and_flavour(): void
    {
        $card = BoardCard::firstOrFail();

        $this->put("/board-cards/{$card->id}", [
            'name' => $card->name,
            'qty' => $card->qty,
            'health' => $card->health,
            'traits' => $card->traits ?? [],
            'text' => $card->text,
            'flavour' => 'Brine and bone.',
            ...self::ART,
        ])->assertSessionHasNoErrors();

        $this->assertArtSaved($card);
    }

    public function test_a_town_district_saves_its_art_and_flavour(): void
    {
        $action = TownAction::firstOrFail();

        $this->put("/town-actions/{$action->id}", [
            'name' => $action->name,
            'effect' => $action->effect,
            'gold_cost' => $action->gold_cost,
            'omen' => $action->omen,
            'note' => $action->note,
            'flavour' => 'Brine and bone.',
            ...self::ART,
        ])->assertSessionHasNoErrors();

        $this->assertArtSaved($action);
    }

    public function test_an_entity_card_saves_its_art_and_flavour(): void
    {
        $card = EntityCard::where('name', 'Salt Wind')->firstOrFail();

        $this->put("/cards/{$card->id}", [
            'name' => 'Salt Wind',
            'qty' => 2,
            'layout' => 'single',
            'omen_cost' => 1,
            'omen_is_x' => false,
            'traits' => [],
            'arrow' => 'top',
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => CardType::where('slug', 'attack')->value('id'), 'text' => 'Deal 1 damage.']],
            'flavour' => 'Brine and bone.',
            ...self::ART,
        ])->assertSessionHasNoErrors();

        $this->assertArtSaved($card);
    }

    public function test_a_player_card_saves_its_art_and_flavour(): void
    {
        $card = PlayerCard::where('slug', 'lucky-coin')->firstOrFail();

        $this->put("/player-cards/{$card->id}", [
            'name' => $card->name,
            'slug' => $card->slug,
            'qty' => $card->qty,
            'role' => $card->role,
            'origin' => $card->origin,
            'type' => $card->type,
            'gold_cost' => $card->gold_cost,
            'omen_icons' => $card->omen_icons,
            'shop_cost' => $card->shop_cost,
            'start_zone' => $card->start_zone,
            'text' => $card->text,
            'traits' => $card->traits ?? [],
            'keywords' => $card->keywords ?? [],
            'upgrades_to' => $card->upgrades_to,
            'upgrade_of' => $card->upgrade_of,
            'is_placeholder' => $card->is_placeholder,
            'flavour' => 'Brine and bone.',
            ...self::ART,
        ])->assertSessionHasNoErrors();

        $this->assertArtSaved($card);
    }

    public function test_art_settings_out_of_range_are_refused(): void
    {
        $card = BoardCard::firstOrFail();
        $base = ['name' => $card->name, 'qty' => $card->qty];

        foreach ([
            'art_size' => ['art_size' => 95],
            'art_fit' => ['art_fit' => 'stretch'],
            'art_focus' => ['art_focus' => [50]],
            'art' => ['art' => '../x.png'],
        ] as $field => $bad) {
            $this->put("/board-cards/{$card->id}", $base + $bad)->assertSessionHasErrors($field);
        }

        $this->put("/board-cards/{$card->id}", $base + ['art' => 'art/../x.png'])->assertSessionHasErrors('art');
    }

    public function test_the_presenter_resolves_the_window(): void
    {
        $presenter = CardPresenter::make();

        $entity = $presenter->entityCard(EntityCard::with('faces.cardType')->firstOrFail());
        $board = $presenter->boardCard(BoardCard::firstOrFail());

        $this->assertSame(40, $entity['art_window']);
        $this->assertSame(40, $board['art_window']);
        $this->assertSame('cover', $board['art_fit']);
        $this->assertSame([50, 50], $board['art_focus']);
        $this->assertFalse($board['art_missing']);
        $this->assertArrayHasKey('flavour', $board);
    }

    public function test_a_card_naming_a_picture_that_is_not_there_is_reported(): void
    {
        $card = BoardCard::firstOrFail();
        $card->update(['art' => 'art/board/nowhere.png']);

        $presented = CardPresenter::make()->boardCard($card->fresh());

        $this->assertTrue($presented['art_missing']);
        $this->assertSame('art/board/nowhere.png', $presented['art']);
        $this->assertNull($presented['art_class']);
    }

    /**
     * An editor whose form starts empty would save the picture away the next
     * time anything else on the card is changed, so every edit page hands back
     * what was saved.
     */
    public function test_the_player_and_character_editors_hand_back_the_art_they_saved(): void
    {
        $card = PlayerCard::where('slug', 'lucky-coin')->firstOrFail();
        $card->update(self::ART + ['flavour' => 'Brine and bone.']);
        $character = $card->character;
        $character->update(self::ART);

        $this->get("/player-cards/{$card->id}/edit")->assertInertia(fn ($page) => $page
            ->where('card.art', 'art/board/tide.png')
            ->where('card.art_size', 30)
            ->where('card.art_fit', 'contain')
            ->where('card.art_focus', [20, 80])
            ->where('card.flavour', 'Brine and bone.')
        );

        $this->get("/characters/{$character->slug}/edit")->assertInertia(fn ($page) => $page
            ->where('character.art', 'art/board/tide.png')
            ->where('character.art_size', 30)
            ->where('character.art_focus', [20, 80])
        );
    }

    public function test_a_duplicated_card_keeps_its_picture(): void
    {
        $card = EntityCard::where('name', 'Rising Tide')->firstOrFail();
        $card->update(self::ART);

        $this->post("/cards/{$card->id}/duplicate")->assertRedirect();

        $copy = EntityCard::where('name', 'Rising Tide (copy)')->firstOrFail();

        $this->assertSame('art/board/tide.png', $copy->art);
        $this->assertSame([20, 80], $copy->art_focus);
    }
}
