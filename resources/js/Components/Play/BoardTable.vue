<script setup>
import { computed, ref } from 'vue';
import TableCard from './TableCard.vue';
import Icon from '../Icon.vue';
import { PORTRAIT, useCardFit } from './useCardFit';

/**
 * The entity board as it stands in this game: which board cards are out, the
 * damage on each, and a counter for whatever a card keeps on itself — the
 * March on the Barrows, say. Destroying one counts it towards the beat and
 * touches nothing else — kills do not bleed omen, the designer's call — and
 * anything a card says is the table's to do by hand.
 *
 * Laid out as one row across the middle of the table, only a card tall:
 * clicking a card deals it a damage (Shift-click takes one off), a badge says
 * what it has left, and once the damage reaches its health a Destroy button
 * sits on it. The counter, Remove and the rest are on right-click. What
 * destroyed cards count towards is shown on the beat in the entity rail.
 */
const props = defineProps({
    board: { type: Array, required: true },
    boardCards: { type: Array, default: () => [] },
    kills: { type: Object, default: () => ({}) },
    healthOf: { type: Function, required: true },
    table: { type: Object, required: true },
});

const byId = computed(() => Object.fromEntries(props.boardCards.map((c) => [c.id, c])));
const cardOf = (instance) => byId.value[instance.id] ?? {};

const adjust = (instance, field, delta) =>
    props.table.act(`${cardOf(instance).name} ${field} ${delta > 0 ? '+' : ''}${delta}`, () => {
        instance[field] = Math.max(0, (instance[field] ?? 0) + delta);
    });

const destroy = (instance) => {
    const name = cardOf(instance).name;

    props.table.act(`destroyed ${name}`, () => {
        props.board.splice(props.board.findIndex((c) => c.uid === instance.uid), 1);
        props.kills[name] = (props.kills[name] ?? 0) + 1;
    });
};

// Leaving is not dying: a card an effect removes counts towards no beat.
const remove = (instance) =>
    props.table.act(`${cardOf(instance).name} left the board`, () => {
        props.board.splice(props.board.findIndex((c) => c.uid === instance.uid), 1);
    });

const putting = ref('');
const putOut = () => {
    const card = byId.value[putting.value];
    if (!card) return;

    props.table.act(`put ${card.name} on the board`, () => props.table.placeBoard(card.id));
    putting.value = '';
};

const zoomIn = (index) => props.table.zoom(props.board.map(cardOf), index, 'board');

const hit = (instance, event) => adjust(instance, 'damage', event?.shiftKey ? -1 : 1);
const healthKnown = (instance) => props.healthOf(cardOf(instance)) !== null;
const beaten = (instance) => healthKnown(instance) && (instance.damage ?? 0) >= props.healthOf(cardOf(instance));

const menuFor = (instance, index) => ({
    title: cardOf(instance).name,
    items: [
        { label: 'Damage +1', hint: 'click', action: () => adjust(instance, 'damage', 1) },
        { label: 'Damage −1', hint: 'shift-click', disabled: !instance.damage, action: () => adjust(instance, 'damage', -1) },
        { label: 'Counter +1', hint: `${instance.counter ?? 0} now`, action: () => adjust(instance, 'counter', 1) },
        { label: 'Counter −1', disabled: !instance.counter, action: () => adjust(instance, 'counter', -1) },
        { label: 'View full size', action: () => zoomIn(index) },
        { divider: true },
        { label: 'Destroy', hint: 'counts as a kill', danger: true, action: () => destroy(instance) },
        { label: 'Remove from the board', hint: 'not a kill', action: () => remove(instance) },
    ],
});

const row = ref(null);
// Under each card: the line saying what it has left. Plus the scrollbar.
const width = useCardFit(row, { ratio: PORTRAIT, chrome: 12 + 20, min: 104, max: 220 });
const left = (instance) => Math.max(0, props.healthOf(cardOf(instance)) - (instance.damage ?? 0));
</script>

<template>
    <section class="table-panel flex min-h-0 flex-col px-2 pb-1 pt-1.5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs">
            <h2 class="table-label">Entity board · {{ board.length }}</h2>
            <form class="ml-auto flex items-center gap-1" @submit.prevent="putOut">
                <select v-model="putting" class="table-select w-48" aria-label="Board card to put out">
                    <option value="">Put a card out…</option>
                    <option v-for="card in boardCards" :key="card.id" :value="card.id">
                        {{ card.name }}{{ card.added_by_beat ? ` (beat ${card.added_by_beat.order})` : '' }}
                    </option>
                </select>
                <button type="submit" class="table-btn py-0" :disabled="!putting">Add</button>
            </form>
        </div>

        <div ref="row" class="felt-row min-h-0 flex-1 gap-3 pt-1">
            <TransitionGroup name="card-move">
                <TableCard
                    v-for="(instance, index) in board"
                    :key="instance.uid"
                    :card="cardOf(instance)"
                    kind="board"
                    :width="width"
                    :activate="(event) => hit(instance, event)"
                    :menu="() => menuFor(instance, index)"
                    :label="`${cardOf(instance).name}: click to deal 1 damage, shift-click to take 1 off`"
                >
                    <template #overlay>
                        <button
                            v-if="beaten(instance)"
                            type="button"
                            class="pointer-events-auto absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 whitespace-nowrap rounded-md bg-red-700 px-2 py-1 text-xs font-bold text-red-50 shadow-lg hover:bg-red-600"
                            title="Destroy it"
                            @click="destroy(instance)"
                        >
                            Destroy
                        </button>
                    </template>

                    <!-- What it has left and any counters, under the card so its face stays clear -->
                    <div class="mt-1 flex h-4 items-center justify-center gap-1 text-[11px] leading-4">
                        <span
                            v-if="instance.damage || healthKnown(instance)"
                            :key="`dmg-${instance.damage ?? 0}`"
                            class="badge pulse whitespace-nowrap"
                            :class="beaten(instance) ? 'bg-red-600 text-red-50' : instance.damage ? 'bg-red-900/90 text-red-100' : 'bg-white/10 text-stone-200'"
                        >
                            <template v-if="healthKnown(instance)">{{ left(instance) }} left</template>
                            <template v-if="healthKnown(instance) && instance.damage"> · </template>
                            <template v-if="instance.damage"><Icon name="damage" /> {{ instance.damage }}</template>
                        </span>
                        <span v-if="instance.counter" class="badge bg-white/10 text-stone-100" title="Counters on this card">● {{ instance.counter }}</span>
                    </div>
                </TableCard>
            </TransitionGroup>
            <p v-if="!board.length" class="m-auto text-sm text-stone-400">Nothing on the board.</p>
        </div>
    </section>
</template>

<style scoped>
.badge {
    border-radius: 9999px;
    padding: 0 0.45rem;
    font-size: 11px;
    font-weight: 700;
    line-height: 1rem;
}
</style>
