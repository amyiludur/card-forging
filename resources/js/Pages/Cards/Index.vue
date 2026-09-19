<script setup>
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    scenario: { type: Object, default: null },
    cards: { type: Array, default: () => [] },
    cardTypes: { type: Array, default: () => [] },
    traits: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    deckSize: { type: Number, default: 0 },
});

const filters = ref({
    q: props.filters.q ?? '',
    type: props.filters.type ?? '',
    layout: props.filters.layout ?? '',
    trait: props.filters.trait ?? '',
});

let timer = null;

watch(
    filters,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                '/cards',
                { scenario: props.scenario?.slug, ...Object.fromEntries(Object.entries(value).filter(([, v]) => v)) },
                { preserveState: true, preserveScroll: true, replace: true }
            );
        }, 250);
    },
    { deep: true }
);

const clear = () => (filters.value = { q: '', type: '', layout: '', trait: '' });

const zoom = useCardZoom();
</script>

<template>
    <Head title="Cards" />

    <PageHeader
        :title="scenario ? `${scenario.name} — cards` : 'All cards'"
        :subtitle="`${cards.length} unique, ${deckSize} printed`"
    >
        <template #actions>
            <Link v-if="scenario" :href="`/print/${scenario.slug}`" class="btn-ghost">Print</Link>
            <Link :href="scenario ? `/cards/create?scenario=${scenario.slug}` : '/cards/create'" class="btn-primary">New card</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-48 flex-1">
                <label class="field-label">Search</label>
                <input v-model="filters.q" type="search" class="field" placeholder="name or effect text">
            </div>
            <div class="w-40">
                <label class="field-label">Type</label>
                <select v-model="filters.type" class="field">
                    <option value="">Any</option>
                    <option v-for="type in cardTypes" :key="type.slug" :value="type.slug">{{ type.name }}</option>
                </select>
            </div>
            <div class="w-36">
                <label class="field-label">Layout</label>
                <select v-model="filters.layout" class="field">
                    <option value="">Any</option>
                    <option value="single">Single</option>
                    <option value="split">Split</option>
                    <option value="x-cost">X-cost</option>
                </select>
            </div>
            <div v-if="traits.length" class="w-40">
                <label class="field-label">Trait</label>
                <select v-model="filters.trait" class="field">
                    <option value="">Any</option>
                    <option v-for="trait in traits" :key="trait" :value="trait">{{ trait }}</option>
                </select>
            </div>
            <button type="button" class="btn-ghost" @click="clear">Clear</button>
        </div>
    </PageHeader>

    <div class="px-6 py-6">
        <div v-if="cards.length" class="grid grid-cols-2 gap-5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
            <div v-for="(card, index) in cards" :key="card.id">
                <button
                    type="button"
                    class="card-button"
                    :aria-label="`View ${card.name || 'untitled card'} at full size`"
                    @click="zoom.open(cards, index)"
                >
                    <CardPreview :card="card" kind="entity" :width="160" />
                </button>
                <Link
                    :href="`/cards/${card.id}/edit`"
                    class="mt-1 block text-center text-xs text-stone-600 hover:text-stone-900 hover:underline"
                >
                    ×{{ card.qty }}
                    <span v-if="card.layout === 'split' && !card.arrow" class="text-amber-700">· no arrow</span>
                    <span v-if="card.added_by_beat"> · beat {{ card.added_by_beat.order }}</span>
                </Link>
            </div>
        </div>

        <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
            No cards match these filters.
        </p>
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
