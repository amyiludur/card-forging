<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import TableCard from './TableCard.vue';

/**
 * Looking at the top of the entity deck: the first `count` cards laid out
 * face up, to put back in any order, send to the bottom or discard. Nothing
 * is moved until Done, so the cards can be arranged and rearranged and the
 * whole look is one move — one log line, one undo. Cancel (or Escape) puts
 * everything back as it was.
 *
 * What a card lets you look at, how many and what you may do with them is the
 * card's to say and the table's to follow: this only does the moving. Every
 * card here is always one of the top cards of the draw pile, in pile order to
 * begin with, which is why "Look at one more" can simply take the next one.
 */
const props = defineProps({
    drawPile: { type: Array, required: true },
    cardByKey: { type: Object, required: true },
    count: { type: Number, default: 3 },
    width: { type: Number, default: 290 },
});

const emit = defineEmits(['apply', 'close', 'zoom']);

const FATES = [
    { key: 'top', title: 'Back on top', note: 'leftmost is drawn first' },
    { key: 'bottom', title: 'To the bottom', note: 'rightmost ends up the very bottom card' },
    { key: 'discard', title: 'Discard', note: 'rightmost lands on top of the discard pile — the next storyline reads its arrow' },
];

let uid = 0;
const take = (key) => ({ uid: ++uid, key, fate: 'top' });
const items = ref(props.drawPile.slice(0, props.count).map(take));

const cardOf = (item) => props.cardByKey[item.key] ?? {};
const inFate = (fate) => items.value.filter((item) => item.fate === fate);
const looked = computed(() => items.value.length);
const changed = computed(() =>
    items.value.some((item, i) => item.fate !== 'top' || item.key !== props.drawPile[i]),
);

const lookAtOneMore = () => {
    const next = props.drawPile[items.value.length];
    if (next !== undefined) items.value.push(take(next));
};

/** Swap a card with its neighbour among the cards headed the same way. */
const shift = (item, delta) => {
    const row = inFate(item.fate);
    const other = row[row.indexOf(item) + delta];
    if (!other) return;

    const list = items.value;
    const a = list.indexOf(item);
    const b = list.indexOf(other);
    [list[a], list[b]] = [list[b], list[a]];
};

// A card sent elsewhere joins the end of that row.
const send = (item, fate) => {
    if (item.fate === fate) return;
    items.value.splice(items.value.indexOf(item), 1);
    item.fate = fate;
    items.value.push(item);
};

const done = () => {
    if (!changed.value) return emit('close');

    emit('apply', {
        looked: looked.value,
        top: inFate('top').map((item) => item.key),
        bottom: inFate('bottom').map((item) => item.key),
        discard: inFate('discard').map((item) => item.key),
    });
};

const zoomIn = (item) => emit('zoom', items.value.map(cardOf), items.value.indexOf(item));

const menuFor = (item) => {
    const row = inFate(item.fate);
    const at = row.indexOf(item);

    return {
        title: cardOf(item).name,
        items: [
            { label: 'Move left', disabled: at === 0, action: () => shift(item, -1) },
            { label: 'Move right', disabled: at === row.length - 1, action: () => shift(item, 1) },
            { divider: true },
            ...FATES.map((fate) => ({ label: fate.title, disabled: item.fate === fate.key, action: () => send(item, fate.key) })),
            { divider: true },
            { label: 'View full size', action: () => zoomIn(item) },
        ],
    };
};

const onKeydown = (event) => {
    // The card zoom sits over this and closes on Escape itself.
    if (event.key === 'Escape' && !document.querySelector('[aria-label="Card at full size"]')) emit('close');
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-6"
            role="dialog"
            aria-modal="true"
            aria-label="Top of the entity deck"
            @click.self="emit('close')"
        >
            <div class="table-felt flex max-h-full w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-white/15 shadow-2xl">
                <div class="flex flex-wrap items-center gap-3 border-b border-white/10 px-4 py-2">
                    <h2 class="font-serif text-lg font-semibold text-stone-50">Top {{ looked }} of the entity deck</h2>
                    <span class="text-xs tabular-nums text-stone-400">{{ drawPile.length - looked }} more under them</span>
                    <button type="button" class="table-btn" :disabled="looked >= drawPile.length" @click="lookAtOneMore">
                        Look at one more
                    </button>
                    <span class="text-xs text-stone-500">Click a card to read it, right-click for every move. Nothing moves until Done.</span>
                    <div class="ml-auto flex gap-2">
                        <button type="button" class="table-btn" @click="emit('close')">Cancel</button>
                        <button type="button" class="table-btn table-btn-primary" @click="done">Done</button>
                    </div>
                </div>

                <div class="table-scroll min-h-0 flex-1 space-y-4 p-4">
                    <section v-for="fate in FATES" :key="fate.key">
                        <h3 class="table-label">
                            {{ fate.title }} · {{ inFate(fate.key).length }}
                            <span class="font-normal normal-case tracking-normal text-stone-500">— {{ fate.note }}</span>
                        </h3>
                        <div v-if="inFate(fate.key).length" class="mt-2 flex flex-wrap gap-4">
                            <TableCard
                                v-for="(item, index) in inFate(fate.key)"
                                :key="item.uid"
                                :card="cardOf(item)"
                                kind="entity"
                                :width="width"
                                cursor="zoom-in"
                                :activate="() => zoomIn(item)"
                                :menu="() => menuFor(item)"
                                :label="`${cardOf(item).name}: click to read it, right-click to move it`"
                            >
                                <div class="mt-1 flex items-center justify-center gap-1 text-[11px]">
                                    <button
                                        type="button"
                                        class="table-step"
                                        :disabled="index === 0"
                                        :aria-label="`Move ${cardOf(item).name} left`"
                                        @click="shift(item, -1)"
                                    >◀</button>
                                    <button
                                        v-for="other in FATES.filter((f) => f.key !== fate.key)"
                                        :key="other.key"
                                        type="button"
                                        class="table-btn py-0 text-[11px]"
                                        @click="send(item, other.key)"
                                    >{{ other.title }}</button>
                                    <button
                                        type="button"
                                        class="table-step"
                                        :disabled="index === inFate(fate.key).length - 1"
                                        :aria-label="`Move ${cardOf(item).name} right`"
                                        @click="shift(item, 1)"
                                    >▶</button>
                                </div>
                            </TableCard>
                        </div>
                        <p v-else class="mt-1 text-xs text-stone-500">None.</p>
                    </section>
                </div>
            </div>
        </div>
    </Teleport>
</template>
