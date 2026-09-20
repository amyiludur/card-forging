<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
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
    // {dreadRule} is the card's own scenario's rule, so it travels with the
    // card rather than with the page: a list mixing scenarios still gets each
    // card right, and a module card has none.
    dreadRule: props.card.dread_rule ?? null,
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

const faces = computed(() => props.card.faces ?? []);
const traits = computed(() => props.card.traits ?? []);

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
        <div class="card-head">
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
                <div class="card-type"><Icon v-if="face.type" :name="face.type" /> {{ face.type_name || 'No type' }}</div>
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
        <div class="card-head" style="background: #1e3a5f">
            <div class="card-omen" style="background: #334e68">{{ card.gold_cost ?? 0 }}<Icon name="gold" class="pip-mark" /></div>
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
        <div class="card-head" style="background: #3f2b56">
            <div class="card-title">{{ card.name || 'Unnamed character' }}</div>
            <div class="card-health">{{ card.health }}<Icon name="health" class="pip-mark" /></div>
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
            <span class="card-trait"><Icon name="hand" /> {{ card.hand_size }}</span>
            <span class="card-trait">{{ card.gold_per_round }}<Icon name="gold" class="pip-mark" /> a round</span>
            <span v-if="!card.title" class="ml-auto text-stone-500">name and story not written</span>
        </div>

    </div>

    <!-- Story beat card -->
    <div v-else :style="style" class="card-frame">
        <div class="card-head" style="background: #451a03">
            <div class="card-omen" style="background: #78350f">{{ card.order }}</div>
            <div class="card-title">{{ card.name || 'Untitled beat' }}</div>
            <div v-if="card.dread_change" class="card-health">▲{{ card.dread_change > 0 ? '+' : '' }}{{ card.dread_change }}</div>
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
    font-size: 0.9em;
    font-weight: 700;
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
