<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';

const props = defineProps({
    action: { type: Object, required: true },
});

const open = ref(false);
const zoomed = ref(false);

const form = useForm({
    name: props.action.name,
    effect: props.action.effect ?? '',
    gold_cost: props.action.gold_cost,
    omen: props.action.omen,
    note: props.action.note ?? '',
});

// A district always belongs to a scenario, so its Dread rule is never in doubt
// the way a module card's is.
const dreadRule = computed(() => props.action.dread_rule ?? '');
const dreadAmount = computed(() => props.action.dread_amount ?? '');

// form.data() holds only what is editable, so the scenario's two are put back
// for the preview. The preview follows the form, so an edit shows as it is typed.
const previewAction = computed(() => ({
    ...form.data(),
    id: props.action.id,
    dread_rule: props.action.dread_rule ?? null,
    dread_amount: props.action.dread_amount ?? null,
}));

watch(() => props.action, (action) => form.defaults({
    name: action.name,
    effect: action.effect ?? '',
    gold_cost: action.gold_cost,
    omen: action.omen,
    note: action.note ?? '',
}).reset(), { deep: true });

// An empty gold field is no cost at all, not a cost of 0.
const save = () => form
    .transform((data) => ({ ...data, gold_cost: data.gold_cost === '' ? null : data.gold_cost }))
    .put(`/town-actions/${props.action.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const destroy = () => {
    if (confirm(`Delete ${props.action.name}?`)) {
        router.delete(`/town-actions/${props.action.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-4 p-4">
            <button
                type="button"
                class="card-button shrink-0"
                :aria-label="`View ${action.name} at full size`"
                @click="zoomed = true"
            >
                <CardPreview :card="previewAction" kind="town" :width="120" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="font-serif text-base font-semibold">{{ action.name }}</h3>
                    <span class="text-xs text-stone-500">
                        {{ action.gold_cost ?? '—' }} gold · {{ action.omen }} omen
                    </span>
                </div>

                <p class="mt-1 text-sm text-stone-700" v-html="action.html" />
                <p v-if="action.note" class="mt-1 text-xs italic text-stone-500" v-html="action.note_html" />

                <button type="button" class="mt-2 text-xs text-stone-500 underline decoration-dotted hover:text-stone-900" @click="open = !open">
                    {{ open ? 'close' : 'edit' }}
                </button>
            </div>
        </div>

        <form v-if="open" class="space-y-3 border-t border-stone-200 bg-stone-50 p-4" @submit.prevent="save">
            <div class="grid gap-3 sm:grid-cols-[1fr,6rem,6rem]">
                <div>
                    <label class="field-label">District</label>
                    <input v-model="form.name" type="text" class="field">
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="field-label">Gold</label>
                    <input v-model.number="form.gold_cost" type="number" min="0" class="field" placeholder="none">
                    <p v-if="form.errors.gold_cost" class="field-error">{{ form.errors.gold_cost }}</p>
                </div>
                <div>
                    <label class="field-label">Omen</label>
                    <input v-model.number="form.omen" type="number" min="0" class="field">
                    <p v-if="form.errors.omen" class="field-error">{{ form.errors.omen }}</p>
                </div>
            </div>

            <MarkupField v-model="form.effect" label="Effect" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" :card-name="form.name ?? ''" />
            <p v-if="form.errors.effect" class="field-error">{{ form.errors.effect }}</p>

            <MarkupField v-model="form.note" label="Note" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" :card-name="form.name ?? ''" />

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="text-sm text-red-700 hover:underline" @click="destroy">Delete</button>
            </div>
        </form>

        <CardZoom v-if="zoomed" :card="previewAction" kind="town" :caption="action.name" @close="zoomed = false" />
    </div>
</template>
