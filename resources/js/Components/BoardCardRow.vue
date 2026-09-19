<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import TraitInput from './TraitInput.vue';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';

const props = defineProps({
    card: { type: Object, required: true },
    beats: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
});

const open = ref(false);
const zoomed = ref(false);

const form = useForm({
    name: props.card.name,
    qty: props.card.qty,
    health: props.card.health ?? '',
    traits: props.card.traits ?? [],
    text: props.card.text ?? '',
    added_by_beat_id: props.card.added_by_beat_id,
    is_placeholder: props.card.is_placeholder,
});

watch(() => props.card, (card) => form.defaults(card).reset(), { deep: true });

const save = () => form.put(`/board-cards/${props.card.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const destroy = () => {
    if (confirm(`Delete board card ${props.card.name}?`)) {
        router.delete(`/board-cards/${props.card.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-4 p-4">
            <button
                type="button"
                class="card-button shrink-0"
                :aria-label="`View ${card.name} at full size`"
                @click="zoomed = true"
            >
                <CardPreview :card="form.data()" kind="board" :width="120" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="font-serif text-base font-semibold">{{ card.name }}</h3>
                    <span v-if="card.qty > 1" class="text-xs text-stone-500">×{{ card.qty }}</span>
                    <span v-if="card.health" class="rounded bg-red-900 px-1.5 py-0.5 text-[11px] font-bold text-red-50">{{ card.health }} hp</span>
                    <span v-if="card.added_by_beat" class="text-xs text-amber-800">added at beat {{ card.added_by_beat.order }}</span>
                </div>

                <p class="mt-1 text-sm text-stone-700" v-html="card.html" />

                <div class="mt-2 flex flex-wrap gap-1">
                    <span v-for="trait in card.traits" :key="trait" class="rounded border border-stone-300 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-stone-600">{{ trait }}</span>
                </div>

                <button type="button" class="mt-2 text-xs text-stone-500 underline decoration-dotted hover:text-stone-900" @click="open = !open">
                    {{ open ? 'close' : 'edit' }}
                </button>
            </div>
        </div>

        <form v-if="open" class="space-y-3 border-t border-stone-200 bg-stone-50 p-4" @submit.prevent="save">
            <div class="grid gap-3" :class="beats.length ? 'sm:grid-cols-[1fr,5rem,8rem,1fr]' : 'sm:grid-cols-[1fr,5rem,8rem]'">
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field">
                </div>
                <div>
                    <label class="field-label">Setup qty</label>
                    <input v-model.number="form.qty" type="number" min="1" class="field">
                </div>
                <div>
                    <label class="field-label">Health</label>
                    <input v-model="form.health" type="text" class="field" placeholder="3, or 12 per player">
                </div>
                <div v-if="beats.length">
                    <label class="field-label">Added by beat</label>
                    <select v-model="form.added_by_beat_id" class="field">
                        <option :value="null">In the setup</option>
                        <option v-for="beat in beats" :key="beat.id" :value="beat.id">{{ beat.order }}. {{ beat.name }}</option>
                    </select>
                </div>
            </div>

            <MarkupField v-model="form.text" label="Text" :rows="2" />
            <TraitInput v-model="form.traits" :suggestions="suggestions" />

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                Numbers on this card are still placeholders
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="text-sm text-red-700 hover:underline" @click="destroy">Delete</button>
            </div>
        </form>

        <CardZoom v-if="zoomed" :card="form.data()" kind="board" :caption="card.name" @close="zoomed = false" />
    </div>
</template>
