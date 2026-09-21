<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';
import ScaledNumberField from './ScaledNumberField.vue';

const props = defineProps({
    beat: { type: Object, required: true },
});

const open = ref(false);
const zoomed = ref(false);

const form = useForm({
    order: props.beat.order,
    name: props.beat.name,
    flavour: props.beat.flavour ?? '',
    on_reach: props.beat.on_reach ?? '',
    advance: props.beat.advance ?? '',
    on_advance: props.beat.on_advance ?? '',
    dread_change: props.beat.dread_change ?? 0,
    dread_change_equation: props.beat.dread_change_equation ?? null,
});

// A beat always belongs to a scenario, so {dreadRule} and {dreadAmount} are
// always on offer here, even before the scenario's Dread effect is written.
const dreadRule = computed(() => props.beat.dread_rule ?? '');
const dreadAmount = computed(() => props.beat.dread_amount ?? '');

// form.data() holds only what is editable, so the two are put back for the
// preview: they are the scenario's, not the beat's.
const previewBeat = computed(() => ({
    ...form.data(),
    dread_rule: props.beat.dread_rule ?? null,
    dread_amount: props.beat.dread_amount ?? null,
}));

watch(() => props.beat, (beat) => form.defaults(beat).reset(), { deep: true });

const save = () => form.put(`/beats/${props.beat.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const destroy = () => {
    if (confirm(`Delete beat ${props.beat.order}, ${props.beat.name}?`)) {
        router.delete(`/beats/${props.beat.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-4 p-4">
            <button
                type="button"
                class="card-button shrink-0"
                :aria-label="`View beat ${beat.order} at full size`"
                @click="zoomed = true"
            >
                <CardPreview :card="previewBeat" kind="beat" :width="132" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex items-baseline gap-2">
                    <span class="rounded bg-amber-900 px-1.5 py-0.5 text-xs font-bold text-amber-50">Beat {{ beat.order }}</span>
                    <h3 class="font-serif text-lg font-semibold">{{ beat.name }}</h3>
                    <span v-if="beat.dread_change_equation" class="text-xs font-semibold text-red-700">Dread {{ beat.dread_change_equation }}</span>
                    <span v-else-if="beat.dread_change" class="text-xs font-semibold text-red-700">Dread {{ beat.dread_change > 0 ? '+' : '' }}{{ beat.dread_change }}</span>
                </div>

                <p v-if="beat.flavour" class="mt-1 font-serif text-sm italic text-stone-600">{{ beat.flavour }}</p>

                <dl class="mt-2 space-y-1 text-sm">
                    <div v-if="beat.on_reach"><dt class="field-micro">When reached</dt><dd v-html="beat.html.on_reach" /></div>
                    <div v-if="beat.advance"><dt class="field-micro">Advances when</dt><dd v-html="beat.html.advance" /></div>
                    <div v-if="beat.on_advance"><dt class="field-micro">On advancing</dt><dd v-html="beat.html.on_advance" /></div>
                </dl>

                <button type="button" class="mt-2 text-xs text-stone-500 underline decoration-dotted hover:text-stone-900" @click="open = !open">
                    {{ open ? 'close' : 'edit beat' }}
                </button>
            </div>
        </div>

        <form v-if="open" class="space-y-3 border-t border-stone-200 bg-stone-50 p-4" @submit.prevent="save">
            <div class="grid gap-3 sm:grid-cols-[5rem,1fr,7rem]">
                <div>
                    <label class="field-label">Order</label>
                    <input v-model.number="form.order" type="number" min="1" class="field">
                </div>
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field">
                </div>
                <ScaledNumberField
                    v-model:value="form.dread_change"
                    v-model:equation="form.dread_change_equation"
                    label="Dread change"
                    :error="form.errors.dread_change_equation || form.errors.dread_change"
                />
            </div>

            <div>
                <label class="field-label">Flavour</label>
                <textarea v-model="form.flavour" rows="2" class="field" />
            </div>

            <MarkupField v-model="form.on_reach" label="On reach" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" />
            <MarkupField v-model="form.advance" label="Advance trigger" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" />
            <MarkupField v-model="form.on_advance" label="On advance" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" />

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save beat</button>
                <button type="button" class="text-sm text-red-700 hover:underline" @click="destroy">Delete</button>
            </div>
        </form>

        <CardZoom
            v-if="zoomed"
            :card="previewBeat"
            kind="beat"
            :caption="`Beat ${beat.order} · ${beat.name}`"
            @close="zoomed = false"
        />
    </div>
</template>
