<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';
import MarkupField from './MarkupField.vue';
import TraitInput from './TraitInput.vue';
import ArtField from './ArtField.vue';
import { artFields } from '../cardArt';

/** One of a Tale's own cards, with the face it prints beside the fields. */
const props = defineProps({
    card: { type: Object, required: true },
});

const open = ref(false);
const zoomed = ref(false);

const fields = (card) => ({
    name: card.name,
    qty: card.qty,
    type_line: card.type_line ?? '',
    text: card.text ?? '',
    flavour: card.flavour ?? '',
    ...artFields(card),
    traits: card.traits ?? [],
    is_placeholder: card.is_placeholder,
});

const form = useForm(fields(props.card));

watch(() => props.card, (card) => form.defaults(fields(card)).reset(), { deep: true });

// The preview follows the form, so an edit shows as it is typed.
const preview = computed(() => ({ ...form.data(), id: props.card.id, set_icon: props.card.set_icon }));

const save = () => form.put(`/tale-cards/${props.card.id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const destroy = () => {
    if (confirm(`Delete ${props.card.name}?`)) {
        router.delete(`/tale-cards/${props.card.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="rounded-lg border border-stone-300 bg-white">
        <div class="flex items-start gap-4 p-4">
            <button type="button" class="card-button shrink-0" :aria-label="`View ${card.name} at full size`" @click="zoomed = true">
                <CardPreview :card="preview" kind="tale" :width="120" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="font-serif text-base font-semibold">{{ card.name }}</h3>
                    <span class="text-xs text-stone-500">{{ card.type_line || 'Tale' }} · ×{{ card.qty }}</span>
                </div>

                <form v-if="open" class="mt-3 space-y-3" @submit.prevent="save">
                    <div class="grid gap-3 sm:grid-cols-[1fr,10rem,5rem]">
                        <div>
                            <label class="field-label">Name</label>
                            <input v-model="form.name" type="text" class="field">
                            <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                        </div>
                        <div>
                            <label class="field-label">Type line</label>
                            <input v-model="form.type_line" type="text" maxlength="40" class="field" placeholder="Tale">
                        </div>
                        <div>
                            <label class="field-label">Copies</label>
                            <input v-model.number="form.qty" type="number" min="1" max="20" class="field">
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Flavour</label>
                        <input v-model="form.flavour" type="text" class="field">
                    </div>
                    <div>
                        <label class="field-label">Text</label>
                        <MarkupField v-model="form.text" :rows="4" />
                    </div>
                    <ArtField :form="form" kind="tale" :name="form.name" :missing="card.art_missing ?? false" />
                    <div>
                        <label class="field-label">Traits</label>
                        <TraitInput v-model="form.traits" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="form.is_placeholder" type="checkbox"> Still a placeholder
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                        <button type="button" class="btn-ghost text-red-700" @click="destroy">Delete</button>
                    </div>
                </form>
            </div>

            <button v-if="!open" type="button" class="btn-ghost shrink-0" @click="open = true">Edit</button>
        </div>

        <CardZoom v-if="zoomed" :card="preview" kind="tale" @close="zoomed = false" />
    </div>
</template>
