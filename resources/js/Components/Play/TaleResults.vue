<script setup>
import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * The end of a scenario of a playthrough: what it leaves in the Tale's log.
 * One field per party entry, and one per character entry for each character
 * at the table, in the kind the entry is written in. Saving sends the whole
 * step; recording it again replaces it. The server checks every value against
 * the Tale's own entries and rewards, so this form only has to lay them out.
 */
const props = defineProps({
    tale: { type: Object, required: true },
    // The characters at the table, once each: [{ slug, name }].
    party: { type: Array, default: () => [] },
});

const emit = defineEmits(['close']);

const stored = props.tale.step.values ?? {};
const empty = (kind) => ({ number: '', check: false, note: '', rewards: [] })[kind] ?? '';
const from = (kind, value) => (value === null || value === undefined ? empty(kind) : value);

const partyEntries = props.tale.entries.filter((e) => e.scope === 'party');
const characterEntries = props.tale.entries.filter((e) => e.scope === 'character');

// Everyone at the table, plus anyone this step already recorded who is not
// sitting down now — saving must not quietly drop what was written.
const characters = [...new Set([
    ...props.party.map((m) => m.slug),
    ...characterEntries.flatMap((e) => Object.keys(stored[e.id] ?? {})),
])];

const values = reactive({});

for (const entry of partyEntries) {
    values[entry.id] = from(entry.kind, stored[entry.id]);
}

for (const entry of characterEntries) {
    values[entry.id] = Object.fromEntries(characters.map((slug) => [slug, from(entry.kind, stored[entry.id]?.[slug])]));
}

const nameOf = (slug) => props.party.find((m) => m.slug === slug)?.name ?? slug;

const toggleReward = (list, id) => {
    const at = list.indexOf(id);
    if (at === -1) list.push(id);
    else list.splice(at, 1);
};

const saving = ref(false);
const error = ref(null);

const save = () => {
    saving.value = true;
    router.put(`/tale-runs/${props.tale.run.id}/results/${props.tale.step.id}`, { values }, {
        onError: (errors) => { error.value = errors.values ?? 'Not saved.'; },
        onFinish: () => { saving.value = false; },
    });
};
</script>

<template>
    <div class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/60 p-4" @click.self="emit('close')">
        <form class="w-full max-w-2xl rounded-lg bg-stone-50 p-5 text-stone-800 shadow-2xl" @submit.prevent="save">
            <div class="flex items-baseline gap-3">
                <div class="min-w-0 flex-1">
                    <p class="field-micro">{{ tale.tale.name }} · {{ tale.run.name }}</p>
                    <h2 class="font-serif text-xl font-semibold">Record results: {{ tale.step.number }}. {{ tale.step.scenario }}</h2>
                </div>
                <button type="button" class="rounded px-2 py-1 text-sm text-stone-500 hover:bg-stone-200" aria-label="Close" @click="emit('close')">✕</button>
            </div>

            <!-- Read when the scenario ends. -->
            <div v-if="tale.step.outro_html" class="rules-prose mt-3 border-l-2 border-stone-300 pl-3 text-sm" v-html="tale.step.outro_html" />

            <p v-if="!tale.entries.length" class="mt-4 text-sm text-stone-600">This Tale's log has no entries yet. Saving records that the scenario was played.</p>

            <section v-if="partyEntries.length" class="mt-4 space-y-3">
                <h3 class="field-label">The party</h3>
                <div v-for="entry in partyEntries" :key="entry.id">
                    <span class="text-sm font-medium" v-html="entry.name_html" />
                    <input v-if="entry.kind === 'number'" v-model="values[entry.id]" type="number" step="1" class="field mt-1 w-32">
                    <input v-else-if="entry.kind === 'check'" v-model="values[entry.id]" type="checkbox" class="ml-2">
                    <textarea v-else-if="entry.kind === 'note'" v-model="values[entry.id]" rows="2" class="field mt-1" />
                    <div v-else-if="entry.kind === 'rewards'" class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                        <label v-for="card in tale.rewardCards" :key="card.id" class="inline-flex items-center gap-1.5 text-sm">
                            <input type="checkbox" :checked="values[entry.id].includes(card.id)" @change="toggleReward(values[entry.id], card.id)">
                            {{ card.name }}
                        </label>
                        <span v-if="!tale.rewardCards.length" class="text-xs text-stone-500">The Tale has no reward cards yet.</span>
                    </div>
                </div>
            </section>

            <section v-if="characterEntries.length" class="mt-5 space-y-4">
                <div v-for="slug in characters" :key="slug" class="rounded border border-stone-200 bg-white p-3">
                    <h3 class="font-serif text-base font-semibold">{{ nameOf(slug) }}</h3>
                    <div v-for="entry in characterEntries" :key="entry.id" class="mt-2">
                        <span class="text-sm font-medium" v-html="entry.name_html" />
                        <input v-if="entry.kind === 'number'" v-model="values[entry.id][slug]" type="number" step="1" class="field mt-1 w-32">
                        <input v-else-if="entry.kind === 'check'" v-model="values[entry.id][slug]" type="checkbox" class="ml-2">
                        <textarea v-else-if="entry.kind === 'note'" v-model="values[entry.id][slug]" rows="2" class="field mt-1" />
                        <div v-else-if="entry.kind === 'rewards'" class="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                            <label v-for="card in tale.rewardCards" :key="card.id" class="inline-flex items-center gap-1.5 text-sm">
                                <input type="checkbox" :checked="values[entry.id][slug].includes(card.id)" @change="toggleReward(values[entry.id][slug], card.id)">
                                {{ card.name }}
                            </label>
                            <span v-if="!tale.rewardCards.length" class="text-xs text-stone-500">The Tale has no reward cards yet.</span>
                        </div>
                    </div>
                </div>
                <p v-if="!characters.length" class="text-sm text-stone-500">Nobody is seated, so there is no one to write character entries for.</p>
            </section>

            <p v-if="error" class="field-error mt-3">{{ error }}</p>

            <div class="mt-5 flex items-center gap-2">
                <button type="submit" class="btn-primary" :disabled="saving">Save to the log</button>
                <button type="button" class="btn-ghost" @click="emit('close')">Not yet</button>
                <span class="ml-auto text-xs text-stone-500">Saving takes you to the Tale's playthroughs.</span>
            </div>
        </form>
    </div>
</template>
