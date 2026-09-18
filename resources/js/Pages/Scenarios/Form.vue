<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import MarkupField from '../../Components/MarkupField.vue';
import TraitInput from '../../Components/TraitInput.vue';

const props = defineProps({ scenario: { type: Object, default: null } });

const form = useForm({
    name: props.scenario?.name ?? '',
    slug: props.scenario?.slug ?? '',
    entity_type: props.scenario?.entity_type ?? 'creature',
    status: props.scenario?.status ?? '',
    overview: props.scenario?.overview ?? '',
    starting_dread: props.scenario?.starting_dread ?? 2,
    dread_effect: props.scenario?.dread_effect ?? '',
    traits: props.scenario?.traits ?? [],
    win_text: props.scenario?.win_text ?? '',
    lose_text: props.scenario?.lose_text ?? '',
    printed_arrows: props.scenario?.printed_arrows ?? true,
});

const submit = () => {
    if (props.scenario) {
        form.put(`/scenarios/${props.scenario.slug}`);
    } else {
        form.post('/scenarios');
    }
};
</script>

<template>
    <Head :title="scenario ? `Edit ${scenario.name}` : 'New scenario'" />

    <PageHeader :title="scenario ? `Edit ${scenario.name}` : 'New scenario'">
        <template #actions>
            <Link v-if="scenario" :href="`/scenarios/${scenario.slug}`" class="btn-ghost">Cancel</Link>
        </template>
    </PageHeader>

    <form class="max-w-3xl space-y-5 px-6 py-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="field-label">Name</label>
                <input v-model="form.name" type="text" class="field">
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="field-label">Entity type</label>
                <select v-model="form.entity_type" class="field">
                    <option value="creature">Creature</option>
                    <option value="concept">Concept (no health)</option>
                    <option value="group">Group</option>
                </select>
            </div>
        </div>

        <div>
            <label class="field-label">Status note</label>
            <input v-model="form.status" type="text" class="field" placeholder="draft; all numbers are placeholders">
            <p class="field-hint">Shown wherever the scenario appears, so drafts stay obviously draft.</p>
        </div>

        <div>
            <label class="field-label">Overview</label>
            <textarea v-model="form.overview" rows="3" class="field" />
        </div>

        <div class="grid gap-4 sm:grid-cols-[8rem,1fr]">
            <div>
                <label class="field-label">Starting Dread (X)</label>
                <input v-model.number="form.starting_dread" type="number" min="0" class="field">
            </div>

            <MarkupField v-model="form.dread_effect" label="Dread effect" :rows="2" hint="What happens when fewer than X cards are revealed." />
        </div>

        <TraitInput v-model="form.traits" label="Scenario traits" />

        <MarkupField v-model="form.win_text" label="Win condition" :rows="2" />
        <MarkupField v-model="form.lose_text" label="Lose condition" :rows="2" />

        <label class="flex items-start gap-2 rounded border border-stone-300 bg-white p-3">
            <input v-model="form.printed_arrows" type="checkbox" class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600">
            <span class="text-sm text-stone-700">
                <span class="font-medium text-stone-900">Split cards print an arrow.</span>
                Redirect is handled in play by placing a token over the printed arrow. Turn this off if you move to
                arrow tokens placed at reveal instead — the arrow then disappears from the printed card.
            </span>
        </label>

        <div class="flex items-center gap-3 border-t border-stone-200 pt-4">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ scenario ? 'Save scenario' : 'Create scenario' }}
            </button>
            <Link
                v-if="scenario"
                :href="`/scenarios/${scenario.slug}`"
                method="delete"
                as="button"
                type="button"
                class="text-sm text-red-700 hover:underline"
                onclick="return confirm('Delete this scenario and everything in it?')"
            >
                Delete scenario
            </Link>
        </div>
    </form>
</template>
