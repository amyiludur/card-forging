<?php

namespace Tests\Unit;

use App\Models\EntityCard;
use App\Support\Storyline;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class StorylineTest extends TestCase
{
    private function card(string $arrow, string $layout = 'single'): EntityCard
    {
        return new EntityCard(['arrow' => $arrow, 'layout' => $layout]);
    }

    private function row(array $cards, ?string $incoming = null, array $flipped = []): array
    {
        return (new Storyline(['defaultArrow' => 'top']))
            ->resolve(new Collection($cards), $incoming, $flipped)['row'];
    }

    public function test_a_split_card_resolves_the_half_the_card_before_it_points_at(): void
    {
        $row = $this->row([
            $this->card('bottom'),
            $this->card('top', 'split'),
        ]);

        // The split card's own arrow says nothing about its own halves.
        $this->assertSame('bottom', $row[1]['resolves']);
        $this->assertSame('bottom', $row[1]['deciding_arrow']);
    }

    public function test_the_first_card_uses_the_arrow_carried_in(): void
    {
        $row = $this->row([$this->card('top', 'split')], 'bottom');

        $this->assertSame('bottom', $row[0]['resolves']);
    }

    public function test_the_first_card_falls_back_to_the_default_arrow(): void
    {
        $row = $this->row([$this->card('bottom', 'split')]);

        $this->assertSame('top', $row[0]['resolves']);
    }

    public function test_a_single_effect_card_just_resolves(): void
    {
        $row = $this->row([$this->card('top'), $this->card('bottom')]);

        $this->assertSame('single', $row[0]['resolves']);
        $this->assertSame('single', $row[1]['resolves']);
        $this->assertFalse($row[0]['is_split']);
    }

    public function test_an_x_cost_card_still_carries_an_arrow_for_the_next_card(): void
    {
        $row = $this->row([
            $this->card('bottom', 'x-cost'),
            $this->card('top', 'split'),
        ]);

        $this->assertSame('single', $row[0]['resolves']);
        $this->assertSame('bottom', $row[1]['resolves']);
    }

    public function test_redirect_changes_the_card_after_the_one_flipped(): void
    {
        $cards = [
            $this->card('top'),
            $this->card('top', 'split'),
            $this->card('top', 'split'),
        ];

        $plain = $this->row($cards);
        $this->assertSame('top', $plain[1]['resolves']);

        // Flipping card 0 changes card 1, and leaves card 2 alone.
        $flipped = $this->row($cards, null, [0 => true]);

        $this->assertSame('bottom', $flipped[1]['resolves']);
        $this->assertSame('top', $flipped[2]['resolves']);
        $this->assertTrue($flipped[0]['flipped']);
    }

    public function test_it_reports_the_arrow_carried_out_to_the_next_row(): void
    {
        $resolved = (new Storyline(['defaultArrow' => 'top']))->resolve(new Collection([
            $this->card('top'),
            $this->card('bottom'),
        ]));

        $this->assertSame('bottom', $resolved['outgoing']);
        $this->assertSame('top', $resolved['incoming']);
    }

    public function test_a_flip_on_the_last_card_changes_what_is_carried_out(): void
    {
        $resolved = (new Storyline(['defaultArrow' => 'top']))->resolve(
            new Collection([$this->card('top'), $this->card('bottom')]),
            null,
            [1 => true]
        );

        $this->assertSame('top', $resolved['outgoing']);
    }

    public function test_an_empty_row_carries_the_incoming_arrow_straight_through(): void
    {
        $resolved = (new Storyline(['defaultArrow' => 'top']))->resolve(new Collection(), 'bottom');

        $this->assertSame([], $resolved['row']);
        $this->assertSame('bottom', $resolved['outgoing']);
    }
}
