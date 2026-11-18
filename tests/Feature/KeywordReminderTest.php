<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Keyword;
use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keyword reminders on the print sheet: written once in the keyword library,
 * printed after the text that uses the keyword, and kept only where the card
 * has room — which the sheet's own script decides, because only a browser
 * laying the card out knows.
 */
class KeywordReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');

        Keyword::where('token', 'fastplay')->update(['reminder' => 'Pay omen instead of its cost.']);
    }

    private function coin(): PlayerCard
    {
        $coin = PlayerCard::where('slug', 'lucky-coin')->firstOrFail();
        $coin->update(['text' => '{fastplay} Gain 1 gold.']);

        return $coin;
    }

    public function test_a_keyword_saves_its_reminder(): void
    {
        $keyword = Keyword::where('token', 'unique')->firstOrFail();

        $this->put("/rules/keywords/{$keyword->id}", [
            'token' => 'unique',
            'name' => 'Unique',
            'description' => $keyword->description,
            'reminder' => 'One per deck.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('One per deck.', $keyword->fresh()->reminder);
    }

    public function test_the_sheet_prints_reminders_hidden_with_the_fit_script(): void
    {
        $coin = $this->coin();

        $html = $this->get("/print/character/{$coin->character->slug}/sheet?deck=all")->assertOk()->getContent();

        $this->assertStringContainsString('class="reminder" hidden', $html);
        $this->assertStringContainsString('Pay omen instead of its cost.', $html);
        $this->assertStringContainsString("card.querySelectorAll('.reminder')", $html);
        // Each half is measured as well as the body: a half shrinks to its
        // share, so its reminder can run into the next half or the flavour
        // while the body still fits. Checked in Chromium with a split card.
        $this->assertStringContainsString("card.querySelectorAll('.card-body, .half')", $html);
    }

    public function test_reminders_can_be_turned_off(): void
    {
        $coin = $this->coin();

        $html = $this->get("/print/character/{$coin->character->slug}/sheet?deck=all&reminders=0")->assertOk()->getContent();

        $this->assertStringNotContainsString('class="reminder"', $html);
    }

    public function test_the_pocket_page_prints_no_reminders(): void
    {
        $this->coin();

        $html = $this->get('/pocket')->assertOk()->getContent();

        $this->assertFalse(str_contains($html, 'class="reminder"'), 'The pocket page printed a reminder.');
    }

    public function test_a_split_card_reminds_each_keyword_once(): void
    {
        $card = EntityCard::where('name', 'Rising Tide')->firstOrFail();
        $type = CardType::where('slug', 'attack')->value('id');

        $card->faces()->delete();
        $card->faces()->create(['half' => 'top', 'card_type_id' => $type, 'text' => '{fastplay} Up.', 'sort' => 0]);
        $card->faces()->create(['half' => 'bottom', 'card_type_id' => $type, 'text' => '{fastplay} Down.', 'sort' => 1]);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();
        $face = substr($html, strpos($html, 'Rising Tide'));
        $face = substr($face, 0, strpos($face, 'class="arrow-edge'));

        $this->assertSame(1, substr_count($face, 'class="reminder"'));
    }
}
