<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';

const props = defineProps({
    card: { type: Object, required: true },
    kind: { type: String, default: 'entity' },
    width: { type: Number, default: 200 },
    autoIcons: { type: Boolean, default: true },
});

const page = usePage();
const markupOptions = computed(() => ({
    icons: page.props.markup?.icons ?? {},
    config: page.props.markup?.config ?? {},
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
</script>

<template>
    <!-- Entity deck card -->
    <div v-if="kind === 'entity'" :style="style" class="card-frame">
        <div class="card-head">
            <div class="card-omen" :class="{ italic: card.omen_is_x }">{{ card.omen_label ?? card.omen_cost }}</div>
            <div class="card-title">{{ card.name || 'Untitled card' }}</div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <div
                v-for="(face, index) in faces"
                :key="face.half ?? index"
                class="card-half"
                :class="{ 'border-t border-dashed border-stone-400': index > 0 }"
            >
                <div class="card-type">{{ face.type_name || 'No type' }}</div>
                <div class="card-effect" v-html="render(face.text)" />
            </div>
        </div>

        <div v-if="traits.length || card.added_by_beat || card.set_icon" class="card-foot">
            <span v-for="trait in traits" :key="trait" class="card-trait">{{ trait }}</span>
            <span v-if="card.added_by_beat" class="ml-auto text-amber-800">Beat {{ card.added_by_beat.order }}</span>
            <span v-if="card.set_icon" class="card-set-icon" :class="{ 'ml-auto': !card.added_by_beat }">{{ card.set_icon }}</span>
        </div>

        <!-- Points at the top or bottom half of the card to its right. -->
        <div class="arrow-edge" :class="card.arrow === 'bottom' ? 'arrow-bottom' : 'arrow-top'">▶</div>

        <div v-if="card.is_placeholder" class="placeholder-flag">placeholder</div>
    </div>

    <!-- Entity board card -->
    <div v-else-if="kind === 'board'" :style="style" class="card-frame">
        <div class="card-head" style="background: #14532d">
            <div class="card-title">{{ card.name || 'Untitled' }}</div>
            <div v-if="card.health" class="card-health">{{ card.health }}</div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <div class="card-half">
                <div class="card-type">Board</div>
                <div class="card-effect" v-html="render(card.text)" />
            </div>
        </div>

        <div v-if="traits.length || card.added_by_beat || card.set_icon" class="card-foot">
            <span v-for="trait in traits" :key="trait" class="card-trait">{{ trait }}</span>
            <span v-if="card.added_by_beat" class="ml-auto text-amber-800">Beat {{ card.added_by_beat.order }}</span>
            <span v-if="card.set_icon" class="card-set-icon" :class="{ 'ml-auto': !card.added_by_beat }">{{ card.set_icon }}</span>
        </div>
    </div>

    <!-- Story beat card -->
    <div v-else :style="style" class="card-frame">
        <div class="card-head" style="background: #451a03">
            <div class="card-omen" style="background: #78350f">{{ card.order }}</div>
            <div class="card-title">{{ card.name || 'Untitled beat' }}</div>
            <div v-if="card.dread_change" class="card-health">▲{{ card.dread_change > 0 ? '+' : '' }}{{ card.dread_change }}</div>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden">
            <p v-if="card.flavour" class="px-[0.6em] pt-[0.5em] font-serif text-[0.62em] italic leading-snug text-stone-600">
                {{ card.flavour }}
            </p>
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
.card-set-icon {
    border-radius: 0.2em;
    border: 0.05em solid #1c1917;
    padding: 0.05em 0.25em;
    font-weight: 700;
    letter-spacing: 0.08em;
}
.card-half {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    padding: 0.4em 0.55em;
}
.card-type {
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
    right: 0.25em;
    top: 0.25em;
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
