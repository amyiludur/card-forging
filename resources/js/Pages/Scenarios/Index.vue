<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';

defineProps({ scenarios: { type: Array, default: () => [] } });
</script>

<template>
    <Head title="Scenarios" />

    <PageHeader title="Scenarios" subtitle="Each scenario is one story the players play through.">
        <template #actions>
            <Link href="/scenarios/create" class="btn-primary">New scenario</Link>
        </template>
    </PageHeader>

    <div class="grid gap-4 px-6 py-6 lg:grid-cols-2">
        <article v-for="scenario in scenarios" :key="scenario.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <Link :href="`/scenarios/${scenario.slug}`" class="font-serif text-lg font-semibold hover:text-amber-800">{{ scenario.name }}</Link>
                <span class="rounded bg-stone-200 px-1.5 py-0.5 text-[11px] uppercase tracking-wider text-stone-700">{{ scenario.entity_type }}</span>
            </div>
            <p v-if="scenario.overview" class="mt-2 text-sm text-stone-700">{{ scenario.overview }}</p>
            <div class="mt-3 flex flex-wrap gap-1">
                <span v-for="trait in scenario.traits" :key="trait" class="rounded border border-stone-300 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-stone-600">{{ trait }}</span>
            </div>
            <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                {{ scenario.deck_size }} deck cards · {{ scenario.board_cards_count }} board · {{ scenario.story_beats_count }} beats
            </p>
        </article>
    </div>
</template>
