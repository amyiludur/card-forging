<?php

namespace App\Models\Concerns;

/**
 * A card that can carry a picture: the path into design/art, the window's
 * size, crop-or-fit, and the focus point. What null means for each of them is
 * App\Support\CardArt's to say; this only makes the four columns writable and
 * reads them back as the types they are.
 */
trait HasArt
{
    public function initializeHasArt(): void
    {
        $this->mergeFillable(['art', 'art_size', 'art_fit', 'art_focus']);
        $this->mergeCasts([
            'art_size' => 'integer',
            'art_focus' => 'array',
        ]);
    }
}
