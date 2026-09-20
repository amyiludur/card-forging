<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Keyword;
use App\Models\PlayerCard;
use App\Support\Markup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_designer_can_add_a_keyword(): void
    {
        $this->post('/rules/keywords', [
            'token' => 'unique',
            'name' => 'Unique',
            'description' => 'A deck may hold only one copy.',
        ])->assertRedirect();

        $keyword = Keyword::firstWhere('token', 'unique');

        $this->assertSame('Unique', $keyword->name);
        // New data is a draft until the designer says otherwise.
        $this->assertTrue($keyword->is_placeholder);
        $this->assertTrue($keyword->show_name);
    }

    public function test_a_keyword_renders_in_card_text(): void
    {
        Keyword::create(['token' => 'unique', 'name' => 'Unique', 'is_placeholder' => false]);

        $html = Markup::make()->toHtml('This card is {unique}.');

        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('Unique', $html);
    }

    public function test_a_keyword_cannot_take_an_icon_token_name(): void
    {
        $this->post('/rules/keywords', ['token' => 'omen', 'name' => 'Omen'])
            ->assertSessionHasErrors('token');

        $this->assertSame(0, Keyword::count());
    }

    public function test_a_token_has_to_look_like_a_token(): void
    {
        $this->post('/rules/keywords', ['token' => 'Not A Token', 'name' => 'Nope'])
            ->assertSessionHasErrors('token');

        // A hyphen is fine: {bottom-draw} is a keyword like any other.
        $this->post('/rules/keywords', ['token' => 'bottom-draw', 'name' => 'Bottom draw'])
            ->assertRedirect();

        $this->assertSame(1, Keyword::count());
    }

    public function test_two_keywords_cannot_share_a_token(): void
    {
        Keyword::create(['token' => 'unique', 'name' => 'Unique']);

        $this->post('/rules/keywords', ['token' => 'unique', 'name' => 'Also unique'])
            ->assertSessionHasErrors('token');
    }

    public function test_the_page_says_how_much_text_uses_a_keyword(): void
    {
        Keyword::create(['token' => 'unique', 'name' => 'Unique']);
        $character = Character::create(['slug' => 'gunslinger', 'name' => 'Gunslinger']);
        PlayerCard::create([
            'character_id' => $character->id,
            'slug' => 'lucky-coin',
            'name' => 'Lucky Coin',
            'role' => PlayerCard::ROLE_SIGNATURE,
            'text' => 'This card is {unique}.',
        ]);

        $this->get('/rules/keywords')->assertInertia(
            fn ($page) => $page->component('Rules/Keywords')->where('keywords.0.uses', 1)
        );
    }

    public function test_deleting_a_keyword_leaves_the_text_that_used_it_as_typed(): void
    {
        $keyword = Keyword::create(['token' => 'unique', 'name' => 'Unique']);
        $character = Character::create(['slug' => 'gunslinger', 'name' => 'Gunslinger']);
        $card = PlayerCard::create([
            'character_id' => $character->id,
            'slug' => 'lucky-coin',
            'name' => 'Lucky Coin',
            'role' => PlayerCard::ROLE_SIGNATURE,
            'text' => 'This card is {unique}.',
        ]);

        $this->delete("/rules/keywords/{$keyword->id}")->assertRedirect();

        // The designer's words are never rewritten: the token prints as typed.
        $this->assertSame('This card is {unique}.', $card->fresh()->text);
        $this->assertStringContainsString('{unique}', Markup::make()->toHtml($card->text));
    }

    public function test_a_keyword_prints_on_the_card(): void
    {
        // The print sheet is the other implementation of the card face, so a
        // keyword has to reach it too — and as inline SVG and text, never a
        // stylesheet link that file:// would not load.
        $this->artisan('design:import');
        // updateOrCreate rather than create: the design folder may already
        // ship this exact keyword, and this test only cares that it prints.
        Keyword::updateOrCreate(['token' => 'unique'], ['name' => 'Unique', 'is_placeholder' => false]);

        $card = PlayerCard::whereNotNull('character_id')->firstOrFail();
        $card->update(['text' => 'This card is {unique}.']);

        $html = $this->get('/print/character/'.$card->character->slug.'/sheet?deck=player')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('.markup-keyword', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    public function test_keywords_round_trip_through_the_design_folder(): void
    {
        Keyword::create([
            'token' => 'unique',
            'name' => 'Unique',
            'icon' => 'zone-deck',
            'show_name' => true,
            'plain' => 'Unique',
            'description' => 'A deck may hold only one copy.',
            'is_placeholder' => false,
            'sort' => 0,
        ]);

        $path = sys_get_temp_dir().'/card-forge-keywords-'.uniqid();
        $this->artisan('design:export', ['--path' => $path])->assertExitCode(0);

        $file = "{$path}/data/keywords.json";
        $this->assertFileExists($file);

        $written = json_decode(file_get_contents($file), true)['keywords'];
        $this->assertSame('unique', $written[0]['token']);
        $this->assertSame('zone-deck', $written[0]['icon']);
        $this->assertFalse($written[0]['isPlaceholder']);

        Keyword::query()->delete();
        $this->artisan('design:import', ['--path' => $path])->assertExitCode(0);

        $keyword = Keyword::firstWhere('token', 'unique');
        $this->assertSame('Unique', $keyword->name);
        $this->assertSame('zone-deck', $keyword->icon);
        $this->assertFalse($keyword->is_placeholder);
    }

    public function test_a_design_folder_with_no_keywords_does_not_grow_a_file(): void
    {
        $path = sys_get_temp_dir().'/card-forge-keywords-'.uniqid();
        $this->artisan('design:export', ['--path' => $path])->assertExitCode(0);

        $this->assertFileDoesNotExist("{$path}/data/keywords.json");
    }
}
