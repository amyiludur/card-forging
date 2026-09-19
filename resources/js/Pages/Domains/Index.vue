<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';

defineProps({
    domains: { type: Array, default: () => [] },
    slots: { type: Number, default: 0 },
});
</script>

<template>
    <Head title="Domains" />

    <PageHeader
        title="Domains"
        subtitle="The shared half of a deck. When a deck is built it pairs a character with one domain, any domain, and takes 20 of its cards — so a pool is not 20 cards, it is what 20 are picked from."
    >
        <template #actions>
            <Link href="/domains/create" class="btn-primary"><Icon name="add" /> New domain</Link>
        </template>
    </PageHeader>

    <div class="px-6 py-6">
        <div v-if="domains.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="domain in domains" :key="domain.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/domains/${domain.slug}`" class="flex items-center gap-2 font-serif text-lg font-semibold hover:text-amber-800">
                            <Icon :name="domain.is_neutral ? 'neutral' : 'domain'" class="text-stone-500" />
                            {{ domain.name }}
                        </Link>
                        <p v-if="domain.title" class="text-sm text-stone-700">{{ domain.title }}</p>
                        <p v-if="domain.is_neutral" class="text-xs italic text-stone-500">
                            colourless: its cards fill a domain slot without a colour
                        </p>
                    </div>
                    <span v-if="domain.set_icon" class="shrink-0 rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                        {{ domain.set_icon }}
                    </span>
                </div>

                <p v-if="domain.identity" class="mt-2 text-sm text-stone-700">{{ domain.identity }}</p>
                <p v-if="domain.status" class="mt-2 text-xs italic text-amber-800">{{ domain.status }}</p>

                <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                    <strong>{{ domain.pool_size }}</strong> cards in the pool<span v-if="slots"> · a character takes {{ slots }}</span>
                    <span v-if="slots && domain.pool_size > slots" class="text-stone-500">, leaving {{ domain.pool_size - slots }} to choose between</span>
                    <span v-else-if="slots && domain.pool_size < slots" class="font-semibold text-amber-800">, {{ slots - domain.pool_size }} short of a deck</span>
                    · {{ domain.upgrade_count }} upgrades
                    <span v-if="domain.warnings" class="ml-1 font-semibold text-amber-800">
                        <Icon name="warning" /> · {{ domain.warnings }} to look at
                    </span>
                </p>
            </article>
        </div>

        <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
            No domains yet. A deck is 20 signature cards plus {{ slots || 20 }} chosen out of a domain, and the domains are
            yours to design — create one, or run <code>php artisan design:import</code> after writing
            <code>design/players/domains/&lt;name&gt;.json</code>.
        </p>
    </div>
</template>
