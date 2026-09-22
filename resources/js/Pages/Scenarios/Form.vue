<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import MarkupField from '../../Components/MarkupField.vue';
import TraitInput from '../../Components/TraitInput.vue';
import ScaledNumberField from '../../Components/ScaledNumberField.vue';
import { scaledMarkup } from '../../playerScaled';

const props = defineProps({
    scenario: { type: Object, default: null },
    modules: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.scenario?.name ?? '',
    slug: props.scenario?.slug ?? '',
    entity_type: props.scenario?.entity_type ?? 'creature',
    status: props.scenario?.status ?? '',
    overview: props.scenario?.overview ?? '',
    setup: props.scenario?.setup ?? '',
    starting_dread: props.scenario?.starting_dread ?? 2,
    // Empty is a plain number; an equation counting the players is the value
    // while it is there. The number above is what comes back if it is cleared.
    starting_dread_equation: props.scenario?.starting_dread_equation ?? null,
    dread_effect: props.scenario?.dread_effect ?? '',
    traits: props.scenario?.traits ?? [],
    win_text: props.scenario?.win_text ?? '',
    lose_text: props.scenario?.lose_text ?? '',
    printed_arrows: props.scenario?.printed_arrows ?? true,
    modules_required: props.scenario?.modules_required ?? 1,
    recommended_modules: props.scenario?.recommended_modules ?? [],
    module_note: props.scenario?.module_note ?? '',
});

// What {dreadAmount} writes onto this scenario's cards, off the form rather
// than off the saved row, so typing a new starting Dread — or an equation —
// shows in the setup preview before it is saved. Mirrors
// Scenario::startingDread()->markup() on the server.
const dreadAmount = computed(() => scaledMarkup(form.starting_dread, form.starting_dread_equation));

const toggleModule = (slug) => {
    form.recommended_modules = form.recommended_modules.includes(slug)
        ? form.recommended_modules.filter((s) => s !== slug)
        : [...form.recommended_modules, slug];
};

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

        <MarkupField
            v-model="form.setup"
            label="Setup"
            :rows="6"
            :dread-rule="form.dread_effect"
            :dread-amount="dreadAmount"
            :card-name="form.name ?? ''"
            hint="One step per line. They print numbered on a setup card of their own, and a scenario with nothing written here prints no setup card at all."
        />

        <!-- An equation and its "= 4 at 3 players" preview need more room than
             a number field does. -->
        <div class="grid gap-4" :class="form.starting_dread_equation ? 'sm:grid-cols-[18rem,1fr]' : 'sm:grid-cols-[8rem,1fr]'">
            <ScaledNumberField
                v-model:value="form.starting_dread"
                v-model:equation="form.starting_dread_equation"
                label="Starting Dread (X)"
                :min="0"
                :error="form.errors.starting_dread_equation || form.errors.starting_dread"
            />

            <!-- The rule may quote the number: a card writing {dreadRule} gets
                 the sentence with the number already in it. -->
            <MarkupField v-model="form.dread_effect" label="Dread effect" :rows="2" :dread-amount="dreadAmount" hint="What happens when fewer than X cards are revealed." />
        </div>

        <TraitInput v-model="form.traits" label="Scenario traits" />

        <MarkupField v-model="form.win_text" label="Win condition" :rows="2" />
        <MarkupField v-model="form.lose_text" label="Lose condition" :rows="2" />

        <label class="flex items-start gap-2 rounded border border-stone-300 bg-white p-3">
            <input v-model="form.printed_arrows" type="checkbox" class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600">
            <span class="text-sm text-stone-700">
                <span class="font-medium text-stone-900">Cards print an arrow.</span>
                Every entity card carries one on its right edge, pointing at the top or bottom half of the card to its
                right. Redirect is handled in play by placing a token over the printed arrow.
            </span>
        </label>

        <div class="rounded border border-stone-300 bg-white p-4">
            <h2 class="mb-3 font-serif text-base font-semibold">Modules</h2>

            <div class="mb-3 w-40">
                <label class="field-label">Modules required</label>
                <input v-model.number="form.modules_required" type="number" min="0" max="3" class="field">
                <p class="field-hint">How many modules a play of this scenario asks for.</p>
            </div>

            <label class="field-label">Recommended</label>
            <div v-if="modules.length" class="flex flex-wrap gap-2">
                <button
                    v-for="module in modules"
                    :key="module.slug"
                    type="button"
                    class="rounded border px-2.5 py-1 text-sm"
                    :class="form.recommended_modules.includes(module.slug)
                        ? 'border-stone-900 bg-stone-900 text-stone-50'
                        : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'"
                    @click="toggleModule(module.slug)"
                >
                    {{ module.name }}
                </button>
            </div>
            <p v-else class="text-sm text-stone-500">No modules yet.</p>

            <div class="mt-3">
                <label class="field-label">Note</label>
                <input v-model="form.module_note" type="text" class="field" placeholder="Placeholder. One recommended module, the other slot is free.">
            </div>
        </div>

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
