<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';
import { STEPS } from '../../goldfish';
import { HOTKEYS } from './useHotkeys';

/**
 * The slim bar across the top of the table: where in the round it is, the one
 * button that does the current step, and the ways out. Every press is the
 * page's to carry out — this only says which. Space presses the Next button
 * from anywhere on the table; `?` lists every key.
 */
const props = defineProps({
    scenario: { type: Object, required: true },
    round: { type: Number, default: 1 },
    step: { type: Number, default: 0 },
    omen: { type: Number, default: 0 },
    config: { type: Object, default: () => ({}) },
    canUndo: { type: Boolean, default: false },
    drawerOpen: { type: Boolean, default: false },
    // The step's work is visibly done (every storyline card ticked off), so
    // the Next button asks to be pressed.
    ready: { type: Boolean, default: false },
    helpOpen: { type: Boolean, default: false },
});

const emit = defineEmits(['jump', 'next', 'undo', 'new-game', 'toggle-drawer', 'toggle-help']);

const current = computed(() => STEPS[props.step] ?? STEPS[0]);

// What the current step does, said once: the Next button's tooltip and the
// line beside it read the same sentence.
const explain = computed(() => {
    switch (current.value.key) {
        case 'reveal': return 'Cards come out until their omen cost meets or exceeds the pool, then the pool empties.';
        case 'actions': return 'Play cards, buy from the shop, use the town, attack the board. Every played card adds its omen.';
        case 'draw': return 'Every seat readies its exhausted cards and draws up to its hand size.';
        case 'gold': return props.config.goldCarriesOver
            ? 'Every seat gains the gold on its character card, on top of what it has.'
            : 'Every seat is topped up to the gold on its character card. Unspent gold does not build up past it.';
        case 'upkeep': return 'Resolve the board\'s "start of the entity phase" effects — they are on the board cards.';
        case 'resolve': return 'Resolve the storyline left to right and tick each card off. Responses can be played as a card would resolve.';
        default: return `${props.config.omenAtEndOfRound ? `+${props.config.omenAtEndOfRound} omen, ` : ''}the storyline is discarded, town uses reset and the first player passes left.`;
    }
});
</script>

<template>
    <header class="flex h-12 shrink-0 items-center gap-3 border-b border-white/10 bg-black/40 px-3">
        <Link :href="`/scenarios/${scenario.slug}`" class="table-btn" :title="`Back to ${scenario.name}`">← Scenario</Link>
        <span class="max-w-[14rem] truncate font-serif text-base font-semibold text-stone-50" :title="scenario.name">{{ scenario.name }}</span>
        <span class="whitespace-nowrap font-serif text-base text-amber-200">Round {{ round }}</span>

        <ol class="flex items-center gap-1 text-[11px]">
            <li v-for="(s, index) in STEPS" :key="s.key" class="flex items-center gap-1">
                <span v-if="index === 4" class="mx-0.5 text-stone-500">|</span>
                <button
                    type="button"
                    class="rounded-full border px-2 py-0.5 transition"
                    :class="index === step
                        ? (s.phase === 'player' ? 'border-amber-500 bg-amber-600 text-amber-50' : 'border-red-500 bg-red-800 text-red-50')
                        : 'border-white/15 bg-white/5 text-stone-400 hover:border-white/40 hover:text-stone-200'"
                    :title="`Jump to ${s.label} without doing it`"
                    @click="emit('jump', index)"
                >
                    <span class="hidden 2xl:inline">{{ s.label }}</span>
                    <span class="2xl:hidden">{{ index === step ? s.label : index + 1 }}</span>
                </button>
            </li>
        </ol>

        <button
            type="button"
            class="table-btn table-btn-primary px-3 py-1 text-sm"
            :class="ready ? 'next-ready' : ''"
            :title="`${explain} (Space)`"
            @click="emit('next')"
        >
            <template v-if="current.key === 'reveal'"><Icon name="play" /> Reveal against {{ omen }} omen</template>
            <template v-else-if="current.key === 'draw'">Ready and draw up</template>
            <template v-else-if="current.key === 'gold'">Gain gold</template>
            <template v-else-if="current.key === 'end'">End the round</template>
            <template v-else>{{ current.label }} done →</template>
            <kbd class="ml-1 rounded border border-stone-900/30 bg-stone-900/10 px-1 text-[10px] font-normal leading-4">Space</kbd>
        </button>

        <p class="min-w-0 flex-1 truncate text-xs text-stone-400" :title="explain">{{ explain }}</p>

        <button type="button" class="table-btn" :disabled="!canUndo" title="Undo (Ctrl+Z)" @click="emit('undo')">↶ Undo</button>
        <button type="button" class="table-btn" @click="emit('new-game')">New game</button>
        <button
            type="button"
            class="table-btn"
            :class="drawerOpen ? 'table-btn-on' : ''"
            :aria-expanded="drawerOpen"
            title="Log, story beats, players, setup and modules"
            @click="emit('toggle-drawer')"
        >
            ☰ Table
        </button>
        <div class="relative">
            <button
                type="button"
                class="table-btn w-7"
                :class="helpOpen ? 'table-btn-on' : ''"
                :aria-expanded="helpOpen"
                title="Keyboard shortcuts (?)"
                @click="emit('toggle-help')"
            >?</button>
            <div
                v-if="helpOpen"
                class="absolute right-0 top-full z-50 mt-1 w-72 rounded-lg border border-white/15 bg-stone-900/95 p-3 text-xs text-stone-200 shadow-2xl backdrop-blur"
            >
                <p class="table-label mb-2">On the table</p>
                <dl class="grid grid-cols-[auto,1fr] gap-x-3 gap-y-1">
                    <template v-for="[key, what] in HOTKEYS" :key="key">
                        <dt><kbd class="rounded border border-white/20 bg-white/10 px-1.5 font-mono text-[11px]">{{ key }}</kbd></dt>
                        <dd class="text-stone-300">{{ what }}</dd>
                    </template>
                </dl>
                <p class="table-label mb-1 mt-3">With the mouse</p>
                <ul class="space-y-0.5 text-stone-300">
                    <li><strong class="text-stone-100">Click</strong> a card to do the obvious thing: play, buy, exhaust, tick off, damage, use.</li>
                    <li><strong class="text-stone-100">Right-click</strong> for everything else.</li>
                    <li><strong class="text-stone-100">Drag</strong> a card to any pile or row; hold Shift to put it under a deck.</li>
                    <li><strong class="text-stone-100">Rest the pointer</strong> on a card to read it.</li>
                </ul>
            </div>
        </div>
    </header>
</template>

<style scoped>
.next-ready {
    animation: next-ready 1.1s ease-in-out infinite;
}
@keyframes next-ready {
    0%, 100% { box-shadow: 0 0 0 0 rgba(251, 191, 36, 0.7); }
    50% { box-shadow: 0 0 0 6px rgba(251, 191, 36, 0); }
}
@media (prefers-reduced-motion: reduce) {
    .next-ready { animation: none; box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.8); }
}
</style>
