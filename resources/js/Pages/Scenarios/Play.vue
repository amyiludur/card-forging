<script setup>
import { computed, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import Icon from '../../Components/Icon.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    scenario: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    startingCards: { type: Array, default: () => [] },
    beats: { type: Array, default: () => [] },
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
    discard: [],
    dread: 0,
    beatIndex: -1,
    incomingChoice: props.defaultArrow,
});

const freshSession = () => ({
    drawPile: shuffle(startingInstanceKeys.value),
    discard: [],
    dread: props.scenario.starting_dread ?? 0,
    beatIndex: props.beats.length ? 0 : -1,
    incomingChoice: props.defaultArrow,
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
    // drop it rather than crash the page over it.
    const known = (key) => cardByKey.value[key] !== undefined;

    Object.assign(session, {
        drawPile: (saved.drawPile ?? []).filter(known),
        discard: (saved.discard ?? []).filter((entry) => known(entry.key)),
        dread: saved.dread ?? (props.scenario.starting_dread ?? 0),
        beatIndex: saved.beatIndex ?? (props.beats.length ? 0 : -1),
        incomingChoice: saved.incomingChoice ?? props.defaultArrow,
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

// Redirect flips the arrow a card shows, which changes the card after it, not
// the one flipped — same rule as Storyline::resolve(), reimplemented here so a
// flip updates the table instantly instead of a round trip per card.
const recomputeFrom = (index) => {
    for (let i = Math.max(index, 0); i < session.discard.length; i++) {
        const entry = session.discard[i];
        const decidingArrow = i === 0 ? session.incomingChoice : outgoingArrow(session.discard[i - 1]);
        const card = cardByKey.value[entry.key];

        entry.decidingArrow = decidingArrow;
        entry.resolves = card?.layout === 'split' ? decidingArrow : 'single';
    }
};

const draw = () => {
    if (session.drawPile.length === 0) return;

    const [key, ...rest] = session.drawPile;
    const card = cardByKey.value[key];
    const decidingArrow = session.discard.length
        ? outgoingArrow(session.discard[session.discard.length - 1])
        : session.incomingChoice;

    session.discard.push({
        key,
        printedArrow: card.arrow,
        flipped: false,
        decidingArrow,
        resolves: card.layout === 'split' ? decidingArrow : 'single',
    });
    session.drawPile = rest;
};

const toggleFlip = (index) => {
    session.discard[index].flipped = !session.discard[index].flipped;
    recomputeFrom(index + 1);
};

const setIncomingChoice = (arrow) => {
    session.incomingChoice = arrow;
    recomputeFrom(0);
};

const reshuffleDiscard = () => {
    if (session.discard.length === 0) return;
    if (!confirm('Shuffle everything revealed so far back into the draw pile? This forgets the reveal order.')) return;

    session.drawPile = shuffle([...session.drawPile, ...session.discard.map((entry) => entry.key)]);
    session.discard = [];
};

const adjustDread = (delta) => {
    session.dread = Math.max(0, session.dread + delta);
};

const advanceBeat = () => {
    if (session.beatIndex < 0 || session.beatIndex >= props.beats.length) return;

    const beat = props.beats[session.beatIndex];
    session.dread = Math.max(0, session.dread + (beat.dread_change || 0));
    session.drawPile = shuffle([...session.drawPile, ...(beatInstanceKeys.value[beat.id] ?? [])]);
    session.beatIndex += 1;
};

const newGame = () => {
    if (!confirm('Start a new game? This reshuffles the deck and resets Dread and the story beats.')) return;
    Object.assign(session, freshSession());
};

const reload = (modules) =>
    router.get(`/scenarios/${props.scenario.slug}/play`, { modules }, { preserveState: true, preserveScroll: true, replace: true });

const toggleModule = (slug) =>
    reload(props.chosen.includes(slug) ? props.chosen.filter((s) => s !== slug) : [...props.chosen, slug]);

const discardCards = computed(() => session.discard.map((entry) => cardByKey.value[entry.key]));
const zoom = useCardZoom();

const zoomEntry = computed(() => (zoom.card ? session.discard[zoom.position - 1] ?? null : null));
const zoomCaption = computed(() => {
    const entry = zoomEntry.value;
    if (!entry) return '';
    return entry.resolves === 'single' ? 'single effect' : `resolves ${entry.resolves} half`;
});

const beatCardCount = (beat) => beat.cards.reduce((n, card) => n + Math.max(1, card.qty), 0);
const storyComplete = computed(() => props.beats.length > 0 && session.beatIndex >= props.beats.length);
</script>

<template>
    <Head :title="`${scenario.name} — playtest`" />

    <PageHeader
        :title="`${scenario.name} — playtest`"
        subtitle="Draw and reveal the entity deck, and track Dread and the story beats. Everything else — health, gold, hands — stays on paper."
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
            <!-- Dread -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="dread" class="text-stone-500" /> Dread
                </h2>
                <div class="flex items-center gap-3">
                    <button type="button" class="step-lg" @click="adjustDread(-1)">−</button>
                    <span class="w-12 text-center font-serif text-3xl font-bold tabular-nums">{{ session.dread }}</span>
                    <button type="button" class="step-lg" @click="adjustDread(1)">+</button>
                    <span class="text-xs text-stone-500">started at {{ scenario.starting_dread }}</span>
                </div>
                <p v-if="scenario.dread_effect" class="mt-2 text-xs leading-relaxed text-stone-600">{{ scenario.dread_effect }}</p>
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

            <!-- The deck itself -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Entity deck</h2>
                <dl class="space-y-1 text-sm text-stone-700">
                    <div class="flex justify-between"><dt>Draw pile</dt><dd class="font-semibold tabular-nums">{{ session.drawPile.length }}</dd></div>
                    <div class="flex justify-between"><dt>Revealed</dt><dd class="font-semibold tabular-nums">{{ session.discard.length }}</dd></div>
                </dl>
                <button
                    type="button"
                    class="btn-ghost mt-3 w-full justify-center"
                    :disabled="session.discard.length === 0"
                    @click="reshuffleDiscard"
                >
                    <Icon name="shuffle" /> Reshuffle revealed cards in
                </button>
                <p class="mt-2 text-xs text-stone-500">
                    An empty draw pile is not resolved automatically — what happens next is an open question
                    (fewer than X cards revealed). Reshuffle by hand when your table decides to.
                </p>
            </div>
        </div>

        <div class="min-w-0 space-y-8">
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

            <!-- Reveal -->
            <section>
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-serif text-lg font-semibold">Revealed</h2>
                    <button type="button" class="btn-primary" :disabled="session.drawPile.length === 0" @click="draw">
                        <Icon name="play" /> Draw next card ({{ session.drawPile.length }} left)
                    </button>
                </div>

                <div v-if="session.discard.length === 0" class="mb-3 flex flex-wrap items-center gap-3 text-sm">
                    <span class="field-micro">Arrow carried in from setup</span>
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
                        Open question: where the very first card's arrow comes from ({{ firstCardArrowSource }}), falls
                        back to {{ defaultArrow }}.
                    </span>
                </div>

                <div v-if="session.discard.length" class="overflow-x-auto pb-4">
                    <div class="flex min-w-max items-start gap-3">
                        <div
                            v-for="(entry, index) in session.discard"
                            :key="entry.key + '-' + index"
                            class="shrink-0"
                        >
                            <p class="mb-1 text-center text-[11px] text-stone-500">#{{ index + 1 }}</p>

                            <button
                                type="button"
                                class="card-button"
                                :aria-label="`View ${cardByKey[entry.key]?.name || 'untitled card'} at full size`"
                                @click="zoom.open(discardCards, index)"
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

                                <button
                                    type="button"
                                    class="mt-1 rounded border px-1.5 py-0.5 text-[11px]"
                                    :class="entry.flipped ? 'border-amber-700 bg-amber-100 text-amber-900' : 'border-stone-300 hover:border-stone-500'"
                                    title="Redirect: flip this card's arrow, which changes the next card drawn"
                                    @click="toggleFlip(index)"
                                >
                                    {{ entry.flipped ? 'redirected' : 'redirect' }} {{ outgoingArrow(entry) === 'top' ? '▲' : '▼' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <p v-else class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
                    Nothing drawn yet. Draw the first card to start the storyline.
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
</style>
