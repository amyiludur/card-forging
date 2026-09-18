<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import TraitInput from '../../Components/TraitInput.vue';

const props = defineProps({
    module: { type: Object, default: null },
    scenarios: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.module?.name ?? '',
    slug: props.module?.slug ?? '',
    status: props.module?.status ?? '',
    theme: props.module?.theme ?? '',
    set_icon: props.module?.set_icon ?? '',
    compatible_scenarios: props.module?.compatible_scenarios ?? [],
    traits: props.module?.traits ?? [],
    setup: props.module?.setup ?? '',
});

const toggle = (slug) => {
    form.compatible_scenarios = form.compatible_scenarios.includes(slug)
        ? form.compatible_scenarios.filter((s) => s !== slug)
        : [...form.compatible_scenarios, slug];
};

const submit = () => (props.module ? form.put(`/modules/${props.module.slug}`) : form.post('/modules'));
</script>

<template>
    <Head :title="module ? `Edit ${module.name}` : 'New module'" />

    <PageHeader :title="module ? `Edit ${module.name}` : 'New module'">
        <template #actions>
            <Link :href="module ? `/modules/${module.slug}` : '/modules'" class="btn-ghost">Cancel</Link>
        </template>
    </PageHeader>

    <form class="max-w-3xl space-y-5 px-6 py-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-[1fr,7rem]">
            <div>
                <label class="field-label">Name</label>
                <input v-model="form.name" type="text" class="field" placeholder="What Lurks Below">
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="field-label">Set icon</label>
                <input v-model="form.set_icon" type="text" maxlength="16" class="field font-mono" placeholder="WLB">
                <p class="field-hint">Printed on every card in the module.</p>
            </div>
        </div>

        <div>
            <label class="field-label">Theme</label>
            <textarea v-model="form.theme" rows="2" class="field" placeholder="Things in the dark water: drowned crews, crushing pressure, sunken wrecks." />
        </div>

        <div>
            <label class="field-label">Status note</label>
            <input v-model="form.status" type="text" class="field" placeholder="draft; all numbers and cards are placeholders">
        </div>

        <div>
            <label class="field-label">Works with</label>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="scenario in scenarios"
                    :key="scenario.slug"
                    type="button"
                    class="rounded border px-2.5 py-1 text-sm"
                    :class="form.compatible_scenarios.includes(scenario.slug)
                        ? 'border-stone-900 bg-stone-900 text-stone-50'
                        : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'"
                    @click="toggle(scenario.slug)"
                >
                    {{ scenario.name }}
                </button>
            </div>
            <p class="field-hint">
                Pick none to let the module be used with every scenario. Picking some means the deck assembly view
                warns when it is used anywhere else, rather than refusing.
            </p>
        </div>

        <TraitInput v-model="form.traits" label="Module traits" />

        <div>
            <label class="field-label">Setup</label>
            <textarea v-model="form.setup" rows="2" class="field" placeholder="Anything this module changes at setup" />
        </div>

        <div class="flex items-center gap-3 border-t border-stone-200 pt-4">
            <button type="submit" class="btn-primary" :disabled="form.processing">
                {{ module ? 'Save module' : 'Create module' }}
            </button>
            <Link
                v-if="module"
                :href="`/modules/${module.slug}`"
                method="delete"
                as="button"
                type="button"
                class="text-sm text-red-700 hover:underline"
                onclick="return confirm('Delete this module and all its cards?')"
            >
                Delete module
            </Link>
        </div>
    </form>
</template>
