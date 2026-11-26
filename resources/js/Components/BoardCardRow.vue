<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import MarkupField from './MarkupField.vue';
import TraitInput from './TraitInput.vue';
import CardPreview from './CardPreview.vue';
import CardZoom from './CardZoom.vue';
import ArtField from './ArtField.vue';
import { artFields } from '../cardArt';

const props = defineProps({
    card: { type: Object, required: true },
    beats: { type: Array, default: () => [] },
    suggestions: { type: Array, default: () => [] },
    // The deal groups this scenario's cards already use, offered so one group
    // is not typed two ways.
    groups: { type: Array, default: () => [] },
});

const open = ref(false);
const zoomed = ref(false);

const form = useForm({
    name: props.card.name,
    qty: props.card.qty,
    // Empty prints what setup puts out (at least one).
    print_qty: props.card.print_qty ?? null,
    health: props.card.health ?? '',
    traits: props.card.traits ?? [],
    text: props.card.text ?? '',
    added_by_beat_id: props.card.added_by_beat_id,
    start: props.card.start ?? 'board',
    deal_group: props.card.deal_group ?? '',
    flavour: props.card.flavour ?? '',
    ...artFields(props.card),
    is_placeholder: props.card.is_placeholder,
});

// What {dreadRule} writes onto this card. Null for a module's board card: it
// has no one scenario, so it has no one rule. A scenario card whose Dread
// effect is not written yet still offers the token and reports the gap.
const dreadRule = computed(() => (props.card.module_id ? null : props.card.dread_rule ?? ''));

// The same for {dreadAmount}, the Dread that scenario starts on.
const dreadAmount = computed(() => (props.card.module_id ? null : props.card.dread_amount ?? ''));
const omenAmount = computed(() => (props.card.module_id ? null : props.card.omen_amount ?? ''));

// form.data() holds only what is editable, so the two are put back for the
// preview: they are the scenario's, not the card's.
const previewCard = computed(() => ({
    ...form.data(),
    dread_rule: props.card.dread_rule ?? null,
    dread_amount: props.card.dread_amount ?? null,
    omen_amount: props.card.omen_amount ?? null,
}));

// How the card comes into a game, as one choice: on the board at setup, dealt
// one to each player (the rest kept aside), or out with a beat. A beat's card
// is never dealt as well — the server holds to that too.
const startsAs = computed({
    get: () => (form.added_by_beat_id ? `beat:${form.added_by_beat_id}` : form.start),
    set: (value) => {
        if (value.startsWith('beat:')) {
            form.added_by_beat_id = Number(value.slice(5));
            form.start = 'board';
        } else {
            form.added_by_beat_id = null;
            form.start = value;
        }
        // Only a dealt card is in a deal; the server drops it otherwise too.
        if (form.start !== 'dealt') form.deal_group = '';
    },
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
                <CardPreview :card="previewCard" kind="board" :width="120" />
            </button>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-baseline gap-2">
                    <h3 class="font-serif text-base font-semibold">{{ card.name }}</h3>
                    <span v-if="card.qty !== 1" class="text-xs text-stone-500">×{{ card.qty }}<template v-if="card.qty === 0"> at setup</template></span>
                    <span v-if="card.print_qty != null" class="text-xs text-stone-500">prints {{ card.print_qty }}</span>
                    <span v-if="card.health" class="rounded bg-red-900 px-1.5 py-0.5 text-[11px] font-bold text-red-50">{{ card.health }} hp</span>
                    <span v-if="card.added_by_beat" class="text-xs text-amber-800">added at beat {{ card.added_by_beat.order }}</span>
                    <span v-if="card.start === 'dealt'" class="text-xs text-amber-800">
                        dealt to the players ·
                        <template v-if="card.deal_group">{{ card.deal_group }}</template>
                        <span v-else class="text-red-700" title="Dealt on its own, as an unnamed group. Give it a group name to deal it with others.">no group</span>
                    </span>
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
            <div class="grid gap-3" :class="card.module_id ? 'sm:grid-cols-[1fr,5rem,5rem,8rem]' : startsAs === 'dealt' ? 'sm:grid-cols-[1fr,5rem,5rem,8rem,1fr,1fr]' : 'sm:grid-cols-[1fr,5rem,5rem,8rem,1fr]'">
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field">
                </div>
                <div>
                    <label class="field-label">Setup qty</label>
                    <input v-model.number="form.qty" type="number" min="0" class="field">
                </div>
                <div>
                    <label class="field-label">Print qty</label>
                    <input v-model.number="form.print_qty" type="number" min="1" class="field" :placeholder="String(Math.max(1, Number(form.qty) || 0))" title="Copies to print. Empty prints the setup qty (at least one).">
                </div>
                <div>
                    <label class="field-label">Health</label>
                    <input v-model="form.health" type="text" class="field" placeholder="3, or 12 per player">
                </div>
                <!-- A module's board card comes out when a card says so: it has no start to pick. -->
                <div v-if="!card.module_id">
                    <label class="field-label">Starts</label>
                    <select v-model="startsAs" class="field">
                        <option value="board">On the board</option>
                        <option value="dealt">Dealt to the players</option>
                        <option v-for="beat in beats" :key="beat.id" :value="`beat:${beat.id}`">Added at beat {{ beat.order }}. {{ beat.name }}</option>
                    </select>
                </div>
                <!-- Cards with the same group are dealt together, one to each player. -->
                <div v-if="startsAs === 'dealt'">
                    <label class="field-label">Group</label>
                    <input v-model="form.deal_group" type="text" class="field" :list="`deal-groups-${card.id}`" placeholder="Townsfolk">
                    <datalist :id="`deal-groups-${card.id}`">
                        <option v-for="group in groups" :key="group" :value="group" />
                    </datalist>
                </div>
            </div>

            <MarkupField v-model="form.text" label="Text" :rows="2" :dread-rule="dreadRule" :dread-amount="dreadAmount" :omen-amount="omenAmount" :card-name="form.name ?? ''" />
            <TraitInput v-model="form.traits" :suggestions="suggestions" />

            <div>
                <label class="field-label">Flavour (printed after the rules text)</label>
                <textarea v-model="form.flavour" rows="2" class="field" />
            </div>

            <ArtField :form="form" kind="board" :name="form.name" :missing="card.art_missing ?? false" />

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                Numbers on this card are still placeholders
            </label>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                <button type="button" class="text-sm text-red-700 hover:underline" @click="destroy">Delete</button>
            </div>
        </form>

        <CardZoom v-if="zoomed" :card="previewCard" kind="board" :pool-key="`board:${card.id}`" :caption="card.name" @close="zoomed = false" />
    </div>
</template>
