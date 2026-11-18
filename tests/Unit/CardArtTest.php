<?php

namespace Tests\Unit;

use App\Support\CardArt;
use Tests\TestCase;

/**
 * The art rules that both halves of the card design follow: how big the
 * window is, and which paths count as a picture in the design folder.
 */
class CardArtTest extends TestCase
{
    public function test_the_window_defaults_to_forty_on_every_card_picture_or_not(): void
    {
        $this->assertSame(40, CardArt::windowSize(null, null, true));
        $this->assertSame(40, CardArt::windowSize(null, null, false));
        $this->assertSame(40, CardArt::windowSize('art/a.png', null, false));
    }

    public function test_a_size_that_is_set_always_wins(): void
    {
        $this->assertSame(25, CardArt::windowSize(null, 25, false));
        $this->assertSame(0, CardArt::windowSize('art/a.png', 0, true));
    }

    public function test_nothing_outside_the_art_folder_resolves(): void
    {
        $this->assertNull(CardArt::absolute('art/../../.env'));
        $this->assertNull(CardArt::absolute('../secret.png'));
        $this->assertNull(CardArt::absolute('art/nowhere/missing.png'));
        $this->assertNull(CardArt::absolute(null));
        $this->assertNull(CardArt::absolute(''));
    }

    public function test_a_path_always_gets_the_same_class(): void
    {
        $this->assertSame(CardArt::cssClass('art/board/a.png'), CardArt::cssClass('art/board/a.png'));
        $this->assertNotSame(CardArt::cssClass('art/board/a.png'), CardArt::cssClass('art/board/b.png'));
        $this->assertMatchesRegularExpression('/^art-[0-9a-f]{12}$/', CardArt::cssClass('art/board/a.png'));
    }
}
