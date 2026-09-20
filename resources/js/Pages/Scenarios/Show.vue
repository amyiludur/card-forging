<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';
import BeatRow from '../../Components/BeatRow.vue';
import BoardCardRow from '../../Components/BoardCardRow.vue';
import { onPaper } from '../../colour';
import { renderMarkup } from '../../markup';

const props = defineProps({
    scenario: { type: Object, required: true },
    beats: { type: Array, default: () => [] },
    boardCards: { type: Array, default: () => [] },
    // Null when the designer has not written the setup yet: the tab says so
    // rather than showing a card with nothing on it.
    setupCard: { type: Object, default: null },
    townActions: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
    cardTypes: { type: Array, default: () => [] },
});

const tab = ref('deck');
const tabs = [
    { key: 'deck', label: 'Entity deck' },
    { key: 'beats', label: 'Story beats' },
    { key: 'board', label: 'Entity board' },
    { key: 'town', label: 'Town' },
    { key: 'setup', label: 'Setup' },
];

// Omen curve: how many printed cards sit at each cost. This is the shape the
// reveal step actually cares about.
const omenCurve = computed(() => {
    const buckets = new Map();

    props.cards.forEach((card) => {
        const key = card.omen_is_x ? 'X' : String(card.omen_cost ?? 0);
        buckets.set(key, (buckets.get(key) ?? 0) + card.qty);
    });

    const numeric = [...buckets.keys()].filter((k) => k !== 'X').sort((a, b) => a - b);
    const keys = buckets.has('X') ? [...numeric, 'X'] : numeric;
    const max = Math.max(1, ...buckets.values());

    return keys.map((key) => ({ key, count: buckets.get(key), share: (buckets.get(key) / max) * 100 }));
});

const typeCounts = computed(() =>
    props.cardTypes.map((type) => ({
        ...type,
        count: props.cards.reduce(
            (total, card) => total + (card.faces.some((face) => face.type === type.slug) ? card.qty : 0),
            0
        ),
    }))
);

// v2: every card has an arrow, so what matters is the mix. Open question 6 asks
// whether the designer wants a rule of thumb here, so this reports rather than judges.
const arrowMix = computed(() => {
    const top = props.cards.reduce((n, c) => n + (c.arrow === 'top' ? c.qty : 0), 0);
    const bottom = props.cards.reduce((n, c) => n + (c.arrow === 'bottom' ? c.qty : 0), 0);

    return { top, bottom, total: top + bottom };
});

const newBeat = useForm({ name: '', dread_change: 0, order: null, flavour: '', on_reach: '', advance: '', on_advance: '' });
const newBoardCard = useForm({ name: '', qty: 1, health: '', traits: [], text: '', added_by_beat_id: null, is_placeholder: true });
const newTownAction = useForm({ name: '', effect: '', gold_cost: null, omen: 1, note: '' });
const newCardType = useForm({ name: '', colour: '#7f1d1d', description: '' });

const addBeat = () => newBeat.post(`/scenarios/${props.scenario.slug}/beats`, { preserveScroll: true, onSuccess: () => newBeat.reset() });
const addBoardCard = () => newBoardCard.post(`/scenarios/${props.scenario.slug}/board-cards`, { preserveScroll: true, onSuccess: () => newBoardCard.reset() });
const addTownAction = () => newTownAction.post(`/scenarios/${props.scenario.slug}/town-actions`, { preserveScroll: true, onSuccess: () => newTownAction.reset() });

const addCardType = () => newCardType.post(`/scenarios/${props.scenario.slug}/card-types`, {
    preserveScroll: true,
    onSuccess: () => newCardType.reset(),
});

const deleteTownAction = (action) => {
    if (confirm(`Delete ${action.name}?`)) {
        router.delete(`/town-actions/${action.id}`, { preserveScroll: true });
    }
};

// A setup step renders like any other card text: the same markup, and this
// scenario's own Dread rule behind {dreadRule}.
const page = usePage();

const renderStep = (step) => renderMarkup(step, {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
    dreadRule: props.scenario.dread_effect,
    autoIcons: true,
});

const zoom = useCardZoom();
</script>

<template>
    <Head :title="scenario.name" />

    <PageHeader :title="scenario.name" :subtitle="scenario.overview">
        <template #actions>
            <Link :href="`/scenarios/${scenario.slug}/deck`" class="btn-ghost"><Icon name="cards" /> Deck assembly</Link>
            <Link :href="`/scenarios/${scenario.slug}/storyline`" class="btn-ghost"><Icon name="story" /> Storyline</Link>
            <Link :href="`/scenarios/${scenario.slug}/play`" class="btn-ghost"><Icon name="play" /> Playtest</Link>
            <Link :href="`/cards?scenario=${scenario.slug}`" class="btn-ghost"><Icon name="cards" /> Card list</Link>
            <Link :href="`/print/${scenario.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
            <Link :href="`/scenarios/${scenario.slug}/edit`" class="btn-primary"><Icon name="edit" /> Edit scenario</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span class="rounded bg-stone-200 px-1.5 py-0.5 text-[11px] uppercase tracking-wider">{{ scenario.entity_type }}</span>
            <span><strong>{{ scenario.deck_size }}</strong> cards in the deck</span>
            <span>Starting Dread <strong>{{ scenario.starting_dread }}</strong></span>
            <span v-if="scenario.dread_effect" class="text-stone-600">Dread: {{ scenario.dread_effect }}</span>
            <span>{{ scenario.modules_required }} {{ scenario.modules_required === 1 ? 'module' : 'modules' }} required</span>
        </div>

        <p v-if="scenario.status" class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-900">{{ scenario.status }}</p>

        <nav class="mt-4 flex gap-1 border-b border-stone-300">
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="tab === item.key ? 'border-amber-700 font-semibold text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800'"
                @click="tab = item.key"
            >
                {{ item.label }}
            </button>
        </nav>
    </PageHeader>

    <div class="px-6 py-6">
        <!-- Entity deck -->
        <section v-if="tab === 'deck'" class="space-y-6">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-lg border border-stone-300 bg-white p-4">
                    <h2 class="mb-3 font-serif text-base font-semibold">Omen curve</h2>
                    <div v-for="bucket in omenCurve" :key="bucket.key" class="mb-1.5 flex items-center gap-2 text-sm">
                        <span class="w-6 shrink-0 text-right font-mono font-semibold">{{ bucket.key }}</span>
                        <Icon name="omen" class="text-stone-400" />
                        <span class="h-3.5 rounded-sm bg-stone-800" :style="{ width: `${bucket.share}%` }" />
                        <span class="text-xs text-stone-600">{{ bucket.count }}</span>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">
                        Printed cards at each omen cost. The reveal takes cards until their total cost meets the pool.
                    </p>
                </div>

                <div class="rounded-lg border border-stone-300 bg-white p-4">
                    <h2 class="mb-3 font-serif text-base font-semibold">Types in the deck</h2>
                    <div class="space-y-1.5">
                        <div v-for="type in typeCounts" :key="type.slug" class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="flex items-center gap-2">
                                <!-- The swatch is the head band this type prints; the name
                                     is the type line, which is the same colour taken dark
                                     enough to read on the card's cream body. -->
                                <span
                                    class="h-3.5 w-3.5 shrink-0 rounded-sm border border-stone-300"
                                    :style="{ background: type.colour || '#1c1917' }"
                                />
                                <Icon v-if="type.icon_name" :name="type.icon_name" class="text-stone-400" />
                                <span :style="type.colour ? { color: onPaper(type.colour) } : {}">{{ type.name }}</span>
                                <span v-if="type.scenario_id" class="rounded-sm bg-amber-100 px-1 text-[10px] font-semibold uppercase tracking-wider text-amber-800">own</span>
                            </span>
                            <span class="font-mono text-stone-700">{{ type.count }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">A split card counts once for each type it can resolve as.</p>

                    <!-- A type of this scenario's own: added here, because this is
                         where the designer is when they want one. The shared library
                         is edited at /rules/card-types. -->
                    <form class="mt-3 border-t border-stone-200 pt-3" @submit.prevent="addCardType">
                        <p class="field-micro">A type only this scenario's cards can use</p>
                        <div class="flex items-end gap-2">
                            <input v-model="newCardType.name" type="text" class="field" placeholder="Tide">
                            <input v-model="newCardType.colour" type="color" class="h-9 w-12 shrink-0 cursor-pointer rounded border border-stone-300 bg-white p-1">
                            <button type="submit" class="btn-ghost shrink-0" :disabled="newCardType.processing">
                                <Icon name="add" /> Add
                            </button>
                        </div>
                        <p v-for="(message, field) in newCardType.errors" :key="field" class="field-error">{{ message }}</p>
                        <p class="mt-1.5 text-xs text-stone-500">
                            <Link href="/rules/card-types" class="text-amber-800 underline">Card types</Link>
                            is where every type is renamed, recoloured and described.
                        </p>
                    </form>
                </div>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Arrow mix</h2>
                <div class="flex items-center gap-3 text-sm">
                    <span class="w-16 text-right">▲ top</span>
                    <span class="h-3.5 rounded-sm bg-stone-800" :style="{ width: `${(arrowMix.top / Math.max(1, arrowMix.total)) * 70}%` }" />
                    <span class="text-xs text-stone-600">{{ arrowMix.top }}</span>
                </div>
                <div class="mt-1.5 flex items-center gap-3 text-sm">
                    <span class="w-16 text-right">▼ bottom</span>
                    <span class="h-3.5 rounded-sm bg-stone-500" :style="{ width: `${(arrowMix.bottom / Math.max(1, arrowMix.total)) * 70}%` }" />
                    <span class="text-xs text-stone-600">{{ arrowMix.bottom }}</span>
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    Printed cards whose arrow points at the top or the bottom half of the next card.
                </p>
            </div>

            <div class="flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Cards</h2>
                <Link :href="`/cards/create?scenario=${scenario.slug}`" class="btn-primary"><Icon name="add" /> New card</Link>
            </div>

            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <div v-for="(card, index) in cards" :key="card.id">
                    <button
                        type="button"
                        class="card-button"
                        :aria-label="`View ${card.name || 'untitled card'} at full size`"
                        @click="zoom.open(cards, index)"
                    >
                        <CardPreview :card="card" kind="entity" :width="150" />
                    </button>
                    <Link
                        :href="`/cards/${card.id}/edit`"
                        class="mt-1 block text-center text-xs text-stone-600 hover:text-stone-900 hover:underline"
                    >
                        ×{{ card.qty }}<span v-if="card.added_by_beat"> · beat {{ card.added_by_beat.order }}</span>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Story beats -->
        <section v-else-if="tab === 'beats'" class="space-y-4">
            <BeatRow v-for="beat in beats" :key="beat.id" :beat="beat" />

            <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="addBeat">
                <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold"><Icon name="beat" /> Add a beat</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-48 flex-1">
                        <label class="field-label">Name</label>
                        <input v-model="newBeat.name" type="text" class="field" placeholder="Storm Front">
                    </div>
                    <div class="w-28">
                        <label class="field-label">Dread change</label>
                        <input v-model.number="newBeat.dread_change" type="number" class="field">
                    </div>
                    <button type="submit" class="btn-primary" :disabled="newBeat.processing"><Icon name="add" /> Add beat</button>
                </div>
                <p v-if="newBeat.errors.name" class="field-error">{{ newBeat.errors.name }}</p>
            </form>
        </section>

        <!-- Entity board -->
        <section v-else-if="tab === 'board'" class="space-y-4">
            <BoardCardRow
                v-for="card in boardCards"
                :key="card.id"
                :card="card"
                :beats="beats"
                :suggestions="scenario.traits"
            />

            <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="addBoardCard">
                <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold"><Icon name="board" /> Add a board card</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-48 flex-1">
                        <label class="field-label">Name</label>
                        <input v-model="newBoardCard.name" type="text" class="field" placeholder="Whirlpool">
                    </div>
                    <div class="w-20">
                        <label class="field-label">Qty</label>
                        <input v-model.number="newBoardCard.qty" type="number" min="1" class="field">
                    </div>
                    <div class="w-28">
                        <label class="field-label">Health</label>
                        <input v-model="newBoardCard.health" type="text" class="field">
                    </div>
                    <button type="submit" class="btn-primary" :disabled="newBoardCard.processing">Add</button>
                </div>
                <p v-if="newBoardCard.errors.name" class="field-error">{{ newBoardCard.errors.name }}</p>
            </form>
        </section>

        <!-- Town -->
        <section v-else-if="tab === 'town'" class="max-w-3xl space-y-4">
            <p class="text-sm text-stone-600">
                Each player can take each action once per round, at the end of the entity phase. Every action adds omen.
            </p>

            <!-- The face that prints, so the table and the card cannot drift apart. -->
            <div v-if="townActions.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                <CardPreview v-for="action in townActions" :key="action.id" :card="action" kind="town" :width="150" />
            </div>

            <table class="w-full border-collapse overflow-hidden rounded-lg border border-stone-300 bg-white text-sm">
                <thead class="bg-stone-100 text-left text-xs uppercase tracking-wider text-stone-600">
                    <tr>
                        <th class="px-3 py-2">District</th>
                        <th class="px-3 py-2">Effect</th>
                        <th class="px-3 py-2 w-20">Gold</th>
                        <th class="px-3 py-2 w-20">Omen</th>
                        <th class="px-3 py-2" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="action in townActions" :key="action.id" class="border-t border-stone-200 align-top">
                        <td class="px-3 py-2 font-medium">{{ action.name }}</td>
                        <td class="px-3 py-2">
                            {{ action.effect }}
                            <span v-if="action.note" class="block text-xs italic text-stone-500">{{ action.note }}</span>
                        </td>
                        <td class="px-3 py-2 font-mono">{{ action.gold_cost ?? '—' }}</td>
                        <td class="px-3 py-2 font-mono">{{ action.omen }}</td>
                        <td class="px-3 py-2 text-right">
                            <button type="button" class="text-xs text-red-700 hover:underline" @click="deleteTownAction(action)">delete</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="addTownAction">
                <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold"><Icon name="town" /> Add a town action</h3>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="w-40">
                        <label class="field-label">District</label>
                        <input v-model="newTownAction.name" type="text" class="field" placeholder="Chapel">
                    </div>
                    <div class="min-w-40 flex-1">
                        <label class="field-label">Effect</label>
                        <input v-model="newTownAction.effect" type="text" class="field" placeholder="Heal 3">
                    </div>
                    <div class="w-20">
                        <label class="field-label">Gold</label>
                        <input v-model.number="newTownAction.gold_cost" type="number" min="0" class="field">
                    </div>
                    <div class="w-20">
                        <label class="field-label">Omen</label>
                        <input v-model.number="newTownAction.omen" type="number" min="0" class="field">
                    </div>
                    <button type="submit" class="btn-primary" :disabled="newTownAction.processing">Add</button>
                </div>
                <p v-if="newTownAction.errors.name" class="field-error">{{ newTownAction.errors.name }}</p>
            </form>
        </section>

        <!-- Setup -->
        <section v-else class="max-w-3xl space-y-4">
            <p class="text-sm text-stone-600">
                What the table looks like before the first round: the board cards put into play, the beats set aside, the
                deck shuffled. One step per line, and they print numbered on a card of their own.
            </p>

            <div v-if="setupCard" class="flex flex-wrap items-start gap-6">
                <!-- The face that prints, so the page and the card cannot drift apart. -->
                <CardPreview :card="setupCard" kind="setup" :width="220" />

                <ol class="flex-1 space-y-2 text-sm text-stone-800">
                    <li
                        v-for="(step, index) in setupCard.steps"
                        :key="index"
                        class="flex gap-3 rounded border border-stone-300 bg-white px-3 py-2"
                    >
                        <span class="font-mono text-stone-500">{{ index + 1 }}</span>
                        <span v-html="renderStep(step)" />
                    </li>
                </ol>
            </div>

            <p v-else class="rounded-lg border border-dashed border-stone-400 bg-white p-4 text-sm text-stone-600">
                No setup written yet, so this scenario prints no setup card.
                <Link :href="`/scenarios/${scenario.slug}/edit`" class="text-amber-800 underline">Edit the scenario</Link>
                to write one.
            </p>
        </section>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :edit-href="`/cards/${zoom.card.id}/edit`"
        :caption="`${zoom.card.name || 'Untitled card'} · ×${zoom.card.qty}`"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>
