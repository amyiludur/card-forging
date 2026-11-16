<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import CardPreview from '../../Components/CardPreview.vue';
import BoardCardRow from '../../Components/BoardCardRow.vue';
import TownActionRow from '../../Components/TownActionRow.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    module: { type: Object, required: true },
    tale: { type: Object, default: null },
    cards: { type: Array, default: () => [] },
    boardCards: { type: Array, default: () => [] },
    townActions: { type: Array, default: () => [] },
    cardTypes: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
});

const arrowMix = computed(() => ({
    top: props.cards.reduce((n, c) => n + (c.arrow === 'top' ? c.qty : 0), 0),
    bottom: props.cards.reduce((n, c) => n + (c.arrow === 'bottom' ? c.qty : 0), 0),
}));

const scenarioName = (slug) => props.scenarios.find((s) => s.slug === slug)?.name ?? slug;

const newBoardCard = useForm({
    name: '',
    qty: 1,
    health: '',
    traits: [],
    text: '',
    added_by_beat_id: null,
    is_placeholder: true,
});

const addBoardCard = () =>
    newBoardCard.post(`/modules/${props.module.slug}/board-cards`, {
        preserveScroll: true,
        onSuccess: () => newBoardCard.reset(),
    });

// A district the module adds to the town when it is chosen.
const newTownAction = useForm({ name: '', effect: '', gold_cost: null, omen: 1, note: '' });

const addTownAction = () =>
    newTownAction.post(`/modules/${props.module.slug}/town-actions`, {
        preserveScroll: true,
        onSuccess: () => newTownAction.reset(),
    });

const zoom = useCardZoom();
</script>

<template>
    <Head :title="module.name" />

    <PageHeader :title="module.name" :subtitle="module.theme">
        <template #actions>
            <Link :href="`/cards?module=${module.slug}`" class="btn-ghost"><Icon name="cards" /> Card list</Link>
            <Link :href="`/print/module/${module.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
            <Link :href="`/modules/${module.slug}/edit`" class="btn-primary"><Icon name="edit" /> Edit module</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span v-if="module.set_icon" class="rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                {{ module.set_icon }}
            </span>
            <span><strong>{{ module.deck_size }}</strong> printed cards</span>
            <span><strong>{{ boardCards.length }}</strong> board cards</span>
            <span v-if="townActions.length"><strong>{{ townActions.length }}</strong> town</span>
            <span>▲ {{ arrowMix.top }} · ▼ {{ arrowMix.bottom }}</span>
            <span v-if="module.compatible_scenarios.length">
                For {{ module.compatible_scenarios.map(scenarioName).join(', ') }}
            </span>
            <span v-else class="text-stone-500">Works with every scenario</span>
        </div>

        <p v-if="module.status" class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-900">{{ module.status }}</p>
        <!-- A Tale's own module is hidden from ordinary play, so say whose it is. -->
        <p v-if="tale" class="mt-2 text-sm text-sky-900">
            Part of the Tale <Link :href="`/tales/${tale.slug}`" class="font-medium underline">{{ tale.name }}</Link> — only played in it.
        </p>
    </PageHeader>

    <div class="space-y-8 px-6 py-6">
        <section v-if="module.setup" class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-1 font-serif text-base font-semibold">Setup</h2>
            <p class="text-sm text-stone-700">{{ module.setup }}</p>
        </section>

        <section>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="font-serif text-lg font-semibold">Entity cards</h2>
                <Link :href="`/cards/create?module=${module.slug}`" class="btn-primary">New card</Link>
            </div>

            <div v-if="cards.length" class="grid grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-4">
                <div v-for="(card, index) in cards" :key="card.id">
                    <button
                        type="button"
                        class="card-button"
                        :aria-label="`View ${card.name || 'untitled card'} at full size`"
                        @click="zoom.open(cards, index, 'entity')"
                    >
                        <CardPreview :card="card" kind="entity" :width="210" />
                    </button>
                    <Link
                        :href="`/cards/${card.id}/edit`"
                        class="mt-1 block text-center text-xs text-stone-600 hover:text-stone-900 hover:underline"
                    >
                        ×{{ card.qty }}
                    </Link>
                </div>
            </div>

            <p v-else class="rounded border border-dashed border-stone-300 p-6 text-sm text-stone-600">
                No cards in this module yet.
            </p>
        </section>

        <section>
            <h2 class="mb-3 font-serif text-lg font-semibold">Board cards</h2>

            <div class="space-y-4">
                <!-- BoardCardRow carries its own editor and zoom; a module has no
                     story beats, so it hides the beat picker. -->
                <BoardCardRow
                    v-for="card in boardCards"
                    :key="card.id"
                    :card="card"
                    :beats="[]"
                    :suggestions="module.traits"
                />

                <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="addBoardCard">
                    <h3 class="mb-2 font-serif text-base font-semibold">Add a board card</h3>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="min-w-48 flex-1">
                            <label class="field-label">Name</label>
                            <input v-model="newBoardCard.name" type="text" class="field" placeholder="Drowned">
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
            </div>
        </section>

        <section class="max-w-3xl">
            <h2 class="mb-1 font-serif text-lg font-semibold">Town</h2>
            <p class="mb-3 text-sm text-stone-600">
                Districts this module adds to the town when it is chosen. They join the scenario's own.
            </p>

            <div class="space-y-4">
                <!-- The same row the scenario's Town tab uses: the face that
                     prints, following the form as it is edited. -->
                <TownActionRow v-for="action in townActions" :key="action.id" :action="action" />

                <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="addTownAction">
                    <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold"><Icon name="town" /> Add a district</h3>
                    <div class="flex flex-wrap items-end gap-3">
                        <div class="w-40">
                            <label class="field-label">District</label>
                            <input v-model="newTownAction.name" type="text" class="field" placeholder="Farm">
                        </div>
                        <div class="min-w-40 flex-1">
                            <label class="field-label">Effect</label>
                            <input v-model="newTownAction.effect" type="text" class="field">
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
                    <div class="mt-3">
                        <label class="field-label">Note</label>
                        <input v-model="newTownAction.note" type="text" class="field">
                    </div>
                    <p v-if="newTownAction.errors.name" class="field-error">{{ newTownAction.errors.name }}</p>
                </form>
            </div>
        </section>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :edit-href="zoom.kind === 'entity' ? `/cards/${zoom.card.id}/edit` : null"
        :caption="`${zoom.card.name || 'Untitled card'} · ×${zoom.card.qty}`"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>
