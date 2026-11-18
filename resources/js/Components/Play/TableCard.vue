<script setup>
import { computed, ref } from 'vue';
import CardPreview from '../CardPreview.vue';
import { hideHover, openMenu, showHover } from './tableOverlays';
import { endDrag, startDrag } from './useTableDrag';
import { LANDSCAPE, PORTRAIT } from './useCardFit';

/**
 * One card lying on the playtest table. Every card on it — in a hand, in
 * play, in the shop, on the storyline or the board, in the town — is one of
 * these, so they all answer the same way:
 *
 * - **click** does the one thing the rules make obvious for that card (play
 *   it, buy it, exhaust it, tick it off), through `activate`. When that says
 *   `false` — not enough gold — the card shakes rather than doing nothing.
 * - **right-click** opens the menu `menu()` builds: every other move.
 * - **drag** picks it up with the `drag` payload, if it has one.
 * - **resting the pointer** on it shows it at reading size beside it.
 *
 * The face is CardPreview, untouched: this only decides how a card lies on
 * the felt — turned for exhausted, faded for resolved, face down.
 */
const props = defineProps({
    card: { type: Object, default: () => ({}) },
    kind: { type: String, default: 'player' },
    width: { type: Number, default: 150 },
    highlight: { type: String, default: null },
    faceDown: { type: Boolean, default: false },
    exhausted: { type: Boolean, default: false },
    // In play but not counting yet — the Dormant keyword. Greyed, not turned.
    dormant: { type: Boolean, default: false },
    dim: { type: Boolean, default: false },
    // A soft glow: this card can be played or bought right now.
    glow: { type: Boolean, default: false },
    label: { type: String, default: '' },
    activate: { type: Function, default: null },
    menu: { type: Function, default: null },
    drag: { type: Object, default: null },
    // The pointer over it. A card whose click acts gets a hand, not the
    // magnifier every other card in the app shows: that one promises a zoom.
    cursor: { type: String, default: 'pointer' },
});

const height = computed(() => Math.round(props.width * (props.kind === 'entity' ? LANDSCAPE : PORTRAIT)));

const shaking = ref(false);
const shake = () => {
    shaking.value = false;
    requestAnimationFrame(() => {
        shaking.value = true;
        setTimeout(() => (shaking.value = false), 400);
    });
};

const button = ref(null);

// A brief flash on a click that did something, so a move that changes only a
// small number — a damage, a tick — is still seen to have happened.
const flashing = ref(false);
const flash = () => {
    flashing.value = false;
    requestAnimationFrame(() => {
        flashing.value = true;
        setTimeout(() => (flashing.value = false), 300);
    });
};

const onClick = (event) => {
    hideHover();
    if (!props.activate) return;
    if (props.activate(event) === false) shake();
    else flash();
};

const onMenu = (event) => {
    if (!props.menu) return;
    const { title, items } = props.menu();
    openMenu(event, title ?? props.card?.name ?? '', items ?? []);
};

const onEnter = () => {
    if (!props.faceDown) showHover(button.value, { card: props.card, kind: props.kind, highlight: props.highlight });
};

const onDragStart = (event) => {
    if (!props.drag) return event.preventDefault();
    startDrag(event, props.drag);
};
</script>

<template>
    <div class="table-card relative shrink-0" :class="[shaking ? 'tc-shake' : '', flashing ? 'tc-flash' : '']" :style="{ width: `${width}px` }">
        <button
            ref="button"
            type="button"
            class="card-button block w-full rounded-lg transition duration-200 ease-out"
            :class="[
                exhausted ? 'tc-exhausted' : '',
                dormant ? 'tc-dormant' : '',
                dim ? 'opacity-45 saturate-50' : '',
                glow ? 'tc-glow' : '',
                drag ? 'cursor-grab active:cursor-grabbing' : `tc-cursor-${cursor}`,
            ]"
            :aria-label="label || card?.name || 'card'"
            :draggable="!!drag"
            @click="onClick"
            @contextmenu="onMenu"
            @mouseenter="onEnter"
            @mouseleave="hideHover"
            @dragstart="onDragStart"
            @dragend="endDrag"
        >
            <div
                v-if="faceDown"
                class="flex items-center justify-center rounded-lg border-2 border-stone-600 bg-stone-800 text-center font-serif text-xs italic text-stone-400"
                :style="{ width: `${width}px`, height: `${height}px` }"
            >
                Face down
            </div>
            <CardPreview v-else :card="card" :kind="kind" :width="width" :highlight="highlight" />
        </button>

        <!-- Badges over the card: damage, counters, a tick. Each takes the pointer only if it asks to. -->
        <div class="pointer-events-none absolute inset-x-0 top-0" :style="{ height: `${height}px` }">
            <slot name="overlay" />
        </div>

        <slot />
    </div>
</template>

<style scoped>
.tc-exhausted {
    transform: rotate(7deg) scale(0.93);
    filter: grayscale(0.7) brightness(0.8);
}
.tc-dormant {
    filter: grayscale(0.6) opacity(0.7);
}
/* Both at once: one filter wins in CSS, so the pair is written out. */
.tc-exhausted.tc-dormant {
    filter: grayscale(0.8) brightness(0.8) opacity(0.7);
}
.tc-glow {
    box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.55), 0 0 14px rgba(251, 191, 36, 0.35);
}
/* Overrides .card-button's cursor-zoom-in, which belongs to the rest of the app. */
.tc-cursor-pointer {
    cursor: pointer;
}
.tc-cursor-zoom-in {
    cursor: zoom-in;
}
.tc-flash::after {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 0.5rem;
    pointer-events: none;
    animation: tc-flash 0.3s ease-out;
}
@keyframes tc-flash {
    from { box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.9), inset 0 0 0 999px rgba(251, 191, 36, 0.18); }
    to { box-shadow: 0 0 0 3px rgba(251, 191, 36, 0), inset 0 0 0 999px rgba(251, 191, 36, 0); }
}
.tc-shake {
    animation: tc-shake 0.35s ease-in-out;
}
@keyframes tc-shake {
    0%, 100% { transform: translateX(0); }
    20% { transform: translateX(-5px); }
    40% { transform: translateX(5px); }
    60% { transform: translateX(-3px); }
    80% { transform: translateX(3px); }
}
</style>
