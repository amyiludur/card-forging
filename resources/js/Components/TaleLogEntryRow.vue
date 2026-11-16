<script setup>
import { watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';

/**
 * One thing the campaign log records. Kept on one line, because a log is a
 * list of short names: the kind says how it is written down, the scope whether
 * once for the party or once per character.
 */
const props = defineProps({
    entry: { type: Object, required: true },
    kinds: { type: Array, default: () => [] },
    scopes: { type: Array, default: () => [] },
    first: { type: Boolean, default: false },
    last: { type: Boolean, default: false },
});

// The order is the order the log sheet's rows print in.
const move = (direction) => router.post(`/tale-log-entries/${props.entry.id}/move`, { direction }, { preserveScroll: true });

const kindLabels = { number: 'A number', check: 'A box to tick', note: 'A note', rewards: 'Rewards earned' };
const scopeLabels = { party: 'Whole party', character: 'Each character' };

const fields = (entry) => ({ name: entry.name, kind: entry.kind, scope: entry.scope, is_placeholder: entry.is_placeholder });
const form = useForm(fields(props.entry));

watch(() => props.entry, (entry) => form.defaults(fields(entry)).reset(), { deep: true });

const save = () => form.put(`/tale-log-entries/${props.entry.id}`, { preserveScroll: true });

const destroy = () => {
    if (confirm(`Delete the log entry "${props.entry.name}"?`)) {
        router.delete(`/tale-log-entries/${props.entry.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <form class="flex flex-wrap items-center gap-2 rounded border border-stone-300 bg-white p-2" @submit.prevent="save">
        <input v-model="form.name" type="text" class="field min-w-[12rem] flex-1" aria-label="Name">
        <select v-model="form.kind" class="field w-auto" aria-label="Kind">
            <option v-for="kind in kinds" :key="kind" :value="kind">{{ kindLabels[kind] ?? kind }}</option>
        </select>
        <select v-model="form.scope" class="field w-auto" aria-label="Scope">
            <option v-for="scope in scopes" :key="scope" :value="scope">{{ scopeLabels[scope] ?? scope }}</option>
        </select>
        <label class="flex items-center gap-1 text-xs text-stone-600">
            <input v-model="form.is_placeholder" type="checkbox"> placeholder
        </label>
        <button type="button" class="btn-ghost px-2" :disabled="first" title="Move up" @click="move('up')">↑</button>
        <button type="button" class="btn-ghost px-2" :disabled="last" title="Move down" @click="move('down')">↓</button>
        <button type="submit" class="btn-ghost" :disabled="!form.isDirty || form.processing">Save</button>
        <button type="button" class="btn-ghost text-red-700" @click="destroy">Delete</button>
        <p v-if="form.errors.name" class="field-error w-full">{{ form.errors.name }}</p>
    </form>
</template>
