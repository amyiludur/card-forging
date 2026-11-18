<script setup>
import { computed, ref } from 'vue';
import Icon from '../Icon.vue';
import PileStack from './PileStack.vue';
import { useDropZone } from './useTableDrag';

/**
 * The entity's side of the table, down the left edge: the two dials a reveal
 * reads, the draw pile and the discard pile, and the beat the story is on.
 * Every button here names a move the page makes; nothing in the rail changes
 * the session itself. Clicking the draw pile draws one card onto the
 * storyline, and a storyline card dropped on the discard pile is taken off
 * the line.
 */
const props = defineProps({
    omen: { type: Number, default: 0 },
    dread: { type: Number, default: 0 },
    omenNote: { type: String, default: '' },
    startingOmen: { type: Object, required: true },
    startingDread: { type: Object, required: true },
    scenario: { type: Object, required: true },
    players: { type: Number, default: 0 },
    drawCount: { type: Number, default: 0 },
    discardCount: { type: Number, default: 0 },
    revealCount: { type: Number, default: 0 },
    discardTopName: { type: String, default: '' },
    discardTopArrow: { type: String, default: null },
    beat: { type: Object, default: null },
    beatCount: { type: Number, default: 0 },
    beatCounter: { type: Number, default: 0 },
    storyComplete: { type: Boolean, default: false },
    kills: { type: Object, default: () => ({}) },
    beatDread: { type: String, default: '' },
});

const emit = defineEmits(['omen', 'dread', 'draw-one', 'reveal', 'reshuffle', 'advance-beat', 'beat-counter', 'show-beats', 'discard-card', 'peek']);

// How many to look at is the card's number, so it is picked here each time
// rather than being a rule of the table's. Kept between looks for convenience.
const peekCount = ref(3);
const lookAt = computed(() => Math.max(1, Math.min(peekCount.value, props.drawCount)));

const discardZone = useDropZone((p) => p.source === 'line', (p) => emit('discard-card', p.index));

const killList = computed(() => Object.entries(props.kills).filter(([, n]) => n > 0));
const arrowText = computed(() => (props.discardTopArrow === 'top' ? '▲ top' : props.discardTopArrow === 'bottom' ? '▼ bottom' : ''));
const people = computed(() => `${props.players} ${props.players === 1 ? 'player' : 'players'}`);
</script>

<template>
    <aside class="table-scroll flex w-56 shrink-0 flex-col gap-2 border-r border-white/10 bg-black/20 p-2 text-xs">
        <!-- The two dials the reveal reads -->
        <div class="table-panel p-2">
            <div class="flex items-center justify-between">
                <span class="table-label flex items-center gap-1" :title="omenNote"><Icon name="omen" /> Omen pool</span>
                <span class="text-[10px] text-stone-500" :title="omenNote">ⓘ</span>
            </div>
            <div class="mt-1 flex items-center justify-center gap-3">
                <button type="button" class="dial-btn" aria-label="Remove an omen" @click="emit('omen', -1)">−</button>
                <span :key="`omen-${omen}`" class="pulse w-12 text-center font-serif text-3xl font-bold tabular-nums text-stone-50">{{ omen }}</span>
                <button type="button" class="dial-btn" aria-label="Add an omen" @click="emit('omen', 1)">+</button>
            </div>
            <p class="mt-1 text-[10px] leading-snug text-stone-400">{{ omenNote }}</p>
            <p v-if="startingOmen.scaled" class="mt-0.5 text-[10px] leading-snug text-stone-400">
                Starts at <span v-html="startingOmen.html" /> = <strong class="text-stone-200">{{ startingOmen.value }}</strong> at {{ people }}.
            </p>

            <div class="mt-2 border-t border-white/10 pt-2">
                <span
                    class="table-label flex items-center gap-1"
                    title="A reveal that brings out fewer than this many cards triggers the scenario's Dread effect."
                ><Icon name="dread" /> Dread X</span>
                <div class="mt-1 flex items-center justify-center gap-3">
                    <button type="button" class="dial-btn" aria-label="Lower Dread" @click="emit('dread', -1)">−</button>
                    <span :key="`dread-${dread}`" class="pulse w-12 text-center font-serif text-3xl font-bold tabular-nums text-red-200">{{ dread }}</span>
                    <button type="button" class="dial-btn" aria-label="Raise Dread" @click="emit('dread', 1)">+</button>
                </div>
                <p class="mt-1 text-[10px] leading-snug text-stone-400">
                    <template v-if="startingDread.scaled">
                        Starts at <span v-html="startingDread.html" /> = <strong class="text-stone-200">{{ startingDread.value }}</strong> at {{ people }}.
                    </template>
                    <template v-else>Started at {{ scenario.starting_dread }}.</template>
                    A reveal of fewer cards than this triggers the Dread effect.
                </p>
                <p
                    v-if="scenario.dread_effect"
                    class="mt-1 line-clamp-3 text-[10px] leading-snug text-stone-300"
                    :title="scenario.dread_effect"
                >{{ scenario.dread_effect }}</p>
            </div>
        </div>

        <!-- The entity deck -->
        <div class="table-panel p-2">
            <div class="flex items-start justify-around">
                <PileStack
                    label="Draw pile"
                    :count="drawCount"
                    tone="entity"
                    :clickable="drawCount > 0"
                    title="Click to draw one card onto the storyline"
                    :sub="`${revealCount} ${revealCount === 1 ? 'reveal' : 'reveals'}`"
                    @open="emit('draw-one')"
                />
                <PileStack
                    label="Discard"
                    :count="discardCount"
                    tone="entity"
                    :clickable="false"
                    :ready="discardZone.ready()"
                    :active="discardZone.over.value"
                    :sub="discardTopName ? `${discardTopName} ${discardTopArrow === 'top' ? '▲' : '▼'}` : ''"
                    :title="discardTopName ? `On top of the discard: ${discardTopName}, pointing ${arrowText} — the next storyline's first card reads that. Drop a storyline card here to take it off the line.` : 'Discard pile · drop a storyline card here to take it off the line'"
                    v-on="discardZone.listeners"
                />
            </div>
            <p class="mt-1 text-center text-[10px] text-stone-500">Click the draw pile to draw one card.</p>
            <p v-if="discardTopName" class="mt-1.5 text-[10px] leading-snug text-stone-400">
                On top: <strong class="text-stone-200">{{ discardTopName }}</strong>, pointing {{ arrowText }} — the next storyline's first card reads that.
            </p>
            <div class="mt-2 grid gap-1">
                <button type="button" class="table-btn" :disabled="drawCount === 0" @click="emit('reveal')">
                    <Icon name="play" /> Reveal against {{ omen }} omen
                </button>
                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        class="table-btn flex-1"
                        :disabled="drawCount === 0"
                        title="Look at the top cards of the entity deck, then put them back in any order, send some to the bottom or discard them"
                        @click="emit('peek', lookAt)"
                    >
                        Look at the top {{ lookAt }}
                    </button>
                    <button type="button" class="table-step" aria-label="Look at one fewer" :disabled="peekCount <= 1" @click="peekCount = Math.max(1, lookAt - 1)">−</button>
                    <button type="button" class="table-step" aria-label="Look at one more" :disabled="peekCount >= drawCount" @click="peekCount = lookAt + 1">+</button>
                </div>
                <button
                    type="button"
                    class="table-btn"
                    :disabled="discardCount === 0"
                    title="An empty draw pile is not resolved automatically. The rules pair this shuffle with Dread +1, but that is a placeholder and open question 10, so the dial is left to you."
                    @click="emit('reshuffle')"
                >
                    <Icon name="shuffle" /> Shuffle the discard pile back in
                </button>
            </div>
            <p class="mt-1 text-[10px] leading-snug text-stone-500">
                The rules pair this shuffle with Dread +1 — a placeholder and open question 10, so the dial is left to you.
            </p>
        </div>

        <!-- The beat the story is on -->
        <div class="table-panel p-2">
            <div class="flex items-center justify-between">
                <span class="table-label">Story beat</span>
                <button v-if="beatCount" type="button" class="text-[10px] text-stone-400 underline decoration-dotted hover:text-stone-200" @click="emit('show-beats')">
                    all {{ beatCount }}
                </button>
            </div>

            <template v-if="beat">
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="rounded bg-amber-800 px-1 text-[10px] font-bold text-amber-50">{{ beat.order }}</span>
                    <span class="font-serif text-sm font-semibold leading-tight text-stone-50">{{ beat.name }}</span>
                </div>
                <p v-if="beatDread" class="mt-0.5 text-[10px] font-semibold text-red-300" v-html="beatDread" />
                <div v-if="beat.advance" class="mt-1">
                    <span class="table-label">Advances when</span>
                    <p class="beat-text text-[11px] leading-snug text-stone-200" v-html="beat.html.advance" />
                </div>
                <div class="mt-1.5 flex items-center gap-1.5 text-[11px] text-stone-300">
                    counters
                    <button type="button" class="table-step" aria-label="Remove a counter from this beat" @click="emit('beat-counter', -1)">−</button>
                    <span class="w-5 text-center font-semibold tabular-nums text-stone-50">{{ beatCounter }}</span>
                    <button type="button" class="table-step" aria-label="Add a counter to this beat" @click="emit('beat-counter', 1)">+</button>
                </div>
            </template>
            <p v-else-if="storyComplete" class="mt-1 text-[11px] text-emerald-300">Every story beat has advanced.</p>
            <p v-else class="mt-1 text-[11px] text-stone-400">No story beats written for this scenario yet.</p>

            <div class="mt-1.5">
                <span class="table-label">Destroyed this beat</span>
                <p v-if="killList.length" class="text-[11px] text-stone-200">
                    <span v-for="([name, n], i) in killList" :key="name">{{ i ? ', ' : '' }}{{ name }} × {{ n }}</span>
                </p>
                <p v-else class="text-[11px] text-stone-500">Nothing yet.</p>
            </div>

            <button v-if="beat" type="button" class="table-btn table-btn-primary mt-2 w-full" @click="emit('advance-beat')">Advance this beat</button>
        </div>
    </aside>
</template>

<style scoped>
.dial-btn {
    display: flex;
    height: 1.75rem;
    width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgba(255, 255, 255, 0.25);
    font-size: 1.1rem;
    line-height: 1;
    color: rgb(231 229 228);
}
.dial-btn:hover {
    background: rgba(255, 255, 255, 0.15);
}
/* Beat text is rendered markup: its icons and keywords inherit the colour. */
.beat-text :deep(.markup-keyword-placeholder) {
    color: rgb(252 211 77);
}
</style>
