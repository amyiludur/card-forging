<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
import { scaledMarkup } from '../playerScaled';
import { band, chip, halo, ink, normalise, onPaper } from '../colour';
import Icon from './Icon.vue';

const props = defineProps({
    card: { type: Object, required: true },
    kind: { type: String, default: 'entity' },
    width: { type: Number, default: 200 },
    autoIcons: { type: Boolean, default: true },
    // 'top' or 'bottom' marks the half a split card resolves. Applied to the
    // half element itself rather than an overlay at a guessed percentage, so it
    // lands exactly on the half at any card size.
    highlight: { type: String, default: null },
});

const page = usePage();
const markupOptions = computed(() => ({
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
    // {dreadRule} is the card's own scenario's rule, and {dreadAmount} the
    // Dread it starts on, so both travel with the card rather than with the
    // page: a list mixing scenarios still gets each card right, and a module
    // card has neither.
    dreadRule: props.card.dread_rule ?? null,
    dreadAmount: props.card.dread_amount ?? null,
    // {this} is the card's own name, as the head prints it.
    cardName: props.card.name ?? null,
    autoIcons: props.autoIcons,
}));

// Poker card proportions; everything inside is sized in em off the card width
// so the preview stays faithful at any scale.
const style = computed(() => ({
    width: `${props.width}px`,
    height: `${Math.round((props.width / 63.5) * 88.9)}px`,
    fontSize: `${props.width / 20}px`,
}));

const render = (text) => renderMarkup(text, markupOptions.value);

/**
 * A number that may count the players: the equation as the designer wrote it,
 * with {perPlayer} drawn as the icon, or the plain number when there is no
 * equation. Mirrors CardPresenter::scaled() building the same thing for the
 * print sheet — change one and change the other.
 */
const renderScaled = (value, equation) => render(scaledMarkup(value, equation));

const faces = computed(() => props.card.faces ?? []);
const traits = computed(() => props.card.traits ?? []);

/*
 * The card's colours, and what the head band and the type line make of them.
 * Mirrors the same block in resources/views/print/partials/card.blade.php —
 * one card design, two implementations, so change one and change the other.
 *
 * An entity card takes its colour from its type: a split card whose halves are
 * different types takes both, top colour at the top, the way the halves sit. A
 * character takes the two colours the designer picked, and so does every card
 * that character brings — a hero's deck is theirs on sight. Nothing coloured
 * leaves the head exactly as dark as it always was.
 */
// The head each card kind prints when nothing has been coloured: exactly what
// it printed before a colour could be picked.
const defaultHead = { entity: '#1c1917', character: '#3f2b56', player: '#1e3a5f' };

const headColours = computed(() => {
    // A player card's colours are its character's; a domain card carries none,
    // because a domain belongs to no one hero.
    if (props.kind === 'character' || props.kind === 'player') {
        return [props.card.colour, props.card.colour_secondary].map(normalise).filter(Boolean);
    }

    if (props.kind !== 'entity') return [];

    return [...new Set(faces.value.map((face) => normalise(face.type_colour)).filter(Boolean))];
});

const headStyle = computed(() => {
    const [from, to] = headColours.value;
    // A hero's wash runs across the corner; a split card's two run down the
    // band, because that is where its halves are.
    const background = band(from, to, props.kind === 'entity' ? 'to bottom' : '135deg')
        ?? defaultHead[props.kind];

    if (!background) return {};

    return {
        background,
        color: ink(...(headColours.value.length ? headColours.value : [background])),
        // Two stops that disagree about which ink reads get a halo behind the
        // name, the way the arrow on the card's edge already does.
        ...(headColours.value.length > 1
            ? { textShadow: `0 0 0.18em ${halo(...headColours.value)}, 0 0 0.18em ${halo(...headColours.value)}` }
            : {}),
    };
});

// The type line sits on the cream body, so it takes a version of the colour
// dark enough to read there rather than the colour as picked.
const typeStyle = (face) => (normalise(face?.type_colour) ? { color: onPaper(face.type_colour) } : {});

// The gold chip in a player card's head. It was picked to match the dark blue
// head, so once a hero colours their cards it follows the head instead.
// Mirrored by $chipStyle in resources/views/print/partials/card.blade.php.
const chipStyle = computed(() => {
    const [first] = headColours.value;

    if (!first) return { background: '#334e68', color: '#fdfcf9' };

    return { background: chip(first), color: ink(chip(first)) };
});

const typeNames = { action: 'Action', item: 'Item', response: 'Response', hireling: 'Hireling' };

// A Hireling stays in play, so it prints two numbers no other card has: the
// uses its term runs for, and what sending it away prevents. Mirrors the
// hireling block in resources/views/print/partials/card.blade.php.
const isHireling = computed(() => props.card.type === 'hireling');

// A card that does not start in the deck says so, because where it starts is
// half of how a character plays. An upgrade names the card it replaces instead:
// that is what someone at the Smithy needs to read off it.
const zoneLabels = { shop: 'starts in shop', play: 'starts in play', upgrade: 'upgrade' };

const cornerNote = computed(() =>
    props.card.role === 'upgrade'
        ? `replaces ${props.card.replaces_name ?? 'nothing yet'}`
        : zoneLabels[props.card.start_zone] ?? null
);

const cornerIcon = computed(() =>
    props.card.role === 'upgrade' ? 'zone-upgrade' : `zone-${props.card.start_zone}`
);

// A card out of a shared pool says so, the way a module card carries its set
// icon. The name is the fallback, so a domain without an icon still prints one.
const domainBadge = computed(() => props.card.set_icon || props.card.domain || null);
</script>

<template>
    <!-- Entity deck card -->
    <div v-if="kind === 'entity'" :style="style" class="card-frame">
        <div class="card-head" :style="headStyle">
            <div class="card-omen" :class="{ italic: card.omen_is_x }">{{ card.omen_label ?? card.omen_cost }}</div>
            <div class="card-title">{{ card.name || 'Untitled card' }}</div>
        </div>

        <div class="card-body">
            <div
                v-for="(face, index) in faces"
                :key="face.half ?? index"
                class="card-half"
                :class="[
                    { 'border-t border-dashed border-stone-400': index > 0 },
                    highlight && face.half === highlight ? 'card-half-resolved' : '',
                ]"
            >
                <div class="card-type" :style="typeStyle(face)">
                    <Icon v-if="face.type_icon" :name="face.type_icon" /> {{ face.type_name || 'No type' }}
                </div>
                <div class="card-effect" v-html="render(face.text)" />
            </div>

            <div v-if="card.is_placeholder" class="placeholder-flag">placeholder</div>
        </div>

        <div v-if="traits.length || card.added_by_beat || card.set_icon" class="card-foot">
            <span v-for="trait in traits" :key="trait" class="card-trait">{{ trait }}</span>
            <span v-if="card.added_by_beat" class="ml-auto text-amber-800">Beat {{ card.added_by_beat.order }}</span>
            <span v-if="card.set_icon" class="card-set-icon" :class="{ 'ml-auto': !card.added_by_beat }">{{ card.set_icon }}</span>
        </div>

        <!-- Points at the top or bottom half of the card to its right. -->
        <div class="arrow-edge" :class="card.arrow === 'bottom' ? 'arrow-bottom' : 'arrow-top'">▶</div>

    </div>

    <!-- Entity board card -->
    <div v-else-if="kind === 'board'" :style="style" class="card-frame">
        <div class="card-head" style="background: #14532d">
            <div class="card-title">{{ card.name || 'Untitled' }}</div>
            <div v-if="card.health" class="card-health">{{ card.health }}<Icon name="health" class="pip-mark" /></div>
        </div>

        <div class="card-body">
            <div class="card-half">
                <div class="card-type"><Icon name="board" /> Board</div>
                <div class="card-effect" v-html="render(card.text)" />
            </div>
        </div>

        <div v-if="traits.length || card.added_by_beat || card.set_icon" class="card-foot">
            <span v-for="trait in traits" :key="trait" class="card-trait">{{ trait }}</span>
            <span v-if="card.added_by_beat" class="ml-auto text-amber-800">Beat {{ card.added_by_beat.order }}</span>
            <span v-if="card.set_icon" class="card-set-icon" :class="{ 'ml-auto': !card.added_by_beat }">{{ card.set_icon }}</span>
        </div>
    </div>

    <!-- Player deck card -->
    <div v-else-if="kind === 'player'" :style="style" class="card-frame">
        <div class="card-head" :style="headStyle">
            <div class="card-omen" :style="chipStyle">{{ card.gold_cost ?? 0 }}<Icon name="gold" class="pip-mark" /></div>
            <!-- Both economy numbers sit together on the left, which also keeps
                 the top-right corner clear for the placeholder flag. -->
            <div v-if="card.omen_icons" class="card-omen-pips">
                <Icon v-for="pip in card.omen_icons" :key="pip" name="omen" />
            </div>
            <div class="card-title">{{ card.name || 'Untitled card' }}</div>
            <!-- The head's right corner, where a board card carries health. A
                 Hireling has none: what it has is a term. -->
            <div v-if="isHireling" class="card-uses">
                {{ card.uses ?? '?' }}<Icon name="uses" class="pip-mark" />
            </div>
        </div>

        <div class="card-body">
            <div class="card-half">
                <div class="card-type"><Icon :name="card.type" /> {{ typeNames[card.type] ?? card.type }}</div>
                <div class="card-effect" v-html="render(card.text)" />
            </div>

            <div v-if="card.is_placeholder" class="placeholder-flag">placeholder</div>
        </div>

        <div class="card-foot">
            <span v-for="trait in traits" :key="trait" class="card-trait">{{ trait }}</span>
            <span v-if="card.shop_cost !== null && card.shop_cost !== undefined" class="card-shop-cost">
                shop {{ card.shop_cost }}<Icon name="gold" />
            </span>
            <span v-if="isHireling" class="card-sacrifice">
                <Icon name="sacrifice" /> {{ card.sacrifice_value ?? '?' }}
            </span>
            <span v-if="cornerNote" class="ml-auto text-stone-500">
                <Icon :name="cornerIcon" /> {{ cornerNote }}
            </span>
            <span v-if="domainBadge" class="card-set-icon" :class="{ 'ml-auto': !cornerNote }">{{ domainBadge }}</span>
        </div>

    </div>

    <!-- Character card -->
    <div v-else-if="kind === 'character'" :style="style" class="card-frame">
        <div class="card-head" :style="headStyle">
            <div class="card-title">{{ card.name || 'Unnamed character' }}</div>
            <!-- The number, or the designer's equation counting the players.
                 Mirrors the character branch of print/partials/card.blade.php. -->
            <div class="card-health" :class="{ scaled: card.health_equation }">
                <span v-html="renderScaled(card.health, card.health_equation)" /><Icon name="health" class="pip-mark" />
            </div>
        </div>

        <p v-if="card.identity" class="card-flavour">{{ card.identity }}</p>

        <div class="card-body">
            <div class="card-half">
                <div class="card-type">{{ card.ability_name || 'Ability' }}</div>
                <div class="card-effect" v-html="render(card.ability_text)" />
            </div>

            <div v-if="card.is_placeholder" class="placeholder-flag">placeholder</div>
        </div>

        <div class="card-foot">
            <span class="card-trait"><Icon name="hand" /> <span v-html="renderScaled(card.hand_size, card.hand_size_equation)" /></span>
            <span class="card-trait"><span v-html="renderScaled(card.gold_per_round, card.gold_per_round_equation)" /><Icon name="gold" class="pip-mark" /> a round</span>
            <span v-if="!card.title" class="ml-auto text-stone-500">name and story not written</span>
        </div>

    </div>

    <!-- Town card -->
    <div v-else-if="kind === 'town'" :style="style" class="card-frame">
        <div class="card-head" style="background: #854d0e">
            <div v-if="card.gold_cost !== null && card.gold_cost !== undefined" class="card-omen" style="background: #a16207">
                {{ card.gold_cost }}<Icon name="gold" class="pip-mark" />
            </div>
            <div class="card-title">{{ card.name || 'Untitled district' }}</div>
            <!-- The corner a board card puts health in. A district has none;
                 what it has is the omen taking the action adds. -->
            <div class="card-health" style="background: #78350f">+{{ card.omen }}<Icon name="omen" class="pip-mark" /></div>
        </div>

        <div class="card-body">
            <div class="card-half">
                <div class="card-type"><Icon name="town" /> Town</div>
                <div class="card-effect" v-html="render(card.effect)" />
                <div v-if="card.note" class="town-note" v-html="render(card.note)" />
            </div>
        </div>
    </div>

    <!-- Setup card -->
    <div v-else-if="kind === 'setup'" :style="style" class="card-frame">
        <div class="card-head" style="background: #134e4a">
            <!-- The Dread the dial starts on, in the corner a deck card puts
                 its omen cost. It is the one number setup has to get right. -->
            <div class="card-omen" :class="{ scaled: card.starting_dread_equation }" style="background: #0f766e">
                <span v-html="renderScaled(card.starting_dread, card.starting_dread_equation)" /><Icon name="dread" class="pip-mark" />
            </div>
            <div class="card-title">{{ card.name || 'Untitled scenario' }}</div>
        </div>

        <div class="card-body overflow-hidden">
            <div class="card-half">
                <div class="card-type"><Icon name="setup" /> Setup</div>
                <!-- One step per line, as the designer typed them. The
                     splitting is Scenario::setupSteps() server side, so this
                     and the print sheet number the same things. -->
                <ol class="card-effect setup-steps">
                    <li v-for="(step, index) in card.steps ?? []" :key="index" v-html="render(step)" />
                </ol>
            </div>
        </div>

        <div class="card-foot">
            <span class="card-trait">
                {{ card.modules_required }} {{ card.modules_required === 1 ? 'module' : 'modules' }}
            </span>
        </div>
    </div>

    <!-- Story beat card -->
    <div v-else :style="style" class="card-frame">
        <div class="card-head" style="background: #451a03">
            <div class="card-omen" style="background: #78350f">{{ card.order }}</div>
            <div class="card-title">{{ card.name || 'Untitled beat' }}</div>
            <!-- An equation prints as written; a plain number keeps its sign.
                 A beat that changes nothing prints nothing. -->
            <div v-if="card.dread_change_equation" class="card-health scaled">
                ▲<span v-html="renderScaled(card.dread_change, card.dread_change_equation)" />
            </div>
            <div v-else-if="card.dread_change" class="card-health">▲{{ card.dread_change > 0 ? '+' : '' }}{{ card.dread_change }}</div>
        </div>

        <p v-if="card.flavour" class="card-flavour">{{ card.flavour }}</p>

        <div class="card-body overflow-hidden">
            <div v-if="card.on_reach" class="beat-block">
                <div class="beat-label">When reached</div>
                <div v-html="render(card.on_reach)" />
            </div>
            <div v-if="card.advance" class="beat-block">
                <div class="beat-label">Advances when</div>
                <div v-html="render(card.advance)" />
            </div>
            <div v-if="card.on_advance" class="beat-block">
                <div class="beat-label">On advancing</div>
                <div v-html="render(card.on_advance)" />
            </div>
        </div>
    </div>
</template>

<style scoped>
.card-frame {
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 0.4em;
    border: 0.05em solid #1c1917;
    background: #fdfcf9;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.18);
}
.card-head {
    display: flex;
    min-height: 1.8em;
    align-items: stretch;
    border-bottom: 0.05em solid #1c1917;
    background: #1c1917;
    color: #fdfcf9;
}
.card-omen {
    display: flex;
    flex: 0 0 1.8em;
    align-items: center;
    justify-content: center;
    border-right: 0.05em solid #fdfcf9;
    background: #3f3f46;
    /* The chips in the head carry their own dark backgrounds, so they keep the
       light ink even when a pale card type flips the head band's. Mirrored by
       .omen, .health and .uses in the print sheet's inline CSS. */
    color: #fdfcf9;
    font-size: 0.9em;
    font-weight: 700;
}
/*
 * A chip holding an equation rather than a number: "1 + 1 [icon]" needs the room
 * a single digit does not. The chip widens and the type drops rather than the
 * equation wrapping into the card name beside it. Mirrored by .omen.scaled and
 * .health.scaled in the print sheet's inline CSS.
 */
.card-omen.scaled,
.card-health.scaled {
    flex: 0 0 auto;
    min-width: 1.8em;
    padding: 0 0.35em;
    font-size: 0.72em;
    white-space: nowrap;
}
.card-title {
    display: flex;
    flex: 1;
    align-items: center;
    padding: 0.15em 0.5em;
    font-family: Newsreader, Georgia, serif;
    font-size: 0.75em;
    font-weight: 600;
    line-height: 1.15;
}
.card-health {
    display: flex;
    flex: 0 0 2em;
    align-items: center;
    justify-content: center;
    border-left: 0.05em solid #fdfcf9;
    background: #7f1d1d;
    color: #fdfcf9;
    padding: 0 0.1em;
    text-align: center;
    font-size: 0.62em;
    font-weight: 700;
    line-height: 1.05;
}
/*
 * Mirrors .arrow-edge in resources/views/print/sheet.blade.php. The arrow is on
 * the right edge at a quarter or three quarters of the card height, so it lines
 * up with the halves of the split card to its right.
 */
.arrow-edge {
    position: absolute;
    right: 0;
    transform: translate(35%, -50%);
    font-size: 0.85em;
    line-height: 1;
    color: #1c1917;
    text-shadow: 0 0 0.15em #fdfcf9, 0 0 0.15em #fdfcf9;
}
.arrow-top { top: 25%; }
.arrow-bottom { top: 75%; }
.card-omen-pips {
    display: flex;
    flex: 0 0 auto;
    align-items: center;
    gap: 0.1em;
    padding-left: 0.45em;
    font-size: 0.6em;
}
.pip-mark {
    margin-left: 0.15em;
    font-size: 0.8em;
}
.card-shop-cost {
    border-radius: 0.2em;
    background: #fef3c7;
    padding: 0.05em 0.25em;
    color: #78350f;
}
/* A Hireling's two numbers. Mirrored by .uses and .sacrifice in the print
   sheet's inline CSS — change one and change the other. */
.card-uses {
    display: flex;
    flex: 0 0 2em;
    align-items: center;
    justify-content: center;
    border-left: 0.05em solid #fdfcf9;
    background: #115e59;
    color: #fdfcf9;
    padding: 0 0.1em;
    text-align: center;
    font-size: 0.62em;
    font-weight: 700;
    line-height: 1.05;
}
.card-sacrifice {
    display: inline-flex;
    align-items: center;
    gap: 0.15em;
    border-radius: 0.2em;
    background: #fee2e2;
    padding: 0.05em 0.25em;
    color: #7f1d1d;
}
.card-set-icon {
    border-radius: 0.2em;
    border: 0.05em solid #1c1917;
    padding: 0.05em 0.25em;
    font-weight: 700;
    letter-spacing: 0.08em;
}
/* The card body is the positioning context for the placeholder flag, so the
   flag never lands on a health badge or an omen pip in the head. */
.card-body {
    position: relative;
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
}
.card-half {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    padding: 0.4em 0.55em;
}
.card-flavour {
    padding: 0.5em 0.6em 0;
    font-family: Newsreader, Georgia, serif;
    font-size: 0.62em;
    font-style: italic;
    line-height: 1.375;
    color: #57534e;
}
/* The half a split card resolves. */
.card-half-resolved {
    background: rgb(251 191 36 / 0.18);
    box-shadow: inset 0 0 0 0.1em #d97706;
}
.card-type {
    display: flex;
    align-items: center;
    gap: 0.35em;
    margin-bottom: 0.2em;
    font-size: 0.45em;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #78716c;
}
.card-effect {
    flex: 1;
    font-size: 0.6em;
    line-height: 1.3;
    overflow: hidden;
}
/* The setup card's numbered steps. Mirrors .setup-steps in
   resources/views/print/sheet.blade.php. */
.setup-steps {
    margin: 0;
    padding-left: 1.1em;
    list-style: decimal;
}
.setup-steps li {
    margin-bottom: 0.25em;
    padding-left: 0.1em;
}

/* A district's own caveat, under its effect. Mirrors .town-note in
   resources/views/print/sheet.blade.php. */
.town-note {
    margin-top: 0.25em;
    font-size: 0.52em;
    font-style: italic;
    line-height: 1.25;
    color: #57534e;
}
.card-foot {
    display: flex;
    min-height: 1.1em;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25em;
    border-top: 0.05em solid #d6d3d1;
    padding: 0.2em 0.5em;
    font-size: 0.45em;
    font-weight: 600;
}
.card-trait {
    border-radius: 0.2em;
    border: 0.05em solid #78716c;
    padding: 0.05em 0.25em;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #57534e;
}
.beat-block {
    padding: 0.3em 0.55em 0;
    font-size: 0.58em;
    line-height: 1.25;
}
.beat-label {
    font-size: 0.78em;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #92400e;
}
.placeholder-flag {
    position: absolute;
    right: 0.3em;
    top: 0.3em;
    border-radius: 0.2em;
    background: #fde68a;
    padding: 0.1em 0.3em;
    font-size: 0.4em;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #78350f;
}
</style>
