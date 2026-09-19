<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import MarkupField from '../../Components/MarkupField.vue';
import TraitInput from '../../Components/TraitInput.vue';

const props = defineProps({
    // A card belongs to a scenario's base deck or to a module, never both.
    scenario: { type: Object, default: null },
    module: { type: Object, default: null },
    card: { type: Object, default: null },
    cardTypes: { type: Array, default: () => [] },
    beats: { type: Array, default: () => [] },
});

const owner = computed(() => props.module ?? props.scenario);
const ownerQuery = computed(() => (props.module ? `module=${props.module.slug}` : `scenario=${props.scenario?.slug}`));
const listHref = computed(() => `/cards?${ownerQuery.value}`);

const blankFace = (half) => ({ half, card_type_id: null, text: '' });

const initialFaces = () => {
    if (!props.card) return [blankFace('single')];

    return props.card.faces.map((face) => ({
        half: face.half,
        card_type_id: face.card_type_id,
        text: face.text ?? '',
    }));
};

const form = useForm({
    name: props.card?.name ?? '',
    qty: props.card?.qty ?? 1,
    layout: props.card?.layout ?? 'single',
    omen_cost: props.card?.omen_cost ?? 1,
    omen_is_x: props.card?.omen_is_x ?? false,
    traits: props.card?.traits ?? [],
    added_by_beat_id: props.card?.added_by_beat_id ?? null,
    arrow: props.card?.arrow ?? 'top',
    notes: props.card?.notes ?? '',
    is_placeholder: props.card?.is_placeholder ?? true,
    faces: initialFaces(),
});

// The layout decides how many faces a card has and whether it carries an arrow.
watch(
    () => form.layout,
    (layout) => {
        if (layout === 'split') {
            const [first, second] = form.faces;
            form.faces = [
                { ...blankFace('top'), ...first, half: 'top' },
                { ...blankFace('bottom'), ...second, half: 'bottom' },
            ];
        } else {
            const [first] = form.faces;
            form.faces = [{ ...blankFace('single'), ...first, half: 'single' }];
        }

        if (layout === 'x-cost') {
            form.omen_is_x = true;
        }
    }
);

watch(
    () => form.omen_is_x,
    (isX) => {
        if (!isX && form.layout === 'x-cost') form.layout = 'single';
    }
);

const typeName = (id) => props.cardTypes.find((type) => type.id === id)?.name ?? null;

// What the preview and the print sheet both draw from.
const previewCard = computed(() => ({
    ...form.data(),
    omen_label: form.omen_is_x ? 'X' : String(form.omen_cost ?? 0),
    added_by_beat: props.beats.find((beat) => beat.id === form.added_by_beat_id) ?? null,
    set_icon: props.module?.set_icon ?? null,
    faces: form.faces.map((face) => ({ ...face, type_name: typeName(face.card_type_id) })),
}));

const submit = () => {
    if (props.card) {
        form.put(`/cards/${props.card.id}`, { preserveScroll: true });
    } else {
        form.post(`/cards?${ownerQuery.value}`);
    }
};

const destroy = () => {
    if (confirm(`Delete ${props.card.name}?`)) {
        router.delete(`/cards/${props.card.id}`);
    }
};

const duplicate = () => router.post(`/cards/${props.card.id}/duplicate`);

const zoomed = ref(false);
</script>

<template>
    <Head :title="card ? card.name : 'New card'" />

    <PageHeader
        :title="card ? card.name : 'New card'"
        :subtitle="`${owner?.name ?? ''} · ${module ? 'module' : 'entity deck'}`"
    >
        <template #actions>
            <Link :href="listHref" class="btn-ghost">Back to cards</Link>
            <button v-if="card" type="button" class="btn-ghost" @click="duplicate">Duplicate</button>
        </template>
    </PageHeader>

    <form class="grid gap-8 px-6 py-6 lg:grid-cols-[minmax(0,1fr),18rem]" @submit.prevent="submit">
        <div class="space-y-5">
            <div class="grid gap-4 sm:grid-cols-[1fr,5rem]">
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field">
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>
                <div>
                    <label class="field-label">Copies</label>
                    <input v-model.number="form.qty" type="number" min="1" class="field">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label">Layout</label>
                    <select v-model="form.layout" class="field">
                        <option value="single">Single effect</option>
                        <option value="split">Split (two halves)</option>
                        <option value="x-cost">X-cost</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Omen cost</label>
                    <input
                        v-if="!form.omen_is_x"
                        v-model.number="form.omen_cost"
                        type="number"
                        min="0"
                        class="field"
                    >
                    <p v-else class="field flex items-center bg-stone-100 font-mono italic text-stone-600">X</p>
                    <label class="mt-1 flex items-center gap-1.5 text-xs text-stone-600">
                        <input v-model="form.omen_is_x" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        Cost is X (drains the remaining omen)
                    </label>
                </div>

                <div v-if="!module">
                    <label class="field-label">Added by beat</label>
                    <select v-model="form.added_by_beat_id" class="field">
                        <option :value="null">In the base deck</option>
                        <option v-for="beat in beats" :key="beat.id" :value="beat.id">{{ beat.order }}. {{ beat.name }}</option>
                    </select>
                </div>
                <div v-else>
                    <label class="field-label">Set icon</label>
                    <p class="field flex items-center bg-stone-100 font-mono text-stone-600">{{ module.set_icon || '—' }}</p>
                    <p class="field-hint">From the module, printed on every card in it.</p>
                </div>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <label class="field-label">Arrow <span class="text-xs font-normal text-stone-500">(required)</span></label>
                <div class="flex flex-wrap items-center gap-4">
                    <label class="flex items-center gap-1.5 text-sm">
                        <input v-model="form.arrow" type="radio" value="top" class="border-stone-400 text-amber-700 focus:ring-amber-600"> Top half
                    </label>
                    <label class="flex items-center gap-1.5 text-sm">
                        <input v-model="form.arrow" type="radio" value="bottom" class="border-stone-400 text-amber-700 focus:ring-amber-600"> Bottom half
                    </label>
                </div>
                <p class="field-hint">
                    Printed on the right edge. It points at the top or bottom half of the card <strong>to its right</strong>
                    in the storyline, so it decides which half of the <em>next</em> split card resolves — not this card's own.
                    A Redirect token overrides it in play.
                </p>
                <p v-if="form.errors.arrow" class="field-error">{{ form.errors.arrow }}</p>
            </div>

            <div class="space-y-4">
                <div
                    v-for="(face, index) in form.faces"
                    :key="face.half"
                    class="rounded-lg border border-stone-300 bg-white p-4"
                >
                    <p v-if="form.layout === 'split'" class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">
                        {{ face.half }} half
                    </p>

                    <div class="mb-3">
                        <label class="field-label">Type</label>
                        <select v-model="face.card_type_id" class="field">
                            <option :value="null">No type</option>
                            <option v-for="type in cardTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                        </select>
                        <p class="field-hint">
                            {{ cardTypes.find((t) => t.id === face.card_type_id)?.description ?? 'Types let character cards target this card.' }}
                        </p>
                    </div>

                    <MarkupField
                        v-model="face.text"
                        label="Effect"
                        :rows="3"
                        :error="form.errors[`faces.${index}.text`]"
                    />
                </div>
            </div>

            <TraitInput v-model="form.traits" :suggestions="owner?.traits ?? []" />

            <div>
                <label class="field-label">Designer notes (not printed)</label>
                <textarea v-model="form.notes" rows="2" class="field" />
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                Numbers on this card are still placeholders
            </label>

            <div class="flex items-center gap-4 border-t border-stone-200 pt-4">
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ card ? 'Save card' : 'Create card' }}
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-700">Saved.</span>
                <button v-if="card" type="button" class="ml-auto text-sm text-red-700 hover:underline" @click="destroy">Delete card</button>
            </div>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="mb-2 text-xs font-semibold uppercase tracking-widest text-stone-500">Preview</p>
            <button
                type="button"
                class="card-button"
                aria-label="View this card at full size"
                @click="zoomed = true"
            >
                <CardPreview :card="previewCard" kind="entity" :width="240" />
            </button>
            <p class="mt-3 text-xs leading-relaxed text-stone-600">
                This is the same layout the print sheet uses, at {{ (240 / 63.5).toFixed(1) }}× print size.
                Icons and tunable numbers render live as you type. Click it for a bigger look.
            </p>

            <CardZoom
                v-if="zoomed"
                :card="previewCard"
                kind="entity"
                :caption="previewCard.name || 'Untitled card'"
                @close="zoomed = false"
            />
        </aside>
    </form>
</template>
