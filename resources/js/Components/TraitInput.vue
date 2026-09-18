<script setup>
import { ref } from 'vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
    label: { type: String, default: 'Traits' },
});

const emit = defineEmits(['update:modelValue']);
const draft = ref('');

const add = (value) => {
    const trait = (value ?? draft.value).trim();
    if (!trait || props.modelValue.includes(trait)) {
        draft.value = '';
        return;
    }
    emit('update:modelValue', [...props.modelValue, trait]);
    draft.value = '';
};

const remove = (trait) => emit('update:modelValue', props.modelValue.filter((t) => t !== trait));
</script>

<template>
    <div>
        <label class="mb-1 block text-sm font-medium text-stone-700">{{ label }}</label>

        <div class="flex flex-wrap items-center gap-1.5 rounded border border-stone-300 bg-white p-1.5">
            <span
                v-for="trait in modelValue"
                :key="trait"
                class="inline-flex items-center gap-1 rounded bg-stone-800 px-1.5 py-0.5 text-xs font-semibold uppercase tracking-wide text-stone-100"
            >
                {{ trait }}
                <button type="button" class="text-stone-400 hover:text-white" @click="remove(trait)">×</button>
            </span>

            <input
                v-model="draft"
                type="text"
                placeholder="add a trait…"
                class="min-w-24 flex-1 border-0 p-0.5 text-sm focus:ring-0"
                @keydown.enter.prevent="add()"
                @keydown.,.prevent="add()"
                @blur="add()"
            >
        </div>

        <div v-if="suggestions.length" class="mt-1 flex flex-wrap gap-1">
            <button
                v-for="trait in suggestions.filter((t) => !modelValue.includes(t))"
                :key="trait"
                type="button"
                class="rounded border border-stone-300 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-stone-600 hover:border-stone-500"
                @click="add(trait)"
            >
                + {{ trait }}
            </button>
        </div>
    </div>
</template>
