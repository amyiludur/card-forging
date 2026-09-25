<?php

namespace App\Support;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;

/**
 * One shape for a card, used by the editor, the browser preview and the print
 * sheet, so what the designer sees on screen is what comes out of the printer.
 *
 * {dreadRule} and {dreadAmount} are resolved per card, off the card's own
 * scenario, so a list that mixes scenarios gives each card the right rule and
 * the right number, and a module card — which has no scenario — gets neither.
 * {this} is resolved the same way, off the card's own name.
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

    /**
     * The markup as one scenario's cards read it: its Dread rule behind
     * {dreadRule}, and the Dread its dial starts on behind {dreadAmount}.
     *
     * The one place the pair is resolved, so a card can never be given the
     * rule and not the number. A module card has no scenario and passes null,
     * which is what makes both tokens report rather than guess.
     */
    private function dreadOf(?Scenario $scenario): Markup
    {
        return $this->markup->withDread(
            $scenario?->dread_effect,
            // As card text, not as a figure: a printed card cannot know how
            // many people are at the table, so an equation prints as one.
            $scenario?->startingDread()->markup(),
        );
    }

    /** The same two, as they travel to the browser's preview beside the card. */
    private function dreadProps(?Scenario $scenario): array
    {
        return [
            'dread_rule' => $scenario?->dread_effect,
            'dread_amount' => $scenario?->startingDread()->markup(),
        ];
    }

    public function entityCard(EntityCard $card): array
    {
        $markup = $this->dreadOf($card->scenario)->withName($card->name);

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
            // The scenario's Dread rule and starting Dread, so the browser's
            // preview writes {dreadRule} and {dreadAmount} out the same way the
            // print sheet already has.
            ...$this->dreadProps($card->scenario),
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
        $markup = $this->dreadOf($card->scenario)->withName($card->name);

        return [
            'id' => $card->id,
            'name' => $card->name,
            'qty' => $card->qty,
            'health' => $card->health,
            // Free text, so "12 per player" still prints as typed; an equation
            // naming the player count draws the icon like every scaled number.
            // Mirrors renderScaled() on the board face of CardPreview.vue.
            'health_scaled' => PlayerScaled::mentionsPerPlayer((string) $card->health),
            'health_html' => $this->markup->toHtml(PlayerScaled::make(null, $card->health)->markup()),
            'traits' => $card->traits ?? [],
            'text' => $card->text,
            'html' => $markup->toHtml((string) $card->text, $this->autoIcons),
            ...$this->dreadProps($card->scenario),
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
            // The character's own two colours, so every card a hero brings
            // reads as theirs. A domain card has no character, so it takes
            // its domain's colours instead, falling back to the dark blue
            // head every player card printed before either could be picked.
            'colour' => $card->character?->colour ?? $card->domain?->colour,
            'colour_secondary' => $card->character?->colour_secondary ?? $card->domain?->colour_secondary,
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
            'html' => $this->markup->withName($card->name)->toHtml((string) $card->text, $this->autoIcons),
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

    /**
     * One number that may count the players, as the three things every card
     * face needs from it: the plain number it falls back to, the equation the
     * designer wrote, and that equation rendered with the {perPlayer} icon.
     *
     * The card prints the equation rather than a number because a printed card
     * cannot know how many people are at the table. Only the playtest table,
     * which knows who is playing, works one out.
     *
     * @return array<string, mixed>
     */
    private function scaled(string $key, PlayerScaled $value): array
    {
        return [
            $key => $value->number,
            $key.'_equation' => $value->equation,
            $key.'_html' => $this->markup->toHtml($value->markup()),
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
            // Each of the three may be a plain number or an equation counting
            // the players. The number is what it always was; the equation and
            // the rendered form travel beside it so the preview can draw it
            // itself and the print sheet has it ready.
            ...$this->scaled('health', $character->scaledHealth()),
            ...$this->scaled('hand_size', $character->scaledHandSize()),
            ...$this->scaled('gold_per_round', $character->scaledGoldPerRound()),
            'ability_name' => $character->ability_name,
            'ability_text' => $character->ability_text,
            'html' => $this->markup->withName($character->name)->toHtml((string) $character->ability_text, $this->autoIcons),
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
        $markup = $this->dreadOf($action->scenario)->withName($action->name);

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
            ...$this->dreadProps($action->scenario),
        ];
    }

    /**
     * The setup card: what to do with the other four piles before the first
     * round. The scenario's own board cards, beats and deck all print already;
     * nothing said how to lay them out, and the `## Setup` section of every
     * scenario's markdown is exactly that sentence.
     *
     * The steps are the designer's text, one per line — the splitting is
     * Scenario::setupSteps(), so the print sheet and the preview cannot
     * disagree about what a step is. The two numbers beside them are the
     * scenario's own: the Dread the dial starts on, and how many modules a
     * play asks for. Nothing here is written on the designer's behalf.
     *
     * The deck size is deliberately not one of them. deckSize() counts the
     * cards a scenario holds, beat-added ones included, and only the base deck
     * is shuffled at setup — a card saying "34" beside "shuffle the deck" would
     * be quietly wrong, and how the deck is built is a step the designer
     * writes, not a number the tool infers.
     */
    public function setupCard(Scenario $scenario): array
    {
        $markup = $this->dreadOf($scenario)->withName($scenario->name);
        $steps = $scenario->setupSteps();

        return [
            'id' => $scenario->id,
            'name' => $scenario->name,
            'setup' => $scenario->setup,
            // As typed, for the browser to render, and rendered, for the sheet.
            'steps' => $steps,
            'steps_html' => array_map(fn (string $step) => $markup->toHtml($step, $this->autoIcons), $steps),
            ...$this->scaled('starting_dread', $scenario->startingDread()),
            'modules_required' => $scenario->modules_required,
            ...$this->dreadProps($scenario),
        ];
    }

    public function storyBeat(StoryBeat $beat): array
    {
        // A beat always belongs to a scenario, so its Dread rule is never in
        // doubt the way a module card's is.
        $markup = $this->dreadOf($beat->scenario)->withName($beat->name);

        return [
            'id' => $beat->id,
            'order' => $beat->order,
            'name' => $beat->name,
            'flavour' => $beat->flavour,
            'on_reach' => $beat->on_reach,
            'advance' => $beat->advance,
            'on_advance' => $beat->on_advance,
            ...$this->scaled('dread_change', $beat->dreadChange()),
            ...$this->dreadProps($beat->scenario),
            'html' => [
                'on_reach' => $markup->toHtml((string) $beat->on_reach, $this->autoIcons),
                'advance' => $markup->toHtml((string) $beat->advance, $this->autoIcons),
                'on_advance' => $markup->toHtml((string) $beat->on_advance, $this->autoIcons),
            ],
        ];
    }
}
