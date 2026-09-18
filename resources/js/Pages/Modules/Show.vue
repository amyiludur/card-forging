<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';

const props = defineProps({
    module: { type: Object, required: true },
    cards: { type: Array, default: () => [] },
    boardCards: { type: Array, default: () => [] },
    cardTypes: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
});

const arrowMix = computed(() => ({
    top: props.cards.reduce((n, c) => n + (c.arrow === 'top' ? c.qty : 0), 0),
    bottom: props.cards.reduce((n, c) => n + (c.arrow === 'bottom' ? c.qty : 0), 0),
}));

const scenarioName = (slug) => props.scenarios.find((s) => s.slug === slug)?.name ?? slug;
</script>

<template>
    <Head :title="module.name" />

    <PageHeader :title="module.name" :subtitle="module.theme">
        <template #actions>
            <Link :href="`/cards?module=${module.slug}`" class="btn-ghost">Card list</Link>
            <Link :href="`/print/module/${module.slug}`" class="btn-ghost">Print</Link>
            <Link :href="`/modules/${module.slug}/edit`" class="btn-primary">Edit module</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span v-if="module.set_icon" class="rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                {{ module.set_icon }}
            </span>
            <span><strong>{{ module.deck_size }}</strong> printed cards</span>
            <span><strong>{{ boardCards.length }}</strong> board cards</span>
            <span>▲ {{ arrowMix.top }} · ▼ {{ arrowMix.bottom }}</span>
            <span v-if="module.compatible_scenarios.length">
                For {{ module.compatible_scenarios.map(scenarioName).join(', ') }}
            </span>
            <span v-else class="text-stone-500">Works with every scenario</span>
        </div>

        <p v-if="module.status" class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-900">{{ module.status }}</p>
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

            <div v-if="cards.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <Link v-for="card in cards" :key="card.id" :href="`/cards/${card.id}/edit`" class="group">
                    <CardPreview :card="card" kind="entity" :width="150" />
                    <p class="mt-1 text-center text-xs text-stone-600 group-hover:text-stone-900">×{{ card.qty }}</p>
                </Link>
            </div>

            <p v-else class="rounded border border-dashed border-stone-300 p-6 text-sm text-stone-600">
                No cards in this module yet.
            </p>
        </section>

        <section v-if="boardCards.length">
            <h2 class="mb-3 font-serif text-lg font-semibold">Board cards</h2>
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                <div v-for="card in boardCards" :key="card.id">
                    <CardPreview :card="card" kind="board" :width="150" />
                    <p class="mt-1 text-center text-xs text-stone-600">×{{ card.qty }}</p>
                </div>
            </div>
        </section>
    </div>
</template>
