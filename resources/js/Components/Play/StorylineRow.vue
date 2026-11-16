<script setup>
import { computed, ref } from 'vue';
import TableCard from './TableCard.vue';
import { LANDSCAPE, useCardFit } from './useCardFit';
import { describeX } from '../../omenReveal';

/**
 * The storyline as one row across the table, and above it what the last
 * reveal reported. Every move here is the page's: the arrow chain lives in
 * Play.vue (the JS half of Storyline::resolve()), so this row only shows the
 * line and says which card was pressed.
 *
 * The reveal reports and never applies, and none of what it reports is
 * dropped here — an X that drained the pool, a Dread check that
 * came up short, a draw pile that ran out — each is a chip in the banner with
 * the open question it sits on in its tooltip.
 */
const props = defineProps({
    line: { type: Array, required: true },
    cardByKey: { type: Object, required: true },
    lastReveal: { type: Object, default: null },
    discardTop: { type: Object, default: null },
    incomingArrow: { type: String, default: 'top' },
    incomingChoice: { type: String, default: 'top' },
    firstCardArrowSource: { type: String, default: 'discard' },
    defaultArrow: { type: String, default: 'top' },
    outgoingArrow: { type: Function, required: true },
    shroudFirst: { type: Boolean, default: false },
    // Names of board cards in play whose text says {shrouded}: reported beside
    // the switch, never acted on.
    boardShrouds: { type: Array, default: () => [] },
});

const emit = defineEmits(['zoom', 'toggle-resolved', 'toggle-flip', 'toggle-face-down', 'discard', 'discard-line', 'set-incoming', 'toggle-shroud-first', 'turn-shrouded-up']);

const shroudedCount = computed(() => props.line.filter((entry) => entry.faceDown).length);

// A Shrouded card's arrow decides the card after it, so showing which half
// that card resolves would give the hidden arrow away.
const arrowHidden = (index) => index > 0 && !!props.line[index - 1]?.faceDown;

const row = ref(null);
// Above the card: the "Reveal 2 · #3" caption. Below: the resolve line. Plus
// the sideways scrollbar.
const width = useCardFit(row, { ratio: LANDSCAPE, chrome: 16 + 18 + 12, min: 120, max: 340 });

const arrowLabel = (arrow) => (arrow === 'top' ? '▲ top' : '▼ bottom');
const newReveal = (entry, index) => entry.step && entry.step !== props.line[index - 1]?.step;

// The cards the last reveal brought out are dealt one after another. Only a
// card that has just arrived plays it, because the row keys cards by
// instance and an element that stays mounted does not animate again.
const dealt = (entry) => !!props.lastReveal && entry.step === props.lastReveal.step;
const dealDelay = (index) => {
    const first = props.line.findIndex((e) => e.step === props.line[index].step);
    return Math.max(0, index - first) * 110;
};

// Click ticks a card off; everything else a storyline card can have done to
// it is here — Redirect, Shrouded, taking it off the line.
const menuFor = (entry, index) => ({
    title: props.cardByKey[entry.key]?.name ?? 'Storyline card',
    items: [
        { label: entry.resolved ? 'Untick' : 'Tick off as resolved', hint: 'click', action: () => emit('toggle-resolved', index) },
        entry.faceDown ? null : {
            label: entry.flipped ? 'Undo the redirect' : 'Redirect',
            hint: `arrow ${props.outgoingArrow(entry) === 'top' ? '▲' : '▼'} → ${props.outgoingArrow(entry) === 'top' ? '▼' : '▲'}`,
            action: () => emit('toggle-flip', index),
        },
        { label: entry.faceDown ? 'Turn face up' : 'Hold face down', hint: entry.faceDown ? '' : 'Shrouded', action: () => emit('toggle-face-down', index) },
        entry.faceDown ? null : { label: 'View full size', action: () => emit('zoom', index) },
        { divider: true },
        { label: 'Take off the storyline', hint: 'to the discard', action: () => emit('discard', index) },
    ],
});
</script>

<template>
    <section class="table-panel flex min-h-0 flex-col px-2 pb-1 pt-1.5">
        <!-- What the last reveal did, reported and never applied -->
        <div class="flex min-h-[1.5rem] flex-wrap items-center gap-x-2 gap-y-1 text-xs">
            <h2
                class="table-label"
                title="Resolved left to right in the entity phase, then discarded in order, so the last card resolved sits on top of the pile. A card can go on its own, which makes its neighbours neighbours, or be held face down."
            >
                Storyline · {{ line.length }}
            </h2>

            <template v-if="lastReveal">
                <span class="text-stone-300">
                    <strong class="text-stone-100">Reveal {{ lastReveal.step }}:</strong>
                    {{ lastReveal.cards }} {{ lastReveal.cards === 1 ? 'card' : 'cards' }},
                    {{ lastReveal.total }} omen of cost against a pool of {{ lastReveal.pool }}
                </span>
                <span
                    v-if="lastReveal.xValue !== null"
                    class="chip bg-white/10 text-stone-200"
                    :title="`The X-cost card drained the rest: ${describeX(lastReveal.xValue, lastReveal.xMax).text}. It ends the reveal and skips the Dread check — open question 12.`"
                >
                    X drained the rest: {{ describeX(lastReveal.xValue, lastReveal.xMax).text }} · skips the Dread check (open question 12)
                </span>
                <span
                    v-if="lastReveal.dreadTriggered"
                    class="chip bg-red-600/40 font-semibold text-red-100"
                    :title="`Fewer than ${lastReveal.dreadX} cards revealed. Apply the scenario's Dread effect yourself — the tool does not, because the effect is still a placeholder.`"
                >
                    Dread: fewer than {{ lastReveal.dreadX }} cards — apply the Dread effect yourself (it is a placeholder)
                </span>
                <span
                    v-if="lastReveal.ranOut"
                    class="chip bg-amber-500/25 font-semibold text-amber-100"
                    :title="`The draw pile ran out with ${lastReveal.unmatched} omen unmatched, which the pool is still holding. Nothing was reshuffled: what an empty deck does is open question 10.`"
                >
                    Draw pile ran out: {{ lastReveal.unmatched }} omen unmatched, still in the pool · nothing reshuffled (open question 10)
                </span>
            </template>

            <!-- The first card's arrow, while there is no first card -->
            <span v-if="line.length === 0" class="flex flex-wrap items-center gap-1.5">
                <span class="text-stone-400">· Arrow carried in</span>
                <template v-if="discardTop">
                    <span class="chip bg-white/10 text-stone-100">{{ arrowLabel(incomingArrow) }}</span>
                    <span class="text-stone-400">from the top of the discard pile — the last card resolved ({{ firstCardArrowSource }}).</span>
                </template>
                <template v-else>
                    <button
                        v-for="arrow in ['top', 'bottom']"
                        :key="arrow"
                        type="button"
                        class="table-btn py-0"
                        :class="incomingChoice === arrow ? 'table-btn-on' : ''"
                        @click="emit('set-incoming', arrow)"
                    >
                        {{ arrowLabel(arrow) }}
                    </button>
                    <span class="text-stone-400">The discard pile is empty, so this falls back to the default arrow ({{ defaultArrow }}) — open question 2.</span>
                </template>
            </span>

            <span class="ml-auto flex flex-wrap items-center gap-1.5">
                <span
                    v-if="boardShrouds.length && !shroudFirst"
                    class="chip bg-sky-500/25 font-semibold text-sky-100"
                    :title="`${boardShrouds.join(', ')} says the first card revealed is Shrouded. The table does not read card text, so switch it on yourself.`"
                >
                    {{ boardShrouds.join(', ') }} is in play · shrouding is off
                </span>
                <span
                    v-if="shroudFirst && !boardShrouds.length"
                    class="chip bg-white/10 text-stone-300"
                    title="Shrouding is on, but no board card in play says Shrouded. Grey Light removes the Mist when beat 4 is reached."
                >
                    Nothing on the board says Shrouded
                </span>
                <button
                    type="button"
                    class="table-btn py-0"
                    :class="shroudFirst ? 'table-btn-on' : ''"
                    title="Shrouded: the first card of each reveal counts its omen cost, then comes out face down, its arrow and effect hidden until it resolves or is lit."
                    @click="emit('toggle-shroud-first')"
                >
                    Shroud first card: {{ shroudFirst ? 'on' : 'off' }}
                </button>
                <button
                    v-if="shroudedCount"
                    type="button"
                    class="table-btn py-0"
                    title="The Watchfire: turn every Shrouded card in the storyline face up."
                    @click="emit('turn-shrouded-up')"
                >
                    Turn Shrouded face up ({{ shroudedCount }})
                </button>
            </span>

            <button type="button" class="table-btn py-0" :disabled="line.length === 0" @click="emit('discard-line')">
                Discard the storyline ({{ line.length }})
            </button>
        </div>

        <div ref="row" class="felt-row min-h-0 flex-1 pt-1">
            <TransitionGroup name="card-move">
                <div
                    v-for="(entry, index) in line"
                    :key="entry.key"
                    class="shrink-0"
                    :class="[newReveal(entry, index) && index > 0 ? 'ml-2 border-l border-white/15 pl-3' : '', dealt(entry) ? 'deal-in' : '']"
                    :style="{ width: `${width}px`, animationDelay: dealt(entry) ? `${dealDelay(index)}ms` : null }"
                >
                    <p class="h-4 truncate text-center text-[10px] leading-4 text-stone-400">
                        <span v-if="newReveal(entry, index)" class="font-semibold text-stone-200">Reveal {{ entry.step }} ·</span>
                        #{{ index + 1 }}
                    </p>

                    <TableCard
                        :card="cardByKey[entry.key]"
                        kind="entity"
                        :width="width"
                        :face-down="entry.faceDown"
                        :dim="entry.resolved"
                        :highlight="entry.resolves !== 'single' && !arrowHidden(index) ? entry.resolves : null"
                        :activate="() => emit('toggle-resolved', index)"
                        :menu="() => menuFor(entry, index)"
                        :drag="{ source: 'line', index }"
                        :label="`${cardByKey[entry.key]?.name || 'untitled card'}: click to ${entry.resolved ? 'untick' : 'tick off as resolved'}`"
                    >
                        <template #overlay>
                            <span v-if="entry.resolved" class="badge absolute right-7 top-1 bg-emerald-600 text-emerald-50">✓ resolved</span>
                            <span v-if="entry.flipped && !entry.faceDown" class="badge absolute bottom-1 right-7 bg-amber-500 text-stone-950">
                                redirected {{ outgoingArrow(entry) === 'top' ? '▲' : '▼' }}
                            </span>
                        </template>
                    </TableCard>

                    <p class="mt-1 truncate text-center text-[11px] leading-tight">
                        <template v-if="!entry.faceDown">
                            <span
                                v-if="entry.resolves !== 'single' && arrowHidden(index)"
                                class="font-semibold text-sky-300"
                                title="The card before this one is Shrouded, so the arrow that decides this card's half is hidden until it resolves or is lit."
                            >resolves ? half · arrow Shrouded</span>
                            <span v-else-if="entry.resolves !== 'single'" class="font-semibold text-amber-300">resolves {{ entry.resolves }} half</span>
                            <span v-else class="text-stone-400">single effect</span>
                            <span
                                v-if="entry.xValue !== null && entry.xValue !== undefined"
                                :class="describeX(entry.xValue, entry.xMax).capped ? 'font-semibold text-amber-200' : 'text-stone-200'"
                            > · {{ describeX(entry.xValue, entry.xMax).text }}</span>
                        </template>
                        <span v-else class="text-sky-300/80">Shrouded · face down</span>
                    </p>
                </div>
            </TransitionGroup>

            <p v-if="!line.length" class="m-auto text-sm text-stone-400">Nothing on the storyline. Build the omen pool, then reveal.</p>
        </div>
    </section>
</template>

<style scoped>
.chip {
    border-radius: 9999px;
    padding: 0 0.5rem;
    line-height: 1.25rem;
}
.badge {
    border-radius: 9999px;
    padding: 0 0.4rem;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.05rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
}
</style>
