<script setup>
import { computed, ref } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import TaleStepRow from '../../Components/TaleStepRow.vue';
import TaleModeRow from '../../Components/TaleModeRow.vue';
import TaleLogEntryRow from '../../Components/TaleLogEntryRow.vue';
import TaleCardRow from '../../Components/TaleCardRow.vue';
import TaleRunCard from '../../Components/TaleRunCard.vue';
import CardPreview from '../../Components/CardPreview.vue';
import { renderRules } from '../../rulesMarkdown';
import { renderMarkup } from '../../markup';

const props = defineProps({
    tale: { type: Object, required: true },
    steps: { type: Array, default: () => [] },
    modes: { type: Array, default: () => [] },
    logEntries: { type: Array, default: () => [] },
    modules: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
    rewards: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
    sharedModules: { type: Array, default: () => [] },
    logKinds: { type: Array, default: () => [] },
    logScopes: { type: Array, default: () => [] },
    runs: { type: Array, default: () => [] },
});

const tabs = [
    { key: 'story', label: 'Story' },
    { key: 'scenarios', label: 'Scenarios' },
    { key: 'modes', label: 'Modes' },
    { key: 'log', label: 'Log' },
    { key: 'cards', label: 'Cards' },
    { key: 'playthroughs', label: 'Playthroughs' },
];

// The tab is in the URL, so a save that comes back here lands where it left.
const initial = new URLSearchParams(typeof window === 'undefined' ? '' : window.location.search).get('tab');
const tab = ref(tabs.some((t) => t.key === initial) ? initial : 'story');

const select = (key) => {
    tab.value = key;
    const url = new URL(window.location.href);
    url.searchParams.set('tab', key);
    window.history.replaceState(window.history.state, '', url);
};

const page = usePage();

// The story is block markdown with card markup inside, the same as a rules
// document, so it renders through the rulebook's renderer. A step's text
// quotes its own scenario's Dread rule and numbers; the overview has none.
// Mirrors TaleBook::step() and story() server side.
const markupOptions = (step = null) => ({
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
    dreadRule: step?.dread_rule ?? null,
    dreadAmount: step?.dread_amount ?? null,
    omenAmount: step?.omen_amount ?? null,
});
const prose = (text, step = null) => renderRules(text ?? '', markupOptions(step));
const inline = (text, step = null) => renderMarkup(text ?? '', markupOptions(step));

const stepForm = useForm({ scenario_id: null });
const addStep = () => stepForm.post(`/tales/${props.tale.slug}/steps`, { preserveScroll: true, onSuccess: () => stepForm.reset() });

const modeForm = useForm({ name: '' });
const addMode = () => modeForm.post(`/tales/${props.tale.slug}/modes`, { preserveScroll: true, onSuccess: () => modeForm.reset() });

const entryForm = useForm({ name: '', kind: 'number', scope: 'party' });
const addEntry = () => entryForm.post(`/tales/${props.tale.slug}/log-entries`, { preserveScroll: true, onSuccess: () => entryForm.reset('name') });

const runForm = useForm({ name: '', tale_mode_id: null });
const startRun = () => runForm.post(`/tales/${props.tale.slug}/runs`, { preserveScroll: true, onSuccess: () => runForm.reset() });

const cardForm = useForm({ name: '', traits: [] });
const addCard = () => cardForm.post(`/tales/${props.tale.slug}/tale-cards`, { preserveScroll: true, onSuccess: () => cardForm.reset() });

const cardCount = computed(() => props.cards.length + props.rewards.length + props.modules.length);
</script>

<template>
    <Head :title="tale.name" />

    <PageHeader :title="tale.name">
        <template #actions>
            <Link :href="`/print/tale/${tale.slug}`" class="btn-ghost"><Icon name="print" /> Print cards</Link>
            <a :href="`/print/tale/${tale.slug}/book`" target="_blank" rel="noopener" class="btn-ghost"><Icon name="print" /> Print story and log</a>
            <Link :href="`/tales/${tale.slug}/edit`" class="btn-ghost"><Icon name="edit" /> Edit</Link>
        </template>

        <div class="mt-1 flex flex-wrap gap-3 text-xs text-stone-600">
            <span v-if="tale.set_icon" class="rounded border border-stone-800 px-1.5 py-0.5 font-mono font-bold text-stone-900">{{ tale.set_icon }}</span>
            <span>{{ steps.length }} {{ steps.length === 1 ? 'scenario' : 'scenarios' }}</span>
            <span>{{ modes.length }} {{ modes.length === 1 ? 'mode' : 'modes' }}</span>
            <span v-if="tale.is_placeholder" class="rounded bg-amber-100 px-1.5 text-amber-900">placeholder</span>
        </div>

        <p v-if="tale.status" class="mt-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-900">{{ tale.status }}</p>

        <nav class="mt-4 flex gap-1 border-b border-stone-300">
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                class="-mb-px border-b-2 px-3 py-2 text-sm"
                :class="tab === item.key ? 'border-amber-700 font-semibold text-stone-900' : 'border-transparent text-stone-500 hover:text-stone-800'"
                @click="select(item.key)"
            >
                {{ item.label }}
                <span v-if="item.key === 'cards' && cardCount" class="text-xs text-stone-400">{{ cardCount }}</span>
            </button>
        </nav>
    </PageHeader>

    <div class="px-6 py-6">
        <!-- Read top to bottom, the way the box would be read. -->
        <section v-if="tab === 'story'" class="max-w-3xl space-y-6">
            <div v-if="tale.overview" class="rules-prose" v-html="prose(tale.overview)" />
            <p v-else class="rounded border border-dashed border-stone-300 p-4 text-sm text-stone-600">
                No overview yet. <Link :href="`/tales/${tale.slug}/edit`" class="underline">Write the opening</Link>.
            </p>

            <article v-for="step in steps" :key="step.id" class="border-t border-stone-200 pt-4">
                <h2 class="font-serif text-lg font-semibold">{{ step.number }}. {{ step.scenario?.name ?? 'Missing scenario' }}</h2>
                <div v-if="step.intro" class="rules-prose mt-2" v-html="prose(step.intro, step)" />
                <p v-if="step.notes" class="mt-2 text-sm italic text-stone-600">Setup: <span v-html="inline(step.notes, step)" /></p>
                <div v-if="step.outro" class="rules-prose mt-3 border-l-2 border-stone-300 pl-3" v-html="prose(step.outro, step)" />
                <p v-if="!step.intro && !step.outro" class="mt-1 text-sm text-stone-500">No story written for this scenario yet.</p>
            </article>

            <p v-if="!steps.length" class="text-sm text-stone-600">
                No scenarios in this Tale yet — add them on the <button type="button" class="underline" @click="select('scenarios')">Scenarios</button> tab.
            </p>
        </section>

        <section v-else-if="tab === 'scenarios'" class="max-w-4xl space-y-3">
            <p class="text-sm text-stone-600">
                The scenarios in the order the Tale plays them. A Tale names a scenario and never changes it, so each one is still playable on its own.
            </p>

            <TaleStepRow
                v-for="(step, index) in steps"
                :key="step.id"
                :step="step"
                :scenarios="scenarios"
                :tale-modules="modules"
                :shared-modules="sharedModules"
                :previous="steps[index - 1] ?? null"
                :next="steps[index + 1] ?? null"
            />

            <form class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed border-stone-300 p-4" @submit.prevent="addStep">
                <div>
                    <label class="field-label">Add a scenario</label>
                    <select v-model="stepForm.scenario_id" class="field">
                        <option :value="null" disabled>Pick one…</option>
                        <option v-for="scenario in scenarios" :key="scenario.id" :value="scenario.id">{{ scenario.name }}</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" :disabled="!stepForm.scenario_id || stepForm.processing"><Icon name="add" /> Add</button>
                <p v-if="stepForm.errors.scenario_id" class="field-error w-full">{{ stepForm.errors.scenario_id }}</p>
            </form>
        </section>

        <section v-else-if="tab === 'modes'" class="max-w-3xl space-y-3">
            <p class="text-sm text-stone-600">
                Difficulty modes change what happens between scenarios. How many there are and what each does is yours; the tool applies none of it.
            </p>

            <TaleModeRow
                v-for="(mode, index) in modes"
                :key="mode.id"
                :mode="mode"
                :first="index === 0"
                :last="index === modes.length - 1"
            />

            <form class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed border-stone-300 p-4" @submit.prevent="addMode">
                <div class="flex-1">
                    <label class="field-label">Add a mode</label>
                    <input v-model="modeForm.name" type="text" class="field" placeholder="Hard">
                </div>
                <button type="submit" class="btn-primary" :disabled="!modeForm.name || modeForm.processing"><Icon name="add" /> Add</button>
                <p v-if="modeForm.errors.name" class="field-error w-full">{{ modeForm.errors.name }}</p>
            </form>
        </section>

        <section v-else-if="tab === 'log'" class="space-y-6">
            <div class="max-w-4xl space-y-2">
                <p class="text-sm text-stone-600">
                    What the players write down when a scenario ends. The printed log sheet is laid out from these, one row each, with a column per scenario.
                </p>

                <TaleLogEntryRow
                    v-for="(entry, index) in logEntries"
                    :key="entry.id"
                    :entry="entry"
                    :kinds="logKinds"
                    :scopes="logScopes"
                    :first="index === 0"
                    :last="index === logEntries.length - 1"
                />

                <form class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed border-stone-300 p-3" @submit.prevent="addEntry">
                    <div class="min-w-[12rem] flex-1">
                        <label class="field-label">Add an entry</label>
                        <input v-model="entryForm.name" type="text" class="field" placeholder="Damage kept">
                    </div>
                    <select v-model="entryForm.kind" class="field w-auto" aria-label="Kind">
                        <option v-for="kind in logKinds" :key="kind" :value="kind">{{ kind }}</option>
                    </select>
                    <select v-model="entryForm.scope" class="field w-auto" aria-label="Scope">
                        <option v-for="scope in logScopes" :key="scope" :value="scope">{{ scope }}</option>
                    </select>
                    <button type="submit" class="btn-primary" :disabled="!entryForm.name || entryForm.processing"><Icon name="add" /> Add</button>
                    <p v-if="entryForm.errors.name" class="field-error w-full">{{ entryForm.errors.name }}</p>
                </form>
            </div>

            <!-- The printed log sheet, laid out from the entries above. The key
                 makes it reload when an entry or a scenario changes. -->
            <div class="space-y-2">
                <div class="flex items-baseline gap-3">
                    <h2 class="font-serif text-lg font-semibold">The log sheet</h2>
                    <a :href="`/print/tale/${tale.slug}/book?part=log`" target="_blank" rel="noopener" class="text-sm underline">Open to print</a>
                </div>
                <iframe
                    :key="JSON.stringify([logEntries, steps.map((s) => s.scenario?.id), modes.map((m) => m.name)])"
                    :src="`/print/tale/${tale.slug}/book?part=log`"
                    title="The printed log sheet"
                    class="h-[42rem] w-full max-w-4xl rounded border border-stone-300 bg-stone-600"
                />
            </div>
        </section>

        <section v-else-if="tab === 'cards'" class="max-w-5xl space-y-8">
            <!-- The Tale's entity-side cards are modules it owns, hidden from
                 ordinary play. They are edited on the module page as ever. -->
            <div class="space-y-3">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="font-serif text-lg font-semibold">Modules</h2>
                    <Link :href="`/modules/create?tale=${tale.slug}`" class="btn-ghost"><Icon name="add" /> New Tale module</Link>
                </div>
                <p class="text-sm text-stone-600">Entity cards, board cards and districts that only come out in this Tale. Pick which scenario they join on the Scenarios tab.</p>
                <ul v-if="modules.length" class="divide-y divide-stone-200 rounded-lg border border-stone-300 bg-white">
                    <li v-for="module in modules" :key="module.id" class="flex items-center gap-3 px-4 py-2">
                        <span v-if="module.set_icon" class="rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">{{ module.set_icon }}</span>
                        <Link :href="`/modules/${module.slug}`" class="font-medium hover:text-amber-800">{{ module.name }}</Link>
                        <span class="ml-auto text-xs text-stone-500">{{ module.deck_size }} cards</span>
                    </li>
                </ul>
                <p v-else class="rounded border border-dashed border-stone-300 p-4 text-sm text-stone-500">No Tale modules yet.</p>
            </div>

            <div class="space-y-3">
                <h2 class="font-serif text-lg font-semibold">Tale cards</h2>
                <p class="text-sm text-stone-600">Story cards and the box's own mechanic. The type line is free text.</p>
                <TaleCardRow v-for="card in cards" :key="card.id" :card="card" />
                <form class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed border-stone-300 p-4" @submit.prevent="addCard">
                    <div class="flex-1">
                        <label class="field-label">Add a Tale card</label>
                        <input v-model="cardForm.name" type="text" class="field" placeholder="The Lighthouse">
                    </div>
                    <button type="submit" class="btn-primary" :disabled="!cardForm.name || cardForm.processing"><Icon name="add" /> Add</button>
                    <p v-if="cardForm.errors.name" class="field-error w-full">{{ cardForm.errors.name }}</p>
                </form>
            </div>

            <div class="space-y-3">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="font-serif text-lg font-semibold">Reward cards</h2>
                    <Link :href="`/tales/${tale.slug}/cards/create`" class="btn-ghost"><Icon name="add" /> New reward</Link>
                </div>
                <p class="text-sm text-stone-600">Player cards the party earns in the Tale. They sit outside a built deck's 20.</p>
                <div v-if="rewards.length" class="flex flex-wrap gap-4">
                    <Link v-for="reward in rewards" :key="reward.id" :href="`/player-cards/${reward.id}/edit`" class="block" :title="`Edit ${reward.name}`">
                        <CardPreview :card="reward" kind="player" :width="140" />
                    </Link>
                </div>
                <p v-else class="rounded border border-dashed border-stone-300 p-4 text-sm text-stone-500">No reward cards yet.</p>
            </div>
        </section>

        <!-- Playthroughs: each plays the Tale's scenarios in order on the
             playtest table and keeps its log here. -->
        <section v-else-if="tab === 'playthroughs'" class="max-w-5xl space-y-4">
            <p class="text-sm text-stone-600">
                A playthrough plays the scenarios in order on the playtest table. The table shows the log so far and deals each character the rewards they have earned; everything else in the log is yours to apply.
            </p>

            <form class="flex flex-wrap items-end gap-2 rounded-lg border border-dashed border-stone-300 p-4" @submit.prevent="startRun">
                <div class="min-w-[12rem] flex-1">
                    <label class="field-label">Start a playthrough</label>
                    <input v-model="runForm.name" type="text" class="field" placeholder="First try">
                </div>
                <div>
                    <label class="field-label">Mode</label>
                    <select v-model="runForm.tale_mode_id" class="field w-auto">
                        <option :value="null">None</option>
                        <option v-for="mode in modes" :key="mode.id" :value="mode.id">{{ mode.name }}</option>
                    </select>
                </div>
                <button type="submit" class="btn-primary" :disabled="!runForm.name || runForm.processing"><Icon name="add" /> Start</button>
                <p v-if="runForm.errors.name" class="field-error w-full">{{ runForm.errors.name }}</p>
                <p v-if="runForm.errors.tale_mode_id" class="field-error w-full">{{ runForm.errors.tale_mode_id }}</p>
            </form>

            <TaleRunCard v-for="run in runs" :key="run.id" :run="run" :modes="modes" />
            <p v-if="!runs.length" class="text-sm text-stone-500">No playthroughs yet.</p>
        </section>
    </div>
</template>
