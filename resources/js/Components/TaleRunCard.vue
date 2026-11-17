<script setup>
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
import TaleResults from './Play/TaleResults.vue';

/**
 * One playthrough of a Tale: its mode, what to play next, and its log so far
 * laid out the way the printed log sheet is — a column per scenario, the
 * party's rows, then a block per character.
 */
const props = defineProps({
    run: { type: Object, required: true },
    modes: { type: Array, default: () => [] },
    // What the Record results form needs to correct a recorded scenario.
    tale: { type: Object, required: true },
    entries: { type: Array, default: () => [] },
    rewardCards: { type: Array, default: () => [] },
});

// Correcting a recorded scenario: the table's own form, opened here. Recording
// a step again replaces it, so this is the same request the table makes.
const editing = ref(null);
const editTale = computed(() => {
    const step = editing.value;
    if (!step) return null;

    return {
        run: { id: props.run.id, name: props.run.name },
        tale: { slug: props.tale.slug, name: props.tale.name },
        step: { id: step.id, number: step.number, scenario: step.scenario, outro_html: '', values: props.run.results?.[step.id] ?? {} },
        entries: props.entries,
        rewardCards: props.rewardCards.filter((card) => card.role === 'reward'),
    };
});
// Whoever this playthrough has recorded anything for.
const recordedParty = computed(() => props.run.grid.characters.map((c) => ({ slug: c.slug, name: c.name })));

const modeId = ref(props.run.mode?.id ?? '');

// The mode can change on a run; the log records results, not the mode.
const changeMode = () => router.put(`/tale-runs/${props.run.id}`, {
    name: props.run.name,
    tale_mode_id: modeId.value === '' ? null : modeId.value,
}, { preserveScroll: true });

const destroy = () => {
    if (confirm(`Delete the playthrough "${props.run.name}" and its log?`)) {
        router.delete(`/tale-runs/${props.run.id}`, { preserveScroll: true });
    }
};

// An entry's name is card markup, the same as on the printed log sheet.
const page = usePage();
const name = (text) => renderMarkup(text ?? '', {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
});

const steps = computed(() => props.run.grid.steps);
const hasLog = computed(() => props.run.grid.party.length || props.run.grid.characters.length);
</script>

<template>
    <article class="rounded-lg border border-stone-300 bg-white">
        <header class="flex flex-wrap items-center gap-3 border-b border-stone-200 p-4">
            <div class="min-w-0 flex-1">
                <h3 class="font-serif text-base font-semibold">{{ run.name }}</h3>
                <p class="text-xs text-stone-500">Started {{ run.created_at }}</p>
            </div>

            <label class="flex items-center gap-1.5 text-sm">
                Mode
                <select v-model="modeId" class="field w-auto py-1" @change="changeMode">
                    <option value="">None</option>
                    <option v-for="mode in modes" :key="mode.id" :value="mode.id">{{ mode.name }}</option>
                </select>
            </label>

            <Link v-if="run.next" :href="`/tale-runs/${run.id}/play`" class="btn-primary">
                Play next: {{ run.next.number }}. {{ run.next.scenario }}
            </Link>
            <span v-else-if="run.finished" class="rounded bg-emerald-100 px-2 py-1 text-sm text-emerald-900">Finished</span>
            <span v-else class="text-sm text-stone-500">This Tale has no scenarios yet</span>

            <button type="button" class="btn-ghost text-red-700" @click="destroy">Delete</button>
        </header>

        <div v-if="hasLog && steps.length" class="overflow-x-auto p-4">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="border border-stone-300 bg-stone-50 p-1.5" />
                        <th
                            v-for="step in steps"
                            :key="step.id"
                            class="border border-stone-300 bg-stone-50 p-1.5 font-semibold"
                            :class="step.recorded ? 'text-stone-900' : 'text-stone-400'"
                        >
                            {{ step.number }}. {{ step.scenario }}
                            <button
                                v-if="step.recorded"
                                type="button"
                                class="ml-1 text-xs font-normal text-amber-800 underline"
                                :title="`Correct what ${step.scenario} recorded`"
                                @click="editing = step"
                            >edit</button>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in run.grid.party" :key="`party-${row.entry_id}`">
                        <th class="border border-stone-300 p-1.5 text-left font-medium" v-html="name(row.name)" />
                        <td v-for="(cell, i) in row.cells" :key="i" class="border border-stone-300 p-1.5 text-center">{{ cell }}</td>
                    </tr>

                    <template v-for="character in run.grid.characters" :key="character.slug">
                        <tr>
                            <th :colspan="steps.length + 1" class="border border-stone-300 bg-stone-100 p-1.5 text-left font-serif">
                                {{ character.name }}
                            </th>
                        </tr>
                        <tr v-for="row in character.rows" :key="`${character.slug}-${row.entry_id}`">
                            <th class="border border-stone-300 p-1.5 pl-4 text-left font-medium" v-html="name(row.name)" />
                            <td v-for="(cell, i) in row.cells" :key="i" class="border border-stone-300 p-1.5 text-center">{{ cell }}</td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p v-else class="p-4 text-sm text-stone-500">Nothing recorded yet.</p>

        <TaleResults v-if="editTale" :tale="editTale" :party="recordedParty" @close="editing = null" />
    </article>
</template>
