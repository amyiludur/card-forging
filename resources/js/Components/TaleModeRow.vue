<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import { renderRules } from '../rulesMarkdown';

/** One difficulty mode: a name and the designer's text for it. */
const props = defineProps({
    mode: { type: Object, required: true },
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
});

// The order is the order the log sheet's mode line prints in.
const move = (direction) => router.post(`/tale-modes/${props.mode.id}/move`, { direction }, { preserveScroll: true });

// Card markup, rendered the way the printed book renders it.
const page = usePage();
const textHtml = computed(() => renderRules(props.mode.text ?? '', {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
}));

const open = ref(false);
const fields = (mode) => ({ name: mode.name, text: mode.text ?? '', is_placeholder: mode.is_placeholder });
const form = useForm(fields(props.mode));

watch(() => props.mode, (mode) => form.defaults(fields(mode)).reset(), { deep: true });

const save = () => form.put(`/tale-modes/${props.mode.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const destroy = () => {
    if (confirm(`Delete the ${props.mode.name} mode?`)) {
        router.delete(`/tale-modes/${props.mode.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-3 p-4">
            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2">
                    <h3 class="font-serif text-base font-semibold">{{ mode.name }}</h3>
                    <span v-if="mode.is_placeholder" class="rounded bg-amber-100 px-1.5 text-xs text-amber-900">placeholder</span>
                </div>
                <div v-if="mode.text" class="rules-prose mt-1 text-sm text-stone-700" v-html="textHtml" />
                <p v-else class="mt-1 text-sm text-stone-500">Nothing written for this mode yet.</p>
            </div>
            <div class="flex shrink-0 gap-1">
                <button type="button" class="btn-ghost px-2" :disabled="first" title="Move up" @click="move('up')">↑</button>
                <button type="button" class="btn-ghost px-2" :disabled="last" title="Move down" @click="move('down')">↓</button>
                <button type="button" class="btn-ghost" @click="open = !open">{{ open ? 'Close' : 'Edit' }}</button>
            </div>
        </div>

        <form v-if="open" class="space-y-3 border-t border-stone-200 p-4" @submit.prevent="save">
            <div>
                <label class="field-label">Name</label>
                <input v-model="form.name" type="text" class="field">
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>
            <!-- A mode has no scenario, so the Dread tokens are not offered. -->
            <MarkupField v-model="form.text" label="Between scenarios" :rows="4" hint="What happens between scenarios in this mode, in your words." />
            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_placeholder" type="checkbox"> Still a placeholder
            </label>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="btn-ghost text-red-700" @click="destroy">Delete</button>
            </div>
        </form>
    </div>
</template>
