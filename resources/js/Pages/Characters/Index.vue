<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';

defineProps({
    characters: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Characters" />

    <PageHeader
        title="Characters"
        subtitle="The player side. Each character brings 20 signature cards; the other 20 come from a domain, which is not designed yet."
    >
        <template #actions>
            <Link href="/characters/create" class="btn-primary">New character</Link>
        </template>
    </PageHeader>

    <div class="px-6 py-6">
        <div v-if="characters.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="character in characters" :key="character.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/characters/${character.slug}`" class="font-serif text-lg font-semibold hover:text-amber-800">
                            {{ character.name }}
                        </Link>
                        <p v-if="character.title" class="text-sm text-stone-700">{{ character.title }}</p>
                        <p v-else class="text-xs italic text-stone-500">name and story not written yet</p>
                    </div>
                    <div class="shrink-0 text-right text-xs text-stone-600">
                        <div><strong class="text-sm text-stone-900">{{ character.health }}</strong> ♥</div>
                        <div>hand {{ character.hand_size }}</div>
                    </div>
                </div>

                <p v-if="character.identity" class="mt-2 text-sm text-stone-700">{{ character.identity }}</p>
                <p v-if="character.status" class="mt-2 text-xs italic text-amber-800">{{ character.status }}</p>

                <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                    <strong>{{ character.signature_count }}</strong> signature ·
                    {{ character.kit_count }} kit · {{ character.upgrade_count }} upgrades
                    <span v-if="character.ability_name"> · {{ character.ability_name }}</span>
                    <span v-if="character.warnings" class="ml-1 font-semibold text-amber-800">
                        · {{ character.warnings }} to look at
                    </span>
                </p>
            </article>
        </div>

        <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
            No characters yet. Run <code>php artisan design:import</code>, or create one.
        </p>
    </div>
</template>
