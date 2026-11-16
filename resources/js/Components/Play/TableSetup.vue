<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Icon.vue';

/**
 * The screen in front of the table: who is sitting down, in what order, and
 * with which modules — before anything is dealt. It owns nothing. The lineup
 * is the page's (a v-model of deck keys, seating order), and Start is the
 * page's move, because dealing is the table's business and the dials are
 * seeded against however many sit down here.
 *
 * What it adds over picking decks mid-game in the drawer is exactly that
 * seeding: a party chosen first gets a Dread and an omen pool counted for it,
 * where a seat added later never re-seeds a dial.
 */
const props = defineProps({
    scenario: { type: Object, required: true },
    decks: { type: Array, default: () => [] },
    lineup: { type: Array, default: () => [] },
    characterBySlug: { type: Object, required: true },
    partyStyle: { type: Function, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    // { dread, omen } for a party of a given size.
    dialsAt: { type: Function, required: true },
    // { health, hand, gold } for one character at a given party size.
    seatAt: { type: Function, required: true },
    startingDread: { type: Object, required: true },
    startingOmen: { type: Object, required: true },
    // A game is already on the table, so there is somewhere to go back to.
    inProgress: { type: Boolean, default: false },
});

const emit = defineEmits(['update:lineup', 'start', 'cancel', 'toggle-module']);

const deckByKey = computed(() => Object.fromEntries(props.decks.map((d) => [d.key, d])));
const seats = computed(() => props.lineup.map((key) => deckByKey.value[key]).filter(Boolean));
const count = computed(() => seats.value.length);
const dials = computed(() => props.dialsAt(count.value));

const seated = (key) => props.lineup.includes(key);

const toggle = (key) => emit('update:lineup', seated(key)
    ? props.lineup.filter((k) => k !== key)
    : [...props.lineup, key]);

const move = (index, by) => {
    const next = [...props.lineup];
    const to = index + by;
    if (to < 0 || to >= next.length) return;
    [next[index], next[to]] = [next[to], next[index]];
    emit('update:lineup', next);
};

const characterOf = (deck) => props.characterBySlug[deck.character];
const modules = computed(() => [...props.available, ...props.others]);
</script>

<template>
    <header class="flex h-12 shrink-0 items-center gap-3 border-b border-white/10 bg-black/40 px-3">
        <Link :href="`/scenarios/${scenario.slug}`" class="table-btn" :title="`Back to ${scenario.name}`">← Scenario</Link>
        <span class="truncate font-serif text-base font-semibold text-stone-50">{{ scenario.name }}</span>
        <span class="font-serif text-base text-amber-200">Setting up</span>
        <button v-if="inProgress" type="button" class="table-btn ml-auto" @click="emit('cancel')">Back to the game in progress</button>
    </header>

    <div class="table-scroll min-h-0 flex-1">
        <div class="mx-auto grid max-w-6xl gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <!-- Every saved deck that could sit down -->
            <section class="table-panel p-4">
                <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <h2 class="font-serif text-xl font-semibold text-stone-50">Who is playing?</h2>
                    <p class="text-xs text-stone-400">Click a deck to bring it to the table. Seats follow the order you pick them in.</p>
                </div>

                <div v-if="decks.length" class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="deck in decks"
                        :key="deck.key"
                        type="button"
                        class="flex overflow-hidden rounded-lg border text-left transition"
                        :class="seated(deck.key)
                            ? 'border-amber-400 bg-amber-950/40 ring-1 ring-amber-400'
                            : 'border-white/10 bg-black/20 hover:border-white/30 hover:bg-black/30'"
                        :aria-pressed="seated(deck.key)"
                        @click="toggle(deck.key)"
                    >
                        <span class="w-2 shrink-0" :style="partyStyle(characterOf(deck))" />
                        <span class="min-w-0 flex-1 p-2.5">
                            <span class="flex items-center gap-2">
                                <span class="min-w-0 flex-1 truncate font-medium text-stone-50">{{ deck.name }}</span>
                                <span
                                    v-if="seated(deck.key)"
                                    class="rounded-full bg-amber-500 px-1.5 text-[11px] font-bold tabular-nums text-stone-900"
                                    :title="`Seat ${lineup.indexOf(deck.key) + 1}`"
                                >{{ lineup.indexOf(deck.key) + 1 }}</span>
                            </span>
                            <span class="mt-0.5 block truncate text-xs text-stone-300">
                                {{ characterOf(deck)?.name }}<template v-if="deck.domain"> · {{ deck.domain }}</template>
                            </span>
                            <span class="mt-0.5 block text-[11px] text-stone-500">
                                {{ deck.deck.length }} cards<template v-if="deck.kit.length"> · {{ deck.kit.length }} kit</template><template v-if="deck.upgrades.length"> · {{ deck.upgrades.length }} upgrades</template>
                            </span>
                        </span>
                    </button>
                </div>

                <div v-else class="rounded-lg border border-dashed border-white/20 p-6 text-center text-sm text-stone-300">
                    <p>No saved decks yet. A seat is a deck built and saved in the deck builder.</p>
                    <Link href="/decks" class="table-btn table-btn-primary mt-3"><Icon name="character" /> Build a player deck</Link>
                </div>

                <p v-if="decks.length" class="mt-3 text-xs text-stone-500">
                    Missing one? <Link href="/decks" class="underline hover:text-stone-300">Build and save it in the deck builder</Link>.
                </p>

                <!-- Modules -->
                <template v-if="modules.length">
                    <h3 class="table-label mb-2 mt-6">Modules</h3>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="module in modules"
                            :key="module.slug"
                            type="button"
                            class="table-btn"
                            :class="chosen.includes(module.slug) ? 'table-btn-on' : ''"
                            :aria-pressed="chosen.includes(module.slug)"
                            @click="emit('toggle-module', module.slug)"
                        >
                            {{ module.name }}
                            <span v-if="module.recommended" class="text-[10px] uppercase tracking-wide opacity-70">recommended</span>
                        </button>
                    </div>
                    <p class="mt-1.5 text-xs text-stone-500">Each combination of modules keeps a game of its own.</p>
                </template>

                <!-- The designer's setup, to read while sitting down -->
                <template v-if="scenario.setup_html?.length">
                    <h3 class="table-label mb-2 mt-6">Setup, as written</h3>
                    <ol class="list-decimal space-y-1 pl-5 text-sm text-stone-300">
                        <li v-for="(step, index) in scenario.setup_html" :key="index" v-html="step" />
                    </ol>
                    <p class="mt-2 text-xs text-stone-500">
                        Starting lays out the board, shuffles the deck, sets the dials and deals each seat a hand. Anything else a step says is yours to do.
                    </p>
                </template>
            </section>

            <!-- The table as it will be dealt -->
            <aside class="table-panel flex flex-col p-4 lg:sticky lg:top-4 lg:self-start">
                <h2 class="font-serif text-xl font-semibold text-stone-50">At the table</h2>
                <p class="mb-3 text-xs text-stone-400">In seating order. The first seat takes the first player token.</p>

                <ol v-if="seats.length" class="space-y-1.5">
                    <li
                        v-for="(deck, index) in seats"
                        :key="deck.key"
                        class="flex items-center gap-2 rounded border border-white/10 bg-black/25 p-2"
                    >
                        <span class="h-6 w-6 shrink-0 rounded-full border border-white/20" :style="partyStyle(characterOf(deck))" />
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center gap-1.5">
                                <span class="truncate text-sm font-medium text-stone-50">{{ characterOf(deck)?.name }}</span>
                                <span v-if="index === 0" class="rounded bg-amber-700 px-1 text-[9px] font-bold uppercase tracking-wide text-amber-50">first</span>
                            </span>
                            <span class="block truncate text-[11px] text-stone-400">{{ deck.name }}</span>
                            <span class="block text-[11px] tabular-nums text-stone-500">
                                ♥ {{ seatAt(deck.character, count).health }} · hand {{ seatAt(deck.character, count).hand }} · {{ seatAt(deck.character, count).gold }} gold a round
                            </span>
                        </span>
                        <span class="flex shrink-0 flex-col gap-0.5">
                            <button type="button" class="table-step" :disabled="index === 0" :aria-label="`Move ${deck.name} up`" @click="move(index, -1)">↑</button>
                            <button type="button" class="table-step" :disabled="index === seats.length - 1" :aria-label="`Move ${deck.name} down`" @click="move(index, 1)">↓</button>
                        </span>
                        <button type="button" class="table-step" :aria-label="`Take ${deck.name} off the table`" @click="toggle(deck.key)">✕</button>
                    </li>
                </ol>
                <p v-else class="rounded border border-dashed border-white/15 p-4 text-center text-sm text-stone-400">
                    Nobody yet. Pick a deck on the left.
                </p>

                <dl class="mt-4 grid grid-cols-2 gap-2 text-center">
                    <div class="rounded border border-white/10 bg-black/25 p-2">
                        <dt class="table-label">Dread</dt>
                        <dd class="font-serif text-2xl tabular-nums text-red-300">{{ dials.dread }}</dd>
                        <dd v-if="startingDread.scaled" class="text-[11px] text-stone-400" v-html="startingDread.html" />
                    </div>
                    <div class="rounded border border-white/10 bg-black/25 p-2">
                        <dt class="table-label">Omen</dt>
                        <dd class="font-serif text-2xl tabular-nums text-violet-300">{{ dials.omen }}</dd>
                        <dd v-if="startingOmen.scaled" class="text-[11px] text-stone-400" v-html="startingOmen.html" />
                    </div>
                </dl>
                <p class="mt-1 text-center text-[11px] text-stone-500">
                    Counted for {{ count }} {{ count === 1 ? 'player' : 'players' }}.
                </p>

                <button
                    type="button"
                    class="table-btn table-btn-primary mt-4 w-full py-2 text-base"
                    :disabled="!seats.length"
                    @click="emit('start')"
                >
                    <Icon name="play" /> {{ inProgress ? 'Start a new game' : 'Start the game' }}
                </button>
                <p v-if="inProgress" class="mt-1.5 text-center text-[11px] text-stone-500">
                    This replaces the game in progress. Back to the game keeps it.
                </p>
            </aside>
        </div>
    </div>
</template>
