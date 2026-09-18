<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';

defineProps({
    modules: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Modules" />

    <PageHeader
        title="Modules"
        subtitle="Themed sets of cards dropped into a scenario. Each one prints its own set icon so the decks can be separated again."
    >
        <template #actions>
            <Link href="/modules/create" class="btn-primary">New module</Link>
        </template>
    </PageHeader>

    <div class="px-6 py-6">
        <div v-if="modules.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="module in modules" :key="module.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <Link :href="`/modules/${module.slug}`" class="font-serif text-lg font-semibold hover:text-amber-800">
                            {{ module.name }}
                        </Link>
                        <p v-if="module.theme" class="mt-1 text-sm text-stone-700">{{ module.theme }}</p>
                    </div>
                    <span v-if="module.set_icon" class="shrink-0 rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                        {{ module.set_icon }}
                    </span>
                </div>

                <p v-if="module.status" class="mt-2 text-xs italic text-amber-800">{{ module.status }}</p>

                <div class="mt-3 flex flex-wrap gap-1">
                    <span v-for="trait in module.traits" :key="trait" class="rounded border border-stone-300 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-stone-600">
                        {{ trait }}
                    </span>
                </div>

                <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                    {{ module.deck_size }} printed cards ({{ module.entity_cards_count }} unique) ·
                    {{ module.board_cards_count }} board
                    <span v-if="module.compatible_scenarios.length">
                        · for {{ module.compatible_scenarios.join(', ') }}
                    </span>
                    <span v-else>· works with every scenario</span>
                </p>
            </article>
        </div>

        <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
            No modules yet. Run <code>php artisan design:import</code>, or create one.
        </p>
    </div>
</template>
