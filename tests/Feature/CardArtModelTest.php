<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\Keyword;
use App\Models\PlayerCard;
use App\Models\StoryBeat;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Models\TownAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The columns behind card art, flavour and keyword reminders: every card kind
 * with an editor keeps a picture and its placement.
 */
class CardArtModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    /** @return array<int, Model> one row of every card kind that can carry art */
    private function oneOfEach(): array
    {
        $tale = Tale::first();

        return [
            EntityCard::first(),
            BoardCard::first(),
            TownAction::first(),
            PlayerCard::first(),
            Character::first(),
            StoryBeat::first(),
            TaleCard::create(['tale_id' => $tale->id, 'slug' => 'omen-card', 'name' => 'Omen Card', 'qty' => 1]),
        ];
    }

    public function test_every_art_kind_stores_and_casts_its_art(): void
    {
        foreach ($this->oneOfEach() as $model) {
            $model->update([
                'art' => 'art/x/a.png',
                'art_size' => 55,
                'art_fit' => 'contain',
                'art_focus' => [10, 90],
            ]);

            $fresh = $model->fresh();

            $this->assertSame('art/x/a.png', $fresh->art, $model::class);
            $this->assertSame(55, $fresh->art_size, $model::class);
            $this->assertSame('contain', $fresh->art_fit, $model::class);
            $this->assertSame([10, 90], $fresh->art_focus, $model::class);
        }
    }

    public function test_flavour_is_fillable_on_the_four_new_kinds(): void
    {
        foreach ([EntityCard::first(), BoardCard::first(), TownAction::first(), PlayerCard::first()] as $model) {
            $model->update(['flavour' => 'Salt and silence.']);

            $this->assertSame('Salt and silence.', $model->fresh()->flavour, $model::class);
        }
    }

    public function test_a_keyword_carries_its_reminder_into_the_markup_map(): void
    {
        Keyword::where('token', 'unique')->firstOrFail()->update(['reminder' => 'One per deck.']);

        $this->assertSame('One per deck.', Keyword::markupMap()['unique']['reminder']);
    }
}
