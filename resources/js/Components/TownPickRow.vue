<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';
import Icon from './Icon.vue';

// A common district in this scenario's town. The district is written once at
// /town and edited there; what belongs to this scenario is the line it adds.
const props = defineProps({
    action: { type: Object, required: true },
});

const open = ref(false);
const zoomed = ref(false);

const form = useForm({ extra: props.action.extra ?? '' });

watch(() => props.action.extra, (extra) => form.defaults({ extra: extra ?? '' }).reset());

// The face that prints: the district's own note, then this scenario's line.
// The same join CardPresenter::townAction() makes, so the preview follows the
// form as it is typed and still matches the printed card.
const previewAction = computed(() => ({
    ...props.action,
    note: [props.action.own_note ?? '', form.extra ?? ''].filter((s) => s.trim() !== '').join('\n') || props.action.own_note,
}));

const save = () => form.put(`/town-picks/${props.action.pick_id}`, { preserveScroll: true, onSuccess: () => (open.value = false) });

const remove = () => {
    if (confirm(`Take ${props.action.name} out of this town? The district itself stays in the common library.`)) {
        router.delete(`/town-picks/${props.action.pick_id}`, { preserveScroll: true });
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
                    <span class="rounded bg-stone-100 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-stone-600">common</span>
                    <span class="text-xs text-stone-500">
                        {{ action.gold_cost ?? '—' }} gold · {{ action.omen }} omen
                    </span>
                </div>

                <p class="mt-1 text-sm text-stone-700" v-html="action.html" />
                <p v-if="action.note" class="mt-1 text-xs italic text-stone-500" v-html="action.note_html" />

                <div class="mt-2 flex items-center gap-3 text-xs text-stone-500">
                    <button type="button" class="underline decoration-dotted hover:text-stone-900" @click="open = !open">
                        {{ open ? 'close' : (action.extra ? 'edit this town’s line' : 'add a line for this town') }}
                    </button>
                    <Link href="/town" class="inline-flex items-center gap-1 underline decoration-dotted hover:text-stone-900">
                        <Icon name="town" /> edit the district
                    </Link>
                </div>
            </div>
        </div>

        <form v-if="open" class="space-y-3 border-t border-stone-200 bg-stone-50 p-4" @submit.prevent="save">
            <MarkupField
                v-model="form.extra"
                label="This town’s line (printed after the district’s note)"
                :rows="2"
                :dread-rule="action.dread_rule ?? ''"
                :dread-amount="action.dread_amount ?? ''"
                :omen-amount="action.omen_amount ?? ''"
                :card-name="action.name ?? ''"
            />
            <p v-if="form.errors.extra" class="field-error">{{ form.errors.extra }}</p>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="text-sm text-red-700 hover:underline" @click="remove">Take out of this town</button>
            </div>
        </form>

        <CardZoom v-if="zoomed" :card="previewAction" kind="town" :caption="action.name" @close="zoomed = false" />
    </div>
</template>
