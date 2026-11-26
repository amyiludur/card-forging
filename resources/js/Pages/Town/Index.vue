<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import TownActionRow from '../../Components/TownActionRow.vue';

// The common districts: each written once, here, and picked by the scenarios
// that want it on their own Town tab. Editing one changes every town using it.
defineProps({
    districts: { type: Array, default: () => [] },
});

const adding = useForm({ name: '', effect: '', gold_cost: null, omen: 1, note: '' });

const add = () => adding.post('/town', { preserveScroll: true, onSuccess: () => adding.reset() });

const usedBy = (district) => (district.used_by?.length
    ? `In ${district.used_by.join(', ')}.`
    : 'Not in any town yet.');
</script>

<template>
    <Head title="Common districts" />

    <PageHeader
        title="Common districts"
        subtitle="Districts written once and picked by each scenario that wants one, from the scenario's Town tab. A scenario can add a line of its own to a district it picks; that line is edited there, not here."
    />

    <div class="max-w-3xl space-y-4 px-6 py-6">
        <p v-if="!districts.length" class="text-sm text-stone-500">No common districts yet.</p>

        <div v-for="district in districts" :key="district.id" class="space-y-1">
            <TownActionRow :action="district" />
            <p class="px-1 text-xs text-stone-500">{{ usedBy(district) }}</p>
        </div>

        <form class="rounded-lg border border-dashed border-stone-400 bg-white p-4" @submit.prevent="add">
            <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold"><Icon name="town" /> Add a common district</h3>
            <div class="flex flex-wrap items-end gap-3">
                <div class="w-40">
                    <label class="field-label">District</label>
                    <input v-model="adding.name" type="text" class="field" placeholder="Smithy">
                </div>
                <div class="min-w-40 flex-1">
                    <label class="field-label">Effect</label>
                    <input v-model="adding.effect" type="text" class="field" placeholder="Upgrade a card">
                </div>
                <div class="w-20">
                    <label class="field-label">Gold</label>
                    <input v-model.number="adding.gold_cost" type="number" min="0" class="field">
                </div>
                <div class="w-20">
                    <label class="field-label">Omen</label>
                    <input v-model.number="adding.omen" type="number" min="0" class="field">
                </div>
                <button type="submit" class="btn-primary" :disabled="adding.processing">Add</button>
            </div>
            <div class="mt-3">
                <label class="field-label">Note</label>
                <input v-model="adding.note" type="text" class="field">
            </div>
            <p v-if="adding.errors.name" class="field-error">{{ adding.errors.name }}</p>
        </form>
    </div>
</template>
