<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';

defineProps({
    scenarios: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
});
</script>

<template>
    <Head title="Overview" />

    <PageHeader title="Card Forge" subtitle="Edit the cards, edit the rules, print the game.">
        <template #actions>
            <Link href="/scenarios/create" class="btn-primary">New scenario</Link>
        </template>
    </PageHeader>

    <div class="space-y-8 px-6 py-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stat">
                <p class="stat-value">{{ stats.placeholder_values }} / {{ stats.config_values }}</p>
                <p class="stat-label">tunable numbers still placeholders</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.placeholder_cards }}</p>
                <p class="stat-label">cards flagged as placeholder</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ scenarios.length }}</p>
                <p class="stat-label">scenarios</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.rule_documents }}</p>
                <p class="stat-label">rules documents</p>
            </div>
        </section>

        <section>
            <h2 class="mb-3 font-serif text-lg font-semibold text-stone-900">Scenarios</h2>

            <div v-if="scenarios.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="scenario in scenarios" :key="scenario.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <Link :href="`/scenarios/${scenario.slug}`" class="font-serif text-lg font-semibold text-stone-900 hover:text-amber-800">
                                {{ scenario.name }}
                            </Link>
                            <p class="text-xs uppercase tracking-wider text-stone-500">{{ scenario.entity_type }}</p>
                        </div>
                        <Link :href="`/print/${scenario.slug}`" class="btn-ghost">Print</Link>
                    </div>

                    <p v-if="scenario.overview" class="mt-2 text-sm leading-relaxed text-stone-700">{{ scenario.overview }}</p>
                    <p v-if="scenario.status" class="mt-2 text-xs italic text-amber-800">{{ scenario.status }}</p>

                    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-t border-stone-200 pt-3 text-xs text-stone-600">
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.deck_size }}</dt> <dd class="inline">cards in the entity deck</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.entity_cards_count }}</dt> <dd class="inline">unique</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.board_cards_count }}</dt> <dd class="inline">board cards</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.story_beats_count }}</dt> <dd class="inline">beats</dd></div>
                    </dl>
                </article>
            </div>

            <p v-else class="rounded border border-dashed border-stone-300 p-6 text-sm text-stone-600">
                No scenarios yet. Run <code>php artisan design:import</code> to load the design folder, or create one.
            </p>
        </section>

        <section class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-2 font-serif text-lg font-semibold text-stone-900">Round trip</h2>
            <ol class="list-inside list-decimal space-y-1 text-sm text-stone-700">
                <li><code>php artisan design:import</code> loads <code>design/</code> into the editor.</li>
                <li>Edit cards, beats, board, town and rules here.</li>
                <li><code>php artisan design:export</code> writes it all back to <code>design/</code>.</li>
                <li>Commit <code>design/</code> — git keeps the design history.</li>
            </ol>
        </section>
    </div>
</template>
