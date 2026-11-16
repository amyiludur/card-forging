<script setup>
import TableCard from './TableCard.vue';

/**
 * The town at this table. Each player takes each district once a round; using
 * one adds the district's omen to the pool and costs its gold plus one more
 * for every other town action that player has already used this round, which
 * is how `rules/01-core-rules.md` writes it. What the district then *does* —
 * heal, cleanse, upgrade — is the player's to carry out on their own seat.
 *
 * A district can be closed, for whatever a scenario shuts one with: a Mark,
 * a flood. The table does not know why, only that it cannot be used.
 *
 * Opened as a tray over the seat dock, it acts for the seat that is laid out
 * there — the other seats use the town from their own tab. Clicking a district
 * uses it; closing one is on right-click.
 */
const props = defineProps({
    town: { type: Array, default: () => [] },
    districts: { type: Object, required: true },
    seat: { type: Object, default: null },
    nameOf: { type: Function, required: true },
    table: { type: Object, required: true },
    width: { type: Number, default: 120 },
});

const state = (district) => props.districts[district.id] ?? { closed: false };

const costFor = (district, seat) => Number(district.gold_cost ?? 0) + (seat.townUsed?.length ?? 0);
const used = (district, seat) => (seat.townUsed ?? []).includes(district.id);

const use = (district, seat) => {
    const cost = costFor(district, seat);
    const omen = Number(district.omen ?? props.table.config.omenPerTownAction ?? 0);

    props.table.act(`${props.nameOf(seat)} used the ${district.name} for ${cost} gold, +${omen} omen`, () => {
        seat.gold -= cost;
        seat.townUsed = [...(seat.townUsed ?? []), district.id];
        if (omen) props.table.addOmen(omen);
    });
};

const toggleClosed = (district) =>
    props.table.act(`${district.name} ${state(district).closed ? 'reopened' : 'closed'}`, () => {
        props.districts[district.id] = { ...state(district), closed: !state(district).closed };
    });

const zoomIn = (index) => props.table.zoom(props.town, index, 'town');

/** Why this seat cannot use a district right now, or '' when it can. */
const blocked = (district) => {
    if (!props.seat) return 'nobody seated';
    if (state(district).closed) return 'closed';
    if (used(district, props.seat)) return 'used this round';
    if (props.seat.gold < costFor(district, props.seat)) return `needs ${costFor(district, props.seat)} gold`;
    return '';
};

const tryUse = (district) => {
    if (blocked(district)) return false;
    use(district, props.seat);
    return true;
};

const menuFor = (district, index) => ({
    title: district.name,
    items: [
        {
            label: props.seat ? `Use for ${props.nameOf(props.seat)}` : 'Use',
            hint: blocked(district) || `${costFor(district, props.seat)}g · click`,
            disabled: !!blocked(district),
            action: () => tryUse(district),
        },
        { label: state(district).closed ? 'Reopen' : 'Close (Marked…)', action: () => toggleClosed(district) },
        { label: 'View full size', action: () => zoomIn(index) },
    ],
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col">
        <p class="mb-1 truncate text-[11px] text-stone-400" title="Once per player per round. Cost is the district's gold plus 1 for each other town action that player has used this round. Uses reset at the end of the round.">
            Once per player per round. Cost is the district's gold plus 1 for each other town action that player has used this round.
            Uses reset at the end of the round.
        </p>

        <div v-if="town.length" class="felt-row min-h-0 flex-1">
            <TableCard
                v-for="(district, index) in town"
                :key="district.id"
                :card="district"
                kind="town"
                :width="width"
                :dim="state(district).closed || (seat ? used(district, seat) : false)"
                :glow="!blocked(district)"
                :activate="() => tryUse(district)"
                :menu="() => menuFor(district, index)"
                :label="`Use the ${district.name}`"
            >
                <template #overlay>
                    <span
                        class="badge absolute bottom-1 left-1/2 -translate-x-1/2 whitespace-nowrap"
                        :class="state(district).closed ? 'bg-red-700 text-red-50' : blocked(district) ? 'bg-stone-950/85 text-stone-300' : 'bg-amber-500 text-stone-950'"
                    >
                        <template v-if="state(district).closed">closed</template>
                        <template v-else-if="seat && used(district, seat)">used</template>
                        <template v-else-if="seat">{{ costFor(district, seat) }}g</template>
                    </span>
                </template>
            </TableCard>
        </div>
        <p v-else class="text-sm text-stone-400">No town written for this scenario yet.</p>
    </div>
</template>

<style scoped>
.badge {
    border-radius: 9999px;
    padding: 0 0.45rem;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.15rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
}
</style>
