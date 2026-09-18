<?php

namespace App\Support;

use App\Models\BoardCard;
use App\Models\EntityCard;
use App\Models\StoryBeat;

/**
 * One shape for a card, used by the editor, the browser preview and the print
 * sheet, so what the designer sees on screen is what comes out of the printer.
 */
class CardPresenter
{
    public function __construct(private Markup $markup, private bool $autoIcons = false)
    {
    }

    public static function make(bool $autoIcons = false): self
    {
        return new self(Markup::make(), $autoIcons);
    }

    public function entityCard(EntityCard $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'qty' => $card->qty,
            'layout' => $card->layout,
            'omen_cost' => $card->omen_cost,
            'omen_is_x' => $card->omen_is_x,
            'omen_label' => $card->omen_label,
            'traits' => $card->traits ?? [],
            'arrow' => $card->arrow,
            'notes' => $card->notes,
            'is_placeholder' => $card->is_placeholder,
            'added_by_beat_id' => $card->added_by_beat_id,
            'added_by_beat' => $card->addedByBeat ? [
                'id' => $card->addedByBeat->id,
                'order' => $card->addedByBeat->order,
                'name' => $card->addedByBeat->name,
            ] : null,
            'faces' => $card->faces->map(fn ($face) => [
                'id' => $face->id,
                'half' => $face->half,
                'card_type_id' => $face->card_type_id,
                'type' => $face->cardType?->slug,
                'type_name' => $face->cardType?->name,
                'text' => $face->text,
                'html' => $this->markup->toHtml((string) $face->text, $this->autoIcons),
            ])->values()->all(),
        ];
    }

    public function boardCard(BoardCard $card): array
    {
        return [
            'id' => $card->id,
            'name' => $card->name,
            'qty' => $card->qty,
            'health' => $card->health,
            'traits' => $card->traits ?? [],
            'text' => $card->text,
            'html' => $this->markup->toHtml((string) $card->text, $this->autoIcons),
            'added_by_beat_id' => $card->added_by_beat_id,
            'added_by_beat' => $card->addedByBeat ? [
                'order' => $card->addedByBeat->order,
                'name' => $card->addedByBeat->name,
            ] : null,
            'is_placeholder' => $card->is_placeholder,
        ];
    }

    public function storyBeat(StoryBeat $beat): array
    {
        return [
            'id' => $beat->id,
            'order' => $beat->order,
            'name' => $beat->name,
            'flavour' => $beat->flavour,
            'on_reach' => $beat->on_reach,
            'advance' => $beat->advance,
            'on_advance' => $beat->on_advance,
            'dread_change' => $beat->dread_change,
            'html' => [
                'on_reach' => $this->markup->toHtml((string) $beat->on_reach, $this->autoIcons),
                'advance' => $this->markup->toHtml((string) $beat->advance, $this->autoIcons),
                'on_advance' => $this->markup->toHtml((string) $beat->on_advance, $this->autoIcons),
            ],
        ];
    }
}
