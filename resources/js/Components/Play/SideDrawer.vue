<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';

/**
 * Everything on the table that is read rather than played, in a drawer down
 * the right edge, closed by default: the log, every story beat, who is
 * playing, the setup and the modules. It changes nothing itself — picking a
 * deck, the first player and a module are the page's moves, said by emit.
 */
const props = defineProps({
    tab: { type: String, default: 'log' },
    log: { type: Array, default: () => [] },
    beats: { type: Array, default: () => [] },
    beatIndex: { type: Number, default: -1 },
    storyComplete: { type: Boolean, default: false },
    party: { type: Array, default: () => [] },
    firstPlayer: { type: Number, default: 0 },
    decks: { type: Array, default: () => [] },
    characterBySlug: { type: Object, required: true },
    deckByKey: { type: Object, required: true },
    partyStyle: { type: Function, required: true },
    scenario: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    beatDread: { type: Function, required: true },
    // A scenario of a Tale playthrough, or null.
    tale: { type: Object, default: null },
    fixedModules: { type: Array, default: () => [] },
});

const emit = defineEmits(['update:tab', 'close', 'toggle-deck', 'make-first', 'toggle-module', 'record']);

// In a playthrough the Tale gets a tab of its own, first: it is where the
// scenario ends, with Record results.
const TABS = computed(() => [
    ...(props.tale ? [['tale', 'Tale']] : []),
    ['log', 'Log'],
    ['beats', 'Beats'],
    ['players', 'Players'],
    ['setup', 'Setup'],
    ['modules', 'Modules'],
]);

const seated = (deckKey) => props.party.some((member) => member.deckKey === deckKey);
const beatCardCount = (beat) => beat.cards.reduce((n, card) => n + Math.max(1, card.qty), 0);
</script>

<template>
    <aside class="absolute inset-y-0 right-0 z-30 flex w-[26rem] max-w-full flex-col border-l border-stone-300 bg-stone-50 text-stone-800 shadow-2xl">
        <div class="flex items-center gap-1 border-b border-stone-300 px-2 py-1.5">
            <button
                v-for="[key, label] in TABS"
                :key="key"
                type="button"
                class="rounded px-2 py-1 text-xs font-medium"
                :class="tab === key ? 'bg-stone-900 text-stone-50' : 'text-stone-600 hover:bg-stone-200'"
                @click="emit('update:tab', key)"
            >
                {{ label }}
            </button>
            <button type="button" class="ml-auto rounded px-2 py-1 text-sm text-stone-500 hover:bg-stone-200" aria-label="Close the drawer" @click="emit('close')">✕</button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-3 text-sm">
            <!-- The playthrough this game is a scenario of -->
            <template v-if="tab === 'tale' && tale">
                <p class="field-micro">{{ tale.tale.name }} · {{ tale.run.name }}</p>
                <h3 class="font-serif text-base font-semibold">{{ tale.step.number }}. {{ tale.step.scenario }}</h3>
                <p v-if="tale.mode" class="mt-1 text-xs text-stone-600">Mode: {{ tale.mode.name }}</p>
                <p v-if="fixedModules.length" class="mt-1 text-xs text-stone-600">The Tale's modules: {{ fixedModules.join(', ') }}</p>
                <div v-if="tale.step.intro_html" class="rules-prose mt-3 text-sm" v-html="tale.step.intro_html" />
                <button type="button" class="table-btn table-btn-primary mt-4 w-full py-2" @click="emit('record')">
                    Record results
                </button>
                <p class="mt-1.5 text-xs text-stone-500">When the scenario ends: write what it leaves in the Tale's log. Recording it again replaces it.</p>
            </template>

            <!-- What has happened -->
            <template v-else-if="tab === 'log'">
                <ol v-if="log.length" class="space-y-1 text-xs text-stone-700">
                    <li v-for="(entry, index) in log" :key="log.length - index">
                        <span class="tabular-nums text-stone-400">R{{ entry.round }}</span> {{ entry.text }}
                    </li>
                </ol>
                <p v-else class="text-xs text-stone-500">Nothing yet.</p>
            </template>

            <!-- Story beats -->
            <template v-else-if="tab === 'beats'">
                <template v-if="beats.length">
                    <p v-if="storyComplete" class="mb-3 rounded border border-emerald-300 bg-emerald-50 p-2 text-sm text-emerald-900">
                        Every story beat has advanced.
                    </p>
                    <div class="space-y-2">
                        <div
                            v-for="(beat, index) in beats"
                            :key="beat.id"
                            class="rounded-lg border p-2.5"
                            :class="index === beatIndex ? 'border-amber-500 bg-amber-50' : index < beatIndex ? 'border-stone-200 bg-stone-100 opacity-70' : 'border-stone-200 bg-white'"
                        >
                            <div class="flex flex-wrap items-baseline gap-2">
                                <span class="rounded bg-amber-900 px-1.5 py-0.5 text-xs font-bold text-amber-50">Beat {{ beat.order }}</span>
                                <h3 class="font-serif text-base font-semibold">{{ beat.name }}</h3>
                                <span v-if="beatDread(beat)" class="text-xs font-semibold text-red-700" v-html="beatDread(beat)" />
                                <span v-if="index === beatIndex" class="text-xs font-semibold uppercase tracking-wide text-amber-800">current</span>
                                <span v-else-if="index < beatIndex" class="text-xs uppercase tracking-wide text-stone-500">resolved</span>
                            </div>

                            <p v-if="beat.flavour" class="mt-1 font-serif text-sm italic text-stone-600">{{ beat.flavour }}</p>

                            <dl class="mt-2 space-y-1 text-sm">
                                <div v-if="beat.on_reach"><dt class="field-micro">When reached</dt><dd v-html="beat.html.on_reach" /></div>
                                <div v-if="beat.advance"><dt class="field-micro">Advances when</dt><dd v-html="beat.html.advance" /></div>
                                <div v-if="beat.on_advance"><dt class="field-micro">On advancing</dt><dd v-html="beat.html.on_advance" /></div>
                            </dl>

                            <p v-if="beat.cards.length" class="mt-2 text-xs text-stone-500">
                                Adds {{ beatCardCount(beat) }} {{ beatCardCount(beat) === 1 ? 'card' : 'cards' }} to the draw pile on advancing.
                            </p>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-stone-500">The current beat's Advance button and counters are on the left of the table.</p>
                </template>
                <p v-else class="text-sm text-stone-500">No story beats written for this scenario yet.</p>
            </template>

            <!-- Who is playing, and with which deck -->
            <template v-else-if="tab === 'players'">
                <p class="mb-3 text-xs text-stone-500">In seating order: the first player token passes down this list.</p>

                <div v-if="party.length" class="mb-4 space-y-2">
                    <div
                        v-for="(member, index) in party"
                        :key="member.deckKey"
                        class="rounded border p-2"
                        :class="member.current === 0 ? 'border-red-400 bg-red-50' : 'border-stone-200 bg-white'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="h-4 w-4 shrink-0 rounded-full border border-stone-400" :style="partyStyle(characterBySlug[member.slug])" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ characterBySlug[member.slug]?.name }}</span>
                            <span v-if="index === firstPlayer" class="rounded bg-amber-700 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-50">first</span>
                            <button v-else type="button" class="text-[11px] text-stone-500 underline" @click="emit('make-first', index)">make first</button>
                        </div>
                        <p class="mt-0.5 truncate text-xs text-stone-500">{{ deckByKey[member.deckKey]?.name }}</p>
                    </div>
                </div>

                <h3 class="field-micro mb-1">{{ party.length ? 'Change who is playing' : 'Pick the decks that are playing' }}</h3>
                <div class="space-y-1">
                    <label
                        v-for="deck in decks"
                        :key="deck.key"
                        class="flex cursor-pointer items-start gap-2 rounded border p-2 text-sm"
                        :class="seated(deck.key) ? 'border-stone-900 bg-white' : 'border-stone-200 hover:border-stone-400'"
                    >
                        <input
                            type="checkbox"
                            :checked="seated(deck.key)"
                            class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="emit('toggle-deck', deck.key)"
                        >
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-medium">{{ deck.name }}</span>
                            <span class="block text-xs text-stone-500">
                                {{ characterBySlug[deck.character]?.name }}{{ deck.domain ? ` · ${deck.domain}` : '' }} · {{ deck.deck.length }} cards
                            </span>
                        </span>
                    </label>
                    <p class="pt-1 text-xs text-stone-500">
                        <template v-if="!decks.length">No saved decks yet. </template>
                        Decks come from the <Link href="/decks" class="underline">deck builder</Link>: build one and save it to bring it to the table.
                    </p>
                </div>
                <Link href="/decks" class="btn-ghost mt-3"><Icon name="character" /> Build a player deck</Link>
            </template>

            <!-- Setup, as the designer wrote it -->
            <template v-else-if="tab === 'setup'">
                <ol v-if="scenario.setup_html?.length" class="list-decimal space-y-1 pl-5 text-sm text-stone-700">
                    <li v-for="(step, index) in scenario.setup_html" :key="index" v-html="step" />
                </ol>
                <p v-else class="text-sm text-stone-500">No setup written for this scenario yet.</p>
                <p class="mt-3 text-xs text-stone-500">
                    A new game lays out the board cards, shuffles the deck, sets the dials and deals each seat a hand.
                    Anything else a step says is yours to do.
                </p>
            </template>

            <!-- Modules -->
            <template v-else-if="tab === 'modules'">
                <p class="mb-3 text-xs text-stone-500">
                    Changing modules starts a fresh game for that combination — this one is kept as it is.
                </p>
                <div class="space-y-1.5">
                    <label
                        v-for="module in available"
                        :key="module.slug"
                        class="flex cursor-pointer items-center gap-2 rounded border p-2 text-sm"
                        :class="chosen.includes(module.slug) ? 'border-stone-900 bg-white' : 'border-stone-200 hover:border-stone-400'"
                    >
                        <input
                            type="checkbox"
                            :checked="chosen.includes(module.slug)"
                            class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="emit('toggle-module', module.slug)"
                        >
                        <span class="min-w-0 flex-1">
                            {{ module.name }}
                            <span v-if="module.recommended" class="ml-1 text-[11px] uppercase tracking-wide text-amber-800">recommended</span>
                        </span>
                    </label>
                    <label
                        v-for="module in others"
                        :key="module.slug"
                        class="flex cursor-pointer items-center gap-2 rounded border border-stone-200 p-2 text-sm hover:border-stone-400"
                    >
                        <input
                            type="checkbox"
                            :checked="chosen.includes(module.slug)"
                            class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="emit('toggle-module', module.slug)"
                        >
                        <span class="flex-1">{{ module.name }}</span>
                    </label>
                    <p v-if="!available.length && !others.length" class="text-sm text-stone-500">No modules to add.</p>
                </div>
                <Link :href="`/scenarios/${scenario.slug}/deck?${chosen.map((m) => `modules[]=${m}`).join('&')}`" class="btn-ghost mt-3">
                    Deck assembly
                </Link>
            </template>
        </div>
    </aside>
</template>
