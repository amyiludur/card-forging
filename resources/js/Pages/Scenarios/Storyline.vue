<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    scenario: { type: Object, required: true },
    chosen: { type: Array, default: () => [] },
    available: { type: Array, default: () => [] },
    size: { type: Number, default: 5 },
    row: { type: Array, default: () => [] },
    incoming: { type: String, default: 'top' },
    outgoing: { type: String, default: 'top' },
    firstCardArrowSource: { type: String, default: 'discard' },
    defaultArrow: { type: String, default: 'top' },
    poolSize: { type: Number, default: 0 },
});

const query = (extra = {}) => ({
    modules: props.chosen,
    size: props.size,
    incoming: props.incoming,
    ...extra,
});

const go = (extra) =>
    router.get(`/scenarios/${props.scenario.slug}/storyline`, query(extra), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });

// A fresh draw: drop the explicit card list and let the server shuffle.
const draw = () => go({ cards: [] });

const currentCards = computed(() => props.row.map((e) => e.card_id));
const flippedPositions = computed(() => props.row.filter((e) => e.flipped).map((e) => e.position));

const toggleFlip = (position) => {
    const flip = flippedPositions.value.includes(position)
        ? flippedPositions.value.filter((p) => p !== position)
        : [...flippedPositions.value, position];

    go({ cards: currentCards.value, flip });
};

const setIncoming = (arrow) => go({ cards: currentCards.value, flip: flippedPositions.value, incoming: arrow });
const setSize = (n) => go({ cards: [], size: n });

const splitCount = computed(() => props.row.filter((e) => e.is_split).length);

// The zoom walks the row itself, so the full-size card keeps the band showing
// which half resolves.
const zoom = useCardZoom();
const rowCards = computed(() => props.row.map((entry) => entry.card));
const zoomEntry = computed(() => (zoom.card ? props.row[zoom.position - 1] ?? null : null));

const zoomCaption = computed(() => {
    const entry = zoomEntry.value;

    if (!entry) {
        return '';
    }

    return `#${entry.position + 1} · ${entry.is_split ? `resolves ${entry.resolves} half` : 'single effect'}`;
});

// firstCardArrowSource is a free-text tunable; spell the known values out.
const firstCardSourceText = computed(() => ({
    'discard-top-else-default': 'the last card resolved (the top of the discard pile)',
    discard: 'the last card resolved (the top of the discard pile)',
    default: 'the default arrow',
    choose: 'the first player\'s choice',
}[props.firstCardArrowSource] ?? props.firstCardArrowSource));
</script>

<template>
    <Head :title="`${scenario.name} — storyline`" />

    <PageHeader
        :title="`${scenario.name} — storyline preview`"
        subtitle="Lay cards out in a row and see which half of each split card resolves."
    >
        <template #actions>
            <Link :href="`/scenarios/${scenario.slug}/deck?${chosen.map((m) => `modules[]=${m}`).join('&')}`" class="btn-ghost">
                Deck assembly
            </Link>
            <Link :href="`/scenarios/${scenario.slug}/play?${chosen.map((m) => `modules[]=${m}`).join('&')}`" class="btn-ghost">
                Playtest
            </Link>
            <button type="button" class="btn-primary" @click="draw">Draw again</button>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span>Drawing from <strong>{{ poolSize }}</strong> printed cards</span>
            <span>{{ splitCount }} split {{ splitCount === 1 ? 'card' : 'cards' }} in this row</span>
            <span class="flex items-center gap-2">
                Row length
                <select :value="size" class="field w-20 py-1" @change="setSize(Number($event.target.value))">
                    <option v-for="n in [2, 3, 4, 5, 6, 7, 8]" :key="n" :value="n">{{ n }}</option>
                </select>
            </span>
        </div>
    </PageHeader>

    <div class="space-y-6 px-6 py-6">
        <div class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-1 font-serif text-base font-semibold">The rule being tested</h2>
            <p class="text-sm leading-relaxed text-stone-700">
                Every card carries an arrow on its right edge pointing at the top or bottom half of the card to its
                right, so <strong>a split card resolves the half indicated by the arrow of the card before it</strong>.
                The first card has nothing before it, so it uses the arrow carried in from
                {{ firstCardSourceText }}, falling back to <strong>{{ defaultArrow }}</strong>.
            </p>
            <p class="mt-1 text-xs text-stone-500">
                Open questions 1 and 2 are still open, so both of those come from the tunable numbers rather than being
                fixed here.
            </p>

            <div class="mt-3 flex flex-wrap items-center gap-3 border-t border-stone-200 pt-3 text-sm">
                <span class="field-micro">Arrow carried in</span>
                <button
                    v-for="arrow in ['top', 'bottom']"
                    :key="arrow"
                    type="button"
                    class="rounded border px-2.5 py-1 text-sm"
                    :class="incoming === arrow ? 'border-stone-900 bg-stone-900 text-stone-50' : 'border-stone-300 bg-white hover:border-stone-500'"
                    @click="setIncoming(arrow)"
                >
                    {{ arrow === 'top' ? '▲ top' : '▼ bottom' }}
                </button>
            </div>
        </div>

        <div v-if="available.length" class="flex flex-wrap items-center gap-2 text-sm">
            <span class="field-micro">Modules in this deck</span>
            <span
                v-for="module in available.filter((m) => chosen.includes(m.slug))"
                :key="module.slug"
                class="rounded border border-stone-400 bg-white px-2 py-0.5"
            >
                {{ module.name }}
            </span>
            <span v-if="!chosen.length" class="text-stone-500">none — base deck only</span>
        </div>

        <!-- The row itself. Cards sit edge to edge so the arrows line up with the halves they point at. -->
        <div class="overflow-x-auto pb-4">
            <div class="flex min-w-max items-start gap-0">
                <div class="mr-3 shrink-0 self-stretch">
                    <p class="field-micro mb-1">Carried in</p>
                    <div class="flex h-[210px] w-16 flex-col justify-center rounded border border-dashed border-stone-400 bg-stone-50 text-center">
                        <span class="text-2xl">{{ incoming === 'top' ? '▲' : '▼' }}</span>
                        <span class="mt-1 text-[11px] text-stone-500">{{ incoming }}</span>
                    </div>
                </div>

                <div v-for="entry in row" :key="entry.position" class="shrink-0">
                    <p class="mb-1 text-center text-[11px] text-stone-500">#{{ entry.position + 1 }}</p>

                    <button
                        type="button"
                        class="card-button"
                        :aria-label="`View ${entry.card.name || 'untitled card'} at full size`"
                        @click="zoom.open(rowCards, entry.position)"
                    >
                        <!-- The highlighted half is the one this card resolves. -->
                        <CardPreview
                            :card="entry.card"
                            kind="entity"
                            :width="150"
                            :highlight="entry.is_split ? entry.resolves : null"
                        />
                    </button>

                    <div class="mt-1.5 w-[150px] text-center text-xs">
                        <p v-if="entry.is_split" class="font-semibold text-amber-800">
                            resolves {{ entry.resolves }} half
                        </p>
                        <p v-else class="text-stone-500">single effect</p>

                        <p class="mt-0.5 text-[11px] text-stone-500">
                            told by {{ entry.position === 0 ? 'what came before' : 'previous card' }}
                            ({{ entry.deciding_arrow }})
                        </p>

                        <button
                            type="button"
                            class="mt-1 rounded border px-1.5 py-0.5 text-[11px]"
                            :class="entry.flipped ? 'border-amber-700 bg-amber-100 text-amber-900' : 'border-stone-300 hover:border-stone-500'"
                            :title="'Redirect: flip this card\'s arrow, which changes the card after it'"
                            @click="toggleFlip(entry.position)"
                        >
                            {{ entry.flipped ? 'redirected' : 'redirect' }} {{ entry.arrow === 'top' ? '▲' : '▼' }}
                        </button>
                    </div>
                </div>

                <div class="ml-3 shrink-0">
                    <p class="field-micro mb-1">Carried out</p>
                    <div class="flex h-[210px] w-16 flex-col justify-center rounded border border-dashed border-stone-400 bg-stone-50 text-center">
                        <span class="text-2xl">{{ outgoing === 'top' ? '▲' : '▼' }}</span>
                        <span class="mt-1 text-[11px] text-stone-500">{{ outgoing }}</span>
                    </div>
                </div>
            </div>
        </div>

        <p class="text-xs leading-relaxed text-stone-600">
            <strong>Redirect</strong> flips the arrow a card shows, which changes the card <em>after</em> it, not the
            card you clicked. Open question 3 is which form Redirect takes (flip, swap or remove); only flip is
            modelled here.
        </p>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :edit-href="`/cards/${zoom.card.id}/edit`"
        :highlight="zoomEntry && zoomEntry.is_split ? zoomEntry.resolves : null"
        :caption="zoomCaption"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>
