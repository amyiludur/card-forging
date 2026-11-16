<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';

defineProps({
    tales: { type: Array, default: () => [] },
});

const count = (n, one, many) => `${n} ${n === 1 ? one : many}`;
</script>

<template>
    <Head title="Tales" />

    <PageHeader
        title="Tales"
        subtitle="Campaign boxes: scenarios played in order, a story running through them, and cards that only come out when the Tale is played."
    >
        <template #actions>
            <Link href="/tales/create" class="btn-primary"><Icon name="add" /> New Tale</Link>
        </template>
    </PageHeader>

    <div class="px-6 py-6">
        <div v-if="tales.length" class="grid gap-4 lg:grid-cols-2">
            <article v-for="tale in tales" :key="tale.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <Link :href="`/tales/${tale.slug}`" class="font-serif text-lg font-semibold hover:text-amber-800">
                            {{ tale.name }}
                        </Link>
                        <p v-if="tale.overview" class="mt-1 line-clamp-3 whitespace-pre-line text-sm text-stone-700">{{ tale.overview }}</p>
                    </div>
                    <span v-if="tale.set_icon" class="shrink-0 rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                        {{ tale.set_icon }}
                    </span>
                </div>

                <p v-if="tale.status" class="mt-2 text-xs italic text-amber-800">{{ tale.status }}</p>

                <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                    {{ count(tale.steps_count, 'scenario', 'scenarios') }} ·
                    {{ count(tale.modes_count, 'mode', 'modes') }} ·
                    {{ count(tale.log_entries_count, 'log entry', 'log entries') }} ·
                    {{ count(tale.cards_count, 'Tale card', 'Tale cards') }} ·
                    {{ count(tale.reward_cards_count, 'reward', 'rewards') }} ·
                    {{ count(tale.modules_count, 'module', 'modules') }}
                </p>
            </article>
        </div>

        <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
            No Tales yet. A Tale lists scenarios in order and adds its own story, modes, log and cards on top.
        </p>
    </div>
</template>
