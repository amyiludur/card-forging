<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import { renderMarkup } from '../markup';

/**
 * One scenario in a Tale: which scenario, the modules that join it — the
 * Tale's own listed first, then the shared ones — and the story around it.
 */
const props = defineProps({
    step: { type: Object, required: true },
    scenarios: { type: Array, default: () => [] },
    taleModules: { type: Array, default: () => [] },
    sharedModules: { type: Array, default: () => [] },
    previous: { type: Object, default: null },
    next: { type: Object, default: null },
});

const open = ref(false);

const fields = (step) => ({
    scenario_id: step.scenario?.id ?? null,
    intro: step.intro ?? '',
    outro: step.outro ?? '',
    notes: step.notes ?? '',
    module_ids: step.modules.map((m) => m.id),
});

const form = useForm(fields(props.step));

watch(() => props.step, (step) => form.defaults(fields(step)).reset(), { deep: true });

const toggle = (id) => {
    form.module_ids = form.module_ids.includes(id)
        ? form.module_ids.filter((m) => m !== id)
        : [...form.module_ids, id];
};

const save = () => form.put(`/tale-steps/${props.step.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

// One request moves a step one place; the server swaps it with its neighbour.
const move = (direction) => router.post(`/tale-steps/${props.step.id}/move`, { direction }, { preserveScroll: true });

const destroy = () => {
    if (confirm(`Take ${props.step.scenario?.name ?? 'this scenario'} out of the Tale? The scenario itself stays.`)) {
        router.delete(`/tale-steps/${props.step.id}`, { preserveScroll: true });
    }
};

// The step's own scenario behind its three tokens, as MarkupField names them.
const tokens = computed(() => ({
    dreadRule: props.step.dread_rule ?? null,
    dreadAmount: props.step.dread_amount ?? null,
    omenAmount: props.step.omen_amount ?? null,
}));

const page = usePage();
const introPreview = computed(() => renderMarkup(props.step.intro ?? '', {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
    ...tokens.value,
}));

const moduleNames = computed(() => props.step.modules.map((m) => m.name).join(', '));
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-4 p-4">
            <span class="font-serif text-2xl font-semibold text-stone-400">{{ step.number }}</span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-2">
                    <Link v-if="step.scenario" :href="`/scenarios/${step.scenario.slug}`" class="font-serif text-base font-semibold hover:text-amber-800">
                        {{ step.scenario.name }}
                    </Link>
                    <span class="text-xs text-stone-500">{{ moduleNames ? `with ${moduleNames}` : 'no modules named' }}</span>
                </div>
                <p v-if="step.intro" class="mt-1 line-clamp-2 text-sm text-stone-700" v-html="introPreview" />
            </div>

            <div class="flex shrink-0 gap-1">
                <button type="button" class="btn-ghost px-2" :disabled="!previous" title="Move up" @click="move('up')">↑</button>
                <button type="button" class="btn-ghost px-2" :disabled="!next" title="Move down" @click="move('down')">↓</button>
                <button type="button" class="btn-ghost" @click="open = !open">{{ open ? 'Close' : 'Edit' }}</button>
            </div>
        </div>

        <form v-if="open" class="space-y-4 border-t border-stone-200 p-4" @submit.prevent="save">
            <div>
                <label class="field-label">Scenario</label>
                <select v-model="form.scenario_id" class="field">
                    <option v-for="scenario in scenarios" :key="scenario.id" :value="scenario.id">{{ scenario.name }}</option>
                </select>
                <p v-if="form.errors.scenario_id" class="field-error">{{ form.errors.scenario_id }}</p>
            </div>

            <fieldset>
                <legend class="field-label">Modules that join it</legend>
                <div class="space-y-1">
                    <p v-if="taleModules.length" class="text-xs font-semibold uppercase tracking-wide text-stone-500">The Tale's own</p>
                    <label v-for="module in taleModules" :key="module.id" class="mr-4 inline-flex items-center gap-1.5 text-sm">
                        <input type="checkbox" :checked="form.module_ids.includes(module.id)" @change="toggle(module.id)">
                        {{ module.name }}
                    </label>
                    <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-500">Shared</p>
                    <label v-for="module in sharedModules" :key="module.id" class="mr-4 inline-flex items-center gap-1.5 text-sm">
                        <input type="checkbox" :checked="form.module_ids.includes(module.id)" @change="toggle(module.id)">
                        {{ module.name }}
                    </label>
                </div>
            </fieldset>

            <!-- Card markup, like any card text: icons, {perPlayer}, and this
                 scenario's own {dreadRule}, {dreadAmount} and {omenAmount}. -->
            <MarkupField v-model="form.intro" label="Before it" :rows="4" v-bind="tokens" hint="Read before the scenario is set up." />
            <MarkupField v-model="form.notes" label="Setup changes" :rows="2" v-bind="tokens" hint="Anything about this scenario that is different in the Tale, in your words." />
            <MarkupField v-model="form.outro" label="After it" :rows="4" v-bind="tokens" hint="Read when the scenario ends." />

            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="btn-ghost text-red-700" @click="destroy">Remove from the Tale</button>
            </div>
        </form>
    </div>
</template>
