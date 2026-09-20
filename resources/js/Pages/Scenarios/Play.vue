<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import Icon from '../../Components/Icon.vue';
import { useCardZoom } from '../../useCardZoom';
import { resolveReveal } from '../../omenReveal';
import { band, normalise } from '../../colour';

const props = defineProps({
    scenario: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    startingCards: { type: Array, default: () => [] },
    beats: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    config: { type: Object, default: () => ({}) },
    firstCardArrowSource: { type: String, default: 'discard' },
    defaultArrow: { type: String, default: 'top' },
});

// One printed card can have several physical copies (qty > 1); each gets its
// own instance key so the shuffle and the reveal history can tell them apart.
const instanceKeys = (card) => Array.from({ length: Math.max(1, card.qty) }, (_, n) => `${card.id}#${n}`);

const cardByKey = computed(() => {
    const map = {};

    for (const card of props.startingCards) {
        for (const key of instanceKeys(card)) map[key] = card;
    }
    for (const beat of props.beats) {
        for (const card of beat.cards) {
            for (const key of instanceKeys(card)) map[key] = card;
        }
    }

    return map;
});

const startingInstanceKeys = computed(() => props.startingCards.flatMap(instanceKeys));
const beatInstanceKeys = computed(() =>
    Object.fromEntries(props.beats.map((beat) => [beat.id, beat.cards.flatMap(instanceKeys)])),
);

const characterBySlug = computed(() => Object.fromEntries(props.characters.map((c) => [c.slug, c])));

const shuffle = (list) => {
    const copy = [...list];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
};

// Everything below is kept in the browser only, the way a built deck already
// lives in a query string rather than a database row: this is one designer's
// solo playtest table, not a second source of truth for the game.
const storageKey = computed(() => `cardforge-play:${props.scenario.slug}:${[...props.chosen].sort().join(',')}`);

const session = reactive({
    drawPile: [],
    // The storyline is the row of revealed cards; the discard pile is what has
    // come off it. They are two piles because the rules treat them as two: the
    // first card of a storyline takes its arrow from the top of the discard.
    line: [],
    discardPile: [],
    omen: 0,
    dread: 0,
    beatIndex: -1,
    incomingChoice: props.defaultArrow,
    // Whoever is at the table, and the health they have left.
    party: [],
    revealCount: 0,
    lastReveal: null,
});

const fullHealth = (slug) => characterBySlug.value[slug]?.health ?? 0;

const freshSession = (party = []) => ({
    drawPile: shuffle(startingInstanceKeys.value),
    line: [],
    discardPile: [],
    omen: props.config.startingOmen ?? 0,
    dread: props.scenario.starting_dread ?? 0,
    beatIndex: props.beats.length ? 0 : -1,
    incomingChoice: props.defaultArrow,
    // A new game keeps who is playing and gives them their health back.
    party: party.map((member) => ({ slug: member.slug, current: fullHealth(member.slug) })),
    revealCount: 0,
    lastReveal: null,
});

const loadSession = () => {
    let saved = null;

    try {
        const raw = localStorage.getItem(storageKey.value);
        saved = raw ? JSON.parse(raw) : null;
    } catch {
        saved = null;
    }

    if (!saved) {
        Object.assign(session, freshSession());
        return;
    }

    // A card edited or removed since this game started leaves a dangling key;
    // drop it rather than crash the page over it. Same for a character deleted
    // out from under a party.
    const known = (key) => cardByKey.value[key] !== undefined;

    Object.assign(session, {
        drawPile: (saved.drawPile ?? []).filter(known),
        line: (saved.line ?? []).filter((entry) => known(entry.key)),
        discardPile: (saved.discardPile ?? []).filter((entry) => known(entry.key)),
        omen: saved.omen ?? (props.config.startingOmen ?? 0),
        dread: saved.dread ?? (props.scenario.starting_dread ?? 0),
        beatIndex: saved.beatIndex ?? (props.beats.length ? 0 : -1),
        incomingChoice: saved.incomingChoice ?? props.defaultArrow,
        party: (saved.party ?? []).filter((member) => characterBySlug.value[member.slug]),
        revealCount: saved.revealCount ?? 0,
        lastReveal: saved.lastReveal ?? null,
    });
};

// Fires on mount and again whenever the module picker below changes the key,
// since a different set of modules is a different deck and a different game.
watch(storageKey, loadSession, { immediate: true });

watch(session, () => {
    try {
        localStorage.setItem(storageKey.value, JSON.stringify(session));
    } catch {
        // A full or blocked localStorage loses the save, not the page.
    }
}, { deep: true });

const invert = (arrow) => (arrow === 'top' ? 'bottom' : 'top');
const outgoingArrow = (entry) => (entry.flipped ? invert(entry.printedArrow) : entry.printedArrow);

const discardTop = computed(() => session.discardPile[session.discardPile.length - 1] ?? null);

/**
 * What the first card of the storyline is told to do. The rules say the top of
 * the discard pile — the last card resolved — falling back to the default
 * arrow when the pile is empty, and `firstCardArrowSource` is the tunable that
 * says so, because open question 2 is still open.
 */
const incomingArrow = computed(() => {
    if (props.firstCardArrowSource.includes('discard') && discardTop.value) {
        return outgoingArrow(discardTop.value);
    }

    return session.incomingChoice;
});

// Redirect flips the arrow a card shows, which changes the card after it, not
// the one flipped — same rule as Storyline::resolve(), reimplemented here so a
// flip updates the table instantly instead of a round trip per card.
const recomputeFrom = (index) => {
    for (let i = Math.max(index, 0); i < session.line.length; i++) {
        const entry = session.line[i];
        const decidingArrow = i === 0 ? incomingArrow.value : outgoingArrow(session.line[i - 1]);
        const card = cardByKey.value[entry.key];

        entry.decidingArrow = decidingArrow;
        entry.resolves = card?.layout === 'split' ? decidingArrow : 'single';
    }
};

/** One card off the top onto the storyline, carrying the arrow chain forward. */
const pushReveal = (key, extra = {}) => {
    const card = cardByKey.value[key];
    const decidingArrow = session.line.length
        ? outgoingArrow(session.line[session.line.length - 1])
        : incomingArrow.value;

    session.line.push({
        key,
        printedArrow: card.arrow,
        flipped: false,
        decidingArrow,
        resolves: card.layout === 'split' ? decidingArrow : 'single',
        step: null,
        empowered: 0,
        xValue: null,
        ...extra,
    });
};

/** A single card, for the effects that ask for one outside the reveal step. */
const drawOne = () => {
    if (session.drawPile.length === 0) return;

    pushReveal(session.drawPile[0]);
    session.drawPile = session.drawPile.slice(1);
};

/** The reveal step: cards come out until their cost meets or exceeds the pool. */
const reveal = () => {
    const result = resolveReveal(
        session.drawPile.map((key) => cardByKey.value[key]),
        {
            pool: session.omen,
            dreadX: session.dread,
            empoweredPerPoint: props.config.empoweredPerPointOfExcess ?? 1,
        },
    );

    const step = session.revealCount + 1;
    // taken is a prefix of the draw pile, so the keys line up by position.
    session.drawPile.slice(0, result.taken.length).forEach((key, i) => {
        const entry = result.taken[i];

        pushReveal(key, {
            step,
            // Only the last card of a reveal can be Empowered.
            empowered: i === result.taken.length - 1 ? result.empowered : 0,
            xValue: entry.isX ? entry.cost : null,
        });
    });

    session.drawPile = session.drawPile.slice(result.taken.length);
    session.revealCount = step;
    // Step 2 of the reveal empties the pool. A reveal that ran out of cards
    // never finished, so the pool keeps what is still unmatched instead.
    session.omen = result.ranOut ? result.unmatched : 0;
    session.lastReveal = {
        step,
        cards: result.taken.length,
        total: result.total,
        pool: result.pool,
        empowered: result.empowered,
        endedOnX: result.endedOnX,
        xValue: result.endedOnX ? result.taken[result.taken.length - 1].cost : null,
        dreadTriggered: result.dreadTriggered,
        dreadX: session.dread,
        ranOut: result.ranOut,
        unmatched: result.unmatched,
    };
};

const toggleFlip = (index) => {
    session.line[index].flipped = !session.line[index].flipped;
    recomputeFrom(index + 1);
};

/**
 * One card off the storyline and onto the discard pile. Its neighbours become
 * neighbours, which is Redirect's "remove" form and is why the chain from here
 * on is worked out again — that form is a candidate, not settled, so nothing
 * else about it is assumed.
 */
const discardCard = (index) => {
    const [entry] = session.line.splice(index, 1);
    session.discardPile.push(entry);
    recomputeFrom(index);
};

/**
 * The end of the entity phase: the whole storyline is discarded in order, so
 * the last card resolved ends up on top of the pile — which is where the next
 * storyline's first card reads its arrow from.
 */
const discardLine = () => {
    if (session.line.length === 0) return;

    session.discardPile.push(...session.line);
    session.line = [];
};

const setIncomingChoice = (arrow) => {
    session.incomingChoice = arrow;
    recomputeFrom(0);
};

/**
 * The rules pair this with raising Dread X by 1, but that is a placeholder and
 * open question 10, so the shuffle is by hand and the dial is left alone.
 */
const reshuffleDiscardPile = () => {
    if (session.discardPile.length === 0) return;
    if (!confirm('Shuffle the discard pile back into the draw pile? The storyline stays where it is.')) return;

    session.drawPile = shuffle([...session.drawPile, ...session.discardPile.map((entry) => entry.key)]);
    session.discardPile = [];
};

const adjustOmen = (delta) => {
    session.omen = Math.max(0, session.omen + delta);
};

const adjustDread = (delta) => {
    session.dread = Math.max(0, session.dread + delta);
};

const inParty = (slug) => session.party.some((member) => member.slug === slug);

const toggleCharacter = (slug) => {
    session.party = inParty(slug)
        ? session.party.filter((member) => member.slug !== slug)
        : [...session.party, { slug, current: fullHealth(slug) }];
};

const adjustHealth = (slug, delta) => {
    const member = session.party.find((m) => m.slug === slug);
    if (!member) return;

    member.current = Math.max(0, Math.min(member.current + delta, fullHealth(slug)));
};

const healToFull = (slug) => {
    const member = session.party.find((m) => m.slug === slug);
    if (member) member.current = fullHealth(slug);
};

const advanceBeat = () => {
    if (session.beatIndex < 0 || session.beatIndex >= props.beats.length) return;

    const beat = props.beats[session.beatIndex];
    session.dread = Math.max(0, session.dread + (beat.dread_change || 0));
    session.drawPile = shuffle([...session.drawPile, ...(beatInstanceKeys.value[beat.id] ?? [])]);
    session.beatIndex += 1;
};

const newGame = () => {
    if (!confirm('Start a new game? This reshuffles the deck and resets the omen pool, Dread, health and the story beats.')) return;
    Object.assign(session, freshSession(session.party));
};

const reload = (modules) =>
    router.get(`/scenarios/${props.scenario.slug}/play`, { modules }, { preserveState: true, preserveScroll: true, replace: true });

const toggleModule = (slug) =>
    reload(props.chosen.includes(slug) ? props.chosen.filter((s) => s !== slug) : [...props.chosen, slug]);

const lineCards = computed(() => session.line.map((entry) => cardByKey.value[entry.key]));
const zoom = useCardZoom();

const zoomEntry = computed(() => (zoom.card ? session.line[zoom.position - 1] ?? null : null));
const zoomCaption = computed(() => {
    const entry = zoomEntry.value;
    if (!entry) return '';

    const parts = [entry.resolves === 'single' ? 'single effect' : `resolves ${entry.resolves} half`];
    if (entry.empowered) parts.push(`Empowered +${entry.empowered}`);
    if (entry.xValue !== null) parts.push(`X = ${entry.xValue}`);

    return parts.join(' · ');
});

// A hero's own two colours, so the health tracker reads like their card does.
const partyStyle = (character) => {
    const [from, to] = [character?.colour, character?.colour_secondary].map(normalise).filter(Boolean);

    return { background: band(from, to, '135deg') ?? '#3f2b56' };
};

const beatCardCount = (beat) => beat.cards.reduce((n, card) => n + Math.max(1, card.qty), 0);
const storyComplete = computed(() => props.beats.length > 0 && session.beatIndex >= props.beats.length);
</script>

<template>
    <Head :title="`${scenario.name} — playtest`" />

    <PageHeader
        :title="`${scenario.name} — playtest`"
        subtitle="Reveal the entity deck against the omen pool, and track Dread, health and the story beats. Everything else — effects, gold, hands — stays on paper."
    >
        <template #actions>
            <Link href="/decks" class="btn-ghost"><Icon name="character" /> Browse a player deck</Link>
            <Link :href="`/scenarios/${scenario.slug}/deck?${chosen.map((m) => `modules[]=${m}`).join('&')}`" class="btn-ghost">
                Deck assembly
            </Link>
            <Link :href="`/scenarios/${scenario.slug}`" class="btn-ghost">Back to scenario</Link>
            <button type="button" class="btn-primary" @click="newGame">New game</button>
        </template>
    </PageHeader>

    <div class="grid gap-8 px-6 py-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <div class="space-y-5">
            <!-- The two dials the reveal reads -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="omen" class="text-stone-500" /> Omen pool
                </h2>
                <div class="flex items-center gap-3">
                    <button type="button" class="step-lg" aria-label="Remove an omen" @click="adjustOmen(-1)">−</button>
                    <span class="w-12 text-center font-serif text-3xl font-bold tabular-nums">{{ session.omen }}</span>
                    <button type="button" class="step-lg" aria-label="Add an omen" @click="adjustOmen(1)">+</button>
                </div>
                <p class="mt-2 text-xs text-stone-500">
                    Built up by cards played
                    <span v-if="config.omenPerTownAction !== null">, {{ config.omenPerTownAction }} per town action</span>
                    <span v-if="config.omenAtEndOfRound !== null"> and {{ config.omenAtEndOfRound }} at the end of a round</span>.
                    Emptied by a reveal.
                </p>

                <div class="mt-3 border-t border-stone-200 pt-3">
                    <h3 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold">
                        <Icon name="dread" class="text-stone-500" /> Dread X
                    </h3>
                    <div class="flex items-center gap-3">
                        <button type="button" class="step-lg" aria-label="Lower Dread" @click="adjustDread(-1)">−</button>
                        <span class="w-12 text-center font-serif text-3xl font-bold tabular-nums">{{ session.dread }}</span>
                        <button type="button" class="step-lg" aria-label="Raise Dread" @click="adjustDread(1)">+</button>
                        <span class="text-xs text-stone-500">started at {{ scenario.starting_dread }}</span>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">
                        A reveal that brings out fewer than this many cards triggers the scenario's Dread effect.
                    </p>
                    <p v-if="scenario.dread_effect" class="mt-1 text-xs leading-relaxed text-stone-600">{{ scenario.dread_effect }}</p>
                </div>
            </div>

            <!-- Who is playing, and what they have left -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="health" class="text-stone-500" /> Health
                </h2>
                <p class="mb-3 text-xs text-stone-500">The characters out on the table, and the health they have left.</p>

                <div v-if="session.party.length" class="mb-3 space-y-2">
                    <div
                        v-for="member in session.party"
                        :key="member.slug"
                        class="rounded border p-2"
                        :class="member.current === 0 ? 'border-red-400 bg-red-50' : 'border-stone-200'"
                    >
                        <div class="flex items-center gap-2">
                            <span class="h-4 w-4 shrink-0 rounded-full border border-stone-400" :style="partyStyle(characterBySlug[member.slug])" />
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ characterBySlug[member.slug]?.name }}</span>
                            <span v-if="member.current === 0" class="rounded bg-red-700 px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide text-red-50">
                                out
                            </span>
                        </div>

                        <div class="mt-1.5 flex items-center gap-2">
                            <button type="button" class="step" :aria-label="`Damage ${characterBySlug[member.slug]?.name}`" @click="adjustHealth(member.slug, -1)">−</button>
                            <span class="font-semibold tabular-nums" :class="member.current === 0 ? 'text-red-700' : ''">
                                {{ member.current }} / {{ fullHealth(member.slug) }}
                            </span>
                            <button type="button" class="step" :aria-label="`Heal ${characterBySlug[member.slug]?.name}`" @click="adjustHealth(member.slug, 1)">+</button>
                            <button type="button" class="ml-auto text-xs text-stone-500 underline hover:text-stone-900" @click="healToFull(member.slug)">
                                full
                            </button>
                        </div>

                        <p class="mt-1 text-[11px] text-stone-500">
                            hand {{ characterBySlug[member.slug]?.hand_size }} ·
                            {{ characterBySlug[member.slug]?.gold_per_round }} gold a round
                        </p>
                    </div>
                </div>

                <details class="text-sm">
                    <summary class="cursor-pointer text-xs text-stone-500 underline decoration-dotted hover:text-stone-900">
                        {{ session.party.length ? 'Change who is playing' : 'Pick who is playing' }}
                    </summary>
                    <div class="mt-2 space-y-1">
                        <label
                            v-for="character in characters"
                            :key="character.slug"
                            class="flex cursor-pointer items-center gap-2 rounded border p-2 text-sm"
                            :class="inParty(character.slug) ? 'border-stone-900 bg-stone-50' : 'border-stone-200 hover:border-stone-400'"
                        >
                            <input
                                type="checkbox"
                                :checked="inParty(character.slug)"
                                class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                                @change="toggleCharacter(character.slug)"
                            >
                            <span class="flex-1">{{ character.name }}</span>
                            <span class="text-xs text-stone-500">{{ character.health }} health</span>
                        </label>
                        <p v-if="!characters.length" class="text-sm text-stone-500">
                            No characters yet. <Link href="/characters/create" class="underline">Make one.</Link>
                        </p>
                    </div>
                </details>
            </div>

            <!-- The deck itself -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Entity deck</h2>
                <dl class="space-y-1 text-sm text-stone-700">
                    <div class="flex justify-between"><dt>Draw pile</dt><dd class="font-semibold tabular-nums">{{ session.drawPile.length }}</dd></div>
                    <div class="flex justify-between"><dt>Storyline</dt><dd class="font-semibold tabular-nums">{{ session.line.length }}</dd></div>
                    <div class="flex justify-between"><dt>Discard pile</dt><dd class="font-semibold tabular-nums">{{ session.discardPile.length }}</dd></div>
                    <div class="flex justify-between"><dt>Reveals</dt><dd class="font-semibold tabular-nums">{{ session.revealCount }}</dd></div>
                </dl>
                <p v-if="discardTop" class="mt-2 text-xs text-stone-600">
                    On top of the discard: <strong>{{ cardByKey[discardTop.key]?.name }}</strong>, pointing
                    {{ outgoingArrow(discardTop) === 'top' ? '▲ top' : '▼ bottom' }} — the next storyline's first card
                    reads that.
                </p>
                <button
                    type="button"
                    class="btn-ghost mt-3 w-full justify-center"
                    :disabled="session.discardPile.length === 0"
                    @click="reshuffleDiscardPile"
                >
                    <Icon name="shuffle" /> Shuffle the discard pile back in
                </button>
                <p class="mt-2 text-xs text-stone-500">
                    An empty draw pile is not resolved automatically. The rules pair this shuffle with Dread +1, but
                    that is a placeholder and open question 10, so the dial is left to you.
                </p>
            </div>

            <!-- Modules -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 font-serif text-base font-semibold">Modules</h2>
                <p class="mb-3 text-xs text-stone-500">
                    Changing modules starts a fresh game for that combination — this one is kept as it is.
                </p>
                <div class="space-y-1.5">
                    <label
                        v-for="module in available"
                        :key="module.slug"
                        class="flex cursor-pointer items-center gap-2 rounded border p-2 text-sm"
                        :class="chosen.includes(module.slug) ? 'border-stone-900 bg-stone-50' : 'border-stone-200 hover:border-stone-400'"
                    >
                        <input
                            type="checkbox"
                            :checked="chosen.includes(module.slug)"
                            class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="toggleModule(module.slug)"
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
                            @change="toggleModule(module.slug)"
                        >
                        <span class="flex-1">{{ module.name }}</span>
                    </label>
                    <p v-if="!available.length && !others.length" class="text-sm text-stone-500">No modules to add.</p>
                </div>
            </div>
        </div>

        <div class="min-w-0 space-y-8">
            <!-- The reveal step -->
            <section class="rounded-lg border border-stone-300 bg-white p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-serif text-lg font-semibold">Reveal</h2>
                        <p class="text-sm text-stone-600">
                            Cards come out until their total omen cost meets or exceeds the pool, then the pool empties.
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn-ghost" :disabled="session.drawPile.length === 0" @click="drawOne">
                            Draw one card
                        </button>
                        <button type="button" class="btn-primary" :disabled="session.drawPile.length === 0" @click="reveal">
                            <Icon name="play" /> Reveal against {{ session.omen }} omen
                        </button>
                    </div>
                </div>

                <!-- What the last reveal did. Reported, never applied. -->
                <div v-if="session.lastReveal" class="mt-3 border-t border-stone-200 pt-3 text-sm">
                    <p class="text-stone-700">
                        <strong>Reveal {{ session.lastReveal.step }}:</strong>
                        {{ session.lastReveal.cards }} {{ session.lastReveal.cards === 1 ? 'card' : 'cards' }},
                        {{ session.lastReveal.total }} omen of cost against a pool of {{ session.lastReveal.pool }}.
                    </p>

                    <p v-if="session.lastReveal.empowered" class="mt-1 font-semibold text-amber-800">
                        Empowered +{{ session.lastReveal.empowered }} on the last card revealed.
                    </p>
                    <p v-if="session.lastReveal.xValue !== null" class="mt-1 text-stone-700">
                        The X-cost card drained the rest: X = {{ session.lastReveal.xValue }}.
                        <span class="text-stone-500">It ends the reveal and skips the Dread check — open question 12.</span>
                    </p>
                    <p v-if="session.lastReveal.dreadTriggered" class="mt-1 font-semibold text-red-700">
                        Dread: fewer than {{ session.lastReveal.dreadX }} cards revealed. Apply the scenario's Dread
                        effect yourself — the tool does not, because the effect is still a placeholder.
                    </p>
                    <p v-if="session.lastReveal.ranOut" class="mt-1 font-semibold text-amber-800">
                        The draw pile ran out with {{ session.lastReveal.unmatched }} omen unmatched, which the pool is
                        still holding. Nothing was reshuffled: what an empty deck does is open question 10.
                    </p>
                </div>

                <div v-if="session.line.length === 0" class="mt-3 flex flex-wrap items-center gap-3 border-t border-stone-200 pt-3 text-sm">
                    <span class="field-micro">Arrow carried in</span>
                    <template v-if="discardTop">
                        <span class="rounded border border-stone-400 bg-white px-2.5 py-1">
                            {{ incomingArrow === 'top' ? '▲ top' : '▼ bottom' }}
                        </span>
                        <span class="text-xs text-stone-500">
                            from the top of the discard pile — the last card resolved ({{ firstCardArrowSource }}).
                        </span>
                    </template>
                    <template v-else>
                        <button
                            v-for="arrow in ['top', 'bottom']"
                            :key="arrow"
                            type="button"
                            class="rounded border px-2.5 py-1 text-sm"
                            :class="session.incomingChoice === arrow ? 'border-stone-900 bg-stone-900 text-stone-50' : 'border-stone-300 bg-white hover:border-stone-500'"
                            @click="setIncomingChoice(arrow)"
                        >
                            {{ arrow === 'top' ? '▲ top' : '▼ bottom' }}
                        </button>
                        <span class="text-xs text-stone-500">
                            The discard pile is empty, so this falls back to the default arrow
                            ({{ defaultArrow }}) — open question 2.
                        </span>
                    </template>
                </div>
            </section>

            <!-- Story beats -->
            <section v-if="beats.length">
                <h2 class="mb-3 font-serif text-lg font-semibold">Story beats</h2>
                <p v-if="storyComplete" class="mb-3 rounded border border-emerald-300 bg-emerald-50 p-3 text-sm text-emerald-900">
                    Every story beat has advanced.
                </p>
                <div class="space-y-2">
                    <div
                        v-for="(beat, index) in beats"
                        :key="beat.id"
                        class="rounded-lg border p-3"
                        :class="index === session.beatIndex ? 'border-amber-500 bg-amber-50' : index < session.beatIndex ? 'border-stone-200 bg-stone-50 opacity-70' : 'border-stone-200 bg-white opacity-70'"
                    >
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="rounded bg-amber-900 px-1.5 py-0.5 text-xs font-bold text-amber-50">Beat {{ beat.order }}</span>
                            <h3 class="font-serif text-base font-semibold">{{ beat.name }}</h3>
                            <span v-if="beat.dread_change" class="text-xs font-semibold text-red-700">
                                Dread {{ beat.dread_change > 0 ? '+' : '' }}{{ beat.dread_change }}
                            </span>
                            <span v-if="index === session.beatIndex" class="text-xs font-semibold uppercase tracking-wide text-amber-800">current</span>
                            <span v-else-if="index < session.beatIndex" class="text-xs uppercase tracking-wide text-stone-500">resolved</span>
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

                        <button
                            v-if="index === session.beatIndex"
                            type="button"
                            class="btn-primary mt-2"
                            @click="advanceBeat"
                        >
                            Advance this beat
                        </button>
                    </div>
                </div>
            </section>
            <p v-else class="text-sm text-stone-500">No story beats written for this scenario yet.</p>

            <!-- The storyline so far -->
            <section>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-serif text-lg font-semibold">Storyline</h2>
                    <button type="button" class="btn-ghost" :disabled="session.line.length === 0" @click="discardLine">
                        Discard the storyline ({{ session.line.length }})
                    </button>
                </div>
                <p class="mb-3 text-sm text-stone-600">
                    At the end of the entity phase the whole line is discarded in order, so the last card resolved sits
                    on top of the pile. A single card can go on its own, which makes its neighbours neighbours.
                </p>

                <div v-if="session.line.length" class="overflow-x-auto pb-4">
                    <div class="flex min-w-max items-start gap-3">
                        <div
                            v-for="(entry, index) in session.line"
                            :key="entry.key + '-' + index"
                            class="shrink-0"
                            :class="entry.step && entry.step !== session.line[index - 1]?.step ? 'ml-3 border-l border-stone-300 pl-3' : ''"
                        >
                            <p class="mb-1 text-center text-[11px] text-stone-500">
                                <span v-if="entry.step && entry.step !== session.line[index - 1]?.step" class="font-semibold text-stone-700">
                                    Reveal {{ entry.step }} ·
                                </span>
                                #{{ index + 1 }}
                            </p>

                            <button
                                type="button"
                                class="card-button"
                                :aria-label="`View ${cardByKey[entry.key]?.name || 'untitled card'} at full size`"
                                @click="zoom.open(lineCards, index)"
                            >
                                <CardPreview
                                    :card="cardByKey[entry.key]"
                                    kind="entity"
                                    :width="150"
                                    :highlight="entry.resolves !== 'single' ? entry.resolves : null"
                                />
                            </button>

                            <div class="mt-1.5 w-[150px] text-center text-xs">
                                <p v-if="entry.resolves !== 'single'" class="font-semibold text-amber-800">resolves {{ entry.resolves }} half</p>
                                <p v-else class="text-stone-500">single effect</p>

                                <p v-if="entry.empowered" class="font-semibold text-amber-800">Empowered +{{ entry.empowered }}</p>
                                <p v-if="entry.xValue !== null" class="text-stone-700">X = {{ entry.xValue }}</p>

                                <div class="mt-1 flex items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        class="rounded border px-1.5 py-0.5 text-[11px]"
                                        :class="entry.flipped ? 'border-amber-700 bg-amber-100 text-amber-900' : 'border-stone-300 hover:border-stone-500'"
                                        title="Redirect: flip this card's arrow, which changes the card after it"
                                        @click="toggleFlip(index)"
                                    >
                                        {{ entry.flipped ? 'redirected' : 'redirect' }} {{ outgoingArrow(entry) === 'top' ? '▲' : '▼' }}
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded border border-stone-300 px-1.5 py-0.5 text-[11px] hover:border-stone-500"
                                        :aria-label="`Discard ${cardByKey[entry.key]?.name || 'this card'} off the storyline`"
                                        title="Take this card off the storyline and onto the discard pile"
                                        @click="discardCard(index)"
                                    >
                                        discard
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
                    Nothing on the storyline. Build the omen pool, then reveal.
                </p>
            </section>
        </div>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        kind="entity"
        :highlight="zoomEntry && zoomEntry.resolves !== 'single' ? zoomEntry.resolves : null"
        :caption="zoomCaption"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>

<style scoped>
.step-lg {
    display: flex;
    height: 2.25rem;
    width: 2.25rem;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgb(214 211 209);
    font-size: 1.25rem;
    line-height: 1;
}
.step-lg:hover {
    border-color: rgb(120 113 108);
    background: rgb(245 245 244);
}
.step {
    width: 1.5rem;
    border-radius: 0.25rem;
    border: 1px solid rgb(214 211 209);
    line-height: 1.25rem;
}
.step:hover {
    border-color: rgb(120 113 108);
    background: rgb(245 245 244);
}
</style>
