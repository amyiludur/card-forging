<?php

namespace App\Support;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\StoryBeat;
use App\Models\TownAction;

/**
 * One shape for a card, used by the editor, the browser preview and the print
 * sheet, so what the designer sees on screen is what comes out of the printer.
 *
 * {dreadRule} is resolved per card, off the card's own scenario, so a list that
 * mixes scenarios gives each card the right rule and a module card — which has
 * no scenario — gets none.
 */
class CardPresenter
{
    /** Printed names for the player card types. */
    public const PLAYER_TYPES = [
        'action' => 'Action',
        'item' => 'Item',
        'response' => 'Response',
        'hireling' => 'Hireling',
    ];

    /** A card that does not start in the deck says where it does start. */
    public const START_ZONE_LABELS = [
        'shop' => 'starts in shop',
        'play' => 'starts in play',
        'upgrade' => 'upgrade',
    ];

    public function __construct(private Markup $markup, private bool $autoIcons = false)
    {
    }

    public static function make(bool $autoIcons = false): self
    {
        return new self(Markup::make(), $autoIcons);
    }

    public function entityCard(EntityCard $card): array
    {
        $markup = $this->markup->withDreadRule($card->scenario?->dread_effect);

        return [
            'id' => $card->id,
            'name' => $card->name,
            'qty' => $card->qty,
            'layout' => $card->layout,
            'omen_cost' => $card->omen_cost,
            'omen_is_x' => $card->omen_is_x,
            'omen_label' => $card->omen_label,
            'traits' => $card->traits ?? [],
            // v2: the arrow sits on the right edge and points at the top or
            // bottom half of the card to its right, not at this card's halves.
            'arrow' => $card->pointsAt(),
            'notes' => $card->notes,
            'module_id' => $card->module_id,
            'set_icon' => $card->module?->set_icon,
            'origin' => $card->origin(),
            'is_placeholder' => $card->is_placeholder,
            // The scenario's Dread rule, so the browser's preview writes
            // {dreadRule} out the same way the print sheet already has.
            'dread_rule' => $card->scenario?->dread_effect,
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
                // A type the designer added has no icon of its own name, so
                // the icon travels rather than being guessed from the slug.
                'type_icon' => $face->cardType?->icon_name,
                // As picked. What the head band and the type line make of it
                // is App\Support\Colour's, in both halves of the card design.
                'type_colour' => $face->cardType?->hex,
                'text' => $face->text,
                'html' => $markup->toHtml((string) $face->text, $this->autoIcons),
            ])->values()->all(),
        ];
    }

    public function boardCard(BoardCard $card): array
    {
        $markup = $this->markup->withDreadRule($card->scenario?->dread_effect);

        return [
            'id' => $card->id,
            'name' => $card->name,
            'qty' => $card->qty,
            'health' => $card->health,
            'traits' => $card->traits ?? [],
            'text' => $card->text,
            'html' => $markup->toHtml((string) $card->text, $this->autoIcons),
            'dread_rule' => $card->scenario?->dread_effect,
            'added_by_beat_id' => $card->added_by_beat_id,
            'added_by_beat' => $card->addedByBeat ? [
                'order' => $card->addedByBeat->order,
                'name' => $card->addedByBeat->name,
            ] : null,
            'is_placeholder' => $card->is_placeholder,
            'module_id' => $card->module_id,
            'set_icon' => $card->module?->set_icon,
            'origin' => $card->origin(),
        ];
    }

    public function playerCard(PlayerCard $card): array
    {
        return [
            'id' => $card->id,
            'slug' => $card->slug,
            'name' => $card->name,
            'qty' => $card->qty,
            'role' => $card->role,
            'origin' => $card->origin,
            // Which pool the card came out of, so a printed card says where it
            // belongs. Null for a character's own cards.
            'domain' => $card->domain?->name,
            'domain_slug' => $card->domain?->slug,
            'set_icon' => $card->domain?->set_icon,
            'type' => $card->type,
            'gold_cost' => $card->gold_cost,
            'omen_icons' => $card->omen_icons,
            // A Hireling's two numbers, and nothing else's: the card face and
            // the editor both draw them only when this is true.
            'is_hireling' => $card->isHireling(),
            'uses' => $card->uses,
            'sacrifice_value' => $card->sacrifice_value,
            'shop_cost' => $card->shop_cost,
            'start_zone' => $card->start_zone,
            'traits' => $card->traits ?? [],
            'keywords' => $card->keywords ?? [],
            'text' => $card->text,
            'html' => $this->markup->toHtml((string) $card->text, $this->autoIcons),
            'upgrades_to' => $card->upgrades_to,
            'upgrade_of' => $card->upgrade_of,
            // The names behind the two slugs, so the card and the editor can
            // say what a pair is without a second lookup in the template.
            'upgrades_to_name' => $card->upgrade()?->name,
            'replaces_name' => $card->replaces()?->name,
            'character_id' => $card->character_id,
            'character' => $card->character?->name,
            'domain_id' => $card->domain_id,
            'is_placeholder' => $card->is_placeholder,
        ];
    }

    /** The character card itself: health, hand size and the identity ability. */
    public function character(Character $character): array
    {
        return [
            'id' => $character->id,
            'slug' => $character->slug,
            'name' => $character->name,
            'title' => $character->title,
            'story' => $character->story,
            'status' => $character->status,
            'identity' => $character->identity,
            // Two colours, printed as a slight gradient across the head band.
            // Either may be empty: one alone is a flat band, neither is the
            // dark head every other card kind prints.
            'colour' => $character->colour,
            'colour_secondary' => $character->colour_secondary,
            'health' => $character->health,
            'hand_size' => $character->hand_size,
            'gold_per_round' => $character->gold_per_round,
            'ability_name' => $character->ability_name,
            'ability_text' => $character->ability_text,
            'html' => $this->markup->toHtml((string) $character->ability_text, $this->autoIcons),
            'is_placeholder' => $character->is_placeholder,
        ];
    }

    /**
     * A district of the town, as a card. The town is a handful of actions every
     * player can take once a round, so it prints like anything else on the
     * table: one card per district, its cost in the corner the deck cards put a
     * cost in, and the omen it adds where a board card carries health.
     */
    public function townAction(TownAction $action): array
    {
        $markup = $this->markup->withDreadRule($action->scenario?->dread_effect);

        return [
            'id' => $action->id,
            'name' => $action->name,
            'effect' => $action->effect,
            'gold_cost' => $action->gold_cost,
            'omen' => $action->omen,
            'note' => $action->note,
            'html' => $markup->toHtml((string) $action->effect, $this->autoIcons),
            // The note is a rule of its own — "while the Whirlpool is in play"
            // — so it renders like the effect rather than as plain words.
            'note_html' => $markup->toHtml((string) $action->note, $this->autoIcons),
            'dread_rule' => $action->scenario?->dread_effect,
        ];
    }

    public function storyBeat(StoryBeat $beat): array
    {
        // A beat always belongs to a scenario, so its Dread rule is never in
        // doubt the way a module card's is.
        $markup = $this->markup->withDreadRule($beat->scenario?->dread_effect);

        return [
            'id' => $beat->id,
            'order' => $beat->order,
            'name' => $beat->name,
            'flavour' => $beat->flavour,
            'on_reach' => $beat->on_reach,
            'advance' => $beat->advance,
            'on_advance' => $beat->on_advance,
            'dread_change' => $beat->dread_change,
            'dread_rule' => $beat->scenario?->dread_effect,
            'html' => [
                'on_reach' => $markup->toHtml((string) $beat->on_reach, $this->autoIcons),
                'advance' => $markup->toHtml((string) $beat->advance, $this->autoIcons),
                'on_advance' => $markup->toHtml((string) $beat->on_advance, $this->autoIcons),
            ],
        ];
    }
}
