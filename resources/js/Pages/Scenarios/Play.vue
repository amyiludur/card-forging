<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import TableLayout from '../../Layouts/TableLayout.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';
import { describeX, resolveReveal } from '../../omenReveal';
import { band, normalise } from '../../colour';
import { evaluateEquation, isScaled, scaledAt, scaledMarkup } from '../../playerScaled';
import { renderMarkup } from '../../markup';
import TopBar from '../../Components/Play/TopBar.vue';
import EntityRail from '../../Components/Play/EntityRail.vue';
import StorylineRow from '../../Components/Play/StorylineRow.vue';
import BoardTable from '../../Components/Play/BoardTable.vue';
import SeatDock from '../../Components/Play/SeatDock.vue';
import SideDrawer from '../../Components/Play/SideDrawer.vue';
import TaleResults from '../../Components/Play/TaleResults.vue';
import ContextMenu from '../../Components/Play/ContextMenu.vue';
import HoverPreview from '../../Components/Play/HoverPreview.vue';
import TableSetup from '../../Components/Play/TableSetup.vue';
import DeckPeek from '../../Components/Play/DeckPeek.vue';
import PileViewer from '../../Components/Play/PileViewer.vue';
import { useSeatActions } from '../../Components/Play/useSeatActions';
import { useHotkeys } from '../../Components/Play/useHotkeys';
import { menu } from '../../Components/Play/tableOverlays';
import { STEPS, boardHealth, dealSeat, drawCards, goldAfterGoldStep, normaliseSeat, shuffle } from '../../goldfish';
import { dealByGroup } from '../../boardDeal';

// A table, not an editor page: the whole viewport, no sidebar, no page scroll.
defineOptions({ layout: TableLayout });

const page = usePage();

const props = defineProps({
    scenario: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    startingCards: { type: Array, default: () => [] },
    beats: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    boardCards: { type: Array, default: () => [] },
    town: { type: Array, default: () => [] },
    decks: { type: Array, default: () => [] },
    playerCards: { type: Object, default: () => ({}) },
    config: { type: Object, default: () => ({}) },
    firstCardArrowSource: { type: String, default: 'discard' },
    defaultArrow: { type: String, default: 'top' },
    // A scenario of a Tale playthrough: the step, the log so far, the entries
    // the Record results form asks for. Null outside one.
    tale: { type: Object, default: null },
    // Why a run named in the URL is not being played, when it is not.
    taleNotice: { type: String, default: null },
    // The step's own modules, which cannot be taken off.
    fixedModules: { type: Array, default: () => [] },
});

// One printed card can have several physical copies (qty > 1); each gets its
// own instance key so the shuffle and the reveal history can tell them apart.
const instanceKeys = (card) => Array.from({ length: Math.max(1, card.qty) }, (_, n) => `${card.id}#${n}`);

// A card whose copies point both ways gives each copy its own arrow — top
// copies first, the same order the print sheet uses — so everything reading
// `card.arrow` off a key (the storyline, the discard, the face drawn) sees the
// copy's arrow rather than the card's.
const addCopies = (map, card) => {
    const arrows = card.copy_arrows ?? [];
    const mixed = new Set(arrows).size > 1;

    instanceKeys(card).forEach((key, n) => {
        map[key] = mixed ? { ...card, arrow: arrows[n % arrows.length] } : card;
    });
};

const cardByKey = computed(() => {
    const map = {};

    for (const card of props.startingCards) addCopies(map, card);
    for (const beat of props.beats) {
        for (const card of beat.cards) addCopies(map, card);
    }

    return map;
});

const startingInstanceKeys = computed(() => props.startingCards.flatMap(instanceKeys));
const beatInstanceKeys = computed(() =>
    Object.fromEntries(props.beats.map((beat) => [beat.id, beat.cards.flatMap(instanceKeys)])),
);

const characterBySlug = computed(() => Object.fromEntries(props.characters.map((c) => [c.slug, c])));

const deckByKey = computed(() => Object.fromEntries(props.decks.map((d) => [d.key, d])));

// Everything below is kept in the browser only, the way a built deck already
// lives in a query string rather than a database row: this is one designer's
// solo playtest table, not a second source of truth for the game.
// A playthrough's game is kept apart from an ordinary game of the scenario.
const storageKey = computed(() => (props.tale
    ? `cardforge-play:run:${props.tale.run.id}:${props.tale.step.id}:${[...props.chosen].sort().join(',')}`
    : `cardforge-play:${props.scenario.slug}:${[...props.chosen].sort().join(',')}`));

const session = reactive({
    // False until the setup screen deals a game: a table nobody has sat down
    // at yet shows who is playing first, not an empty board.
    started: false,
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
    // Whoever is at the table: health, and — when they sat down with a deck —
    // every pile it moves between, their gold and the counters they keep.
    party: [],
    revealCount: 0,
    lastReveal: null,
    round: 1,
    step: 0,
    firstPlayer: 0,
    board: [],
    boardUid: 1,
    // Board cards destroyed since the current beat was reached, by name,
    // because that is how a beat's trigger counts them: "destroy 3 Risen".
    kills: {},
    districts: {},
    beatCounters: {},
    // Whether the first card of each reveal comes out face down. A switch
    // rather than something read off the Mist, because the table does not read
    // card text; the storyline says when the board and the switch disagree.
    shroudFirst: false,
    log: [],
});

/**
 * How many are at the table. This page is the only place in the app that knows,
 * which is why it is the only place an equation counting the players becomes a
 * number: a printed card cannot know, so it prints the equation instead.
 */
const players = computed(() => session.party.length);

/** One of the designer's numbers at the size of the party in front of us. */
const at = (value, equation, count = players.value) => scaledAt(value, equation, count) ?? 0;

const fullHealth = (slug) => {
    const character = characterBySlug.value[slug];

    return character ? at(character.health, character.health_equation) : 0;
};

/**
 * A scaled number as the designer wrote it, with {perPlayer} drawn as the icon,
 * for the notes beside the dials. Nothing on this page is a card, so it renders
 * through the markup the same way a card's text does.
 */
const showScaled = (value, equation) => renderMarkup(scaledMarkup(value, equation), {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
});

/**
 * The starting omen: the scenario's own number or equation, or the shared
 * tunable number when it sets none — the server has already picked which. One
 * place reads it, because three did and they had to agree.
 */
const omenSetting = computed(() => ({
    value: props.scenario.starting_omen ?? 0,
    equation: props.scenario.starting_omen_equation ?? null,
}));

const omenSeed = (count) => at(omenSetting.value.value, omenSetting.value.equation, count);

/** What the dials were seeded from, for the note that says so. */
const startingDread = computed(() => ({
    scaled: isScaled(props.scenario.starting_dread_equation),
    html: showScaled(props.scenario.starting_dread, props.scenario.starting_dread_equation),
    value: at(props.scenario.starting_dread, props.scenario.starting_dread_equation),
}));

const startingOmen = computed(() => ({
    scaled: isScaled(omenSetting.value.equation),
    html: showScaled(omenSetting.value.value, omenSetting.value.equation),
    value: omenSeed(players.value),
}));

/** How many cards a character draws up to, at this table. */
const handSizeOf = (slug) => {
    const character = characterBySlug.value[slug];
    return character ? at(character.hand_size, character.hand_size_equation) : 0;
};

const goldPerRoundOf = (slug) => {
    const character = characterBySlug.value[slug];
    return character ? at(character.gold_per_round, character.gold_per_round_equation) : 0;
};

/**
 * A seat dealt from its deck: the piles laid out, then a starting hand. The
 * rules say "draws a starting hand" without a size, so it is the character's
 * hand size — the number the draw step fills up to anyway.
 *
 * It also starts with the gold on its character card. The Gold step comes after
 * Actions, so without this nobody could buy anything in round 1 — the
 * designer's rule is that players get their gold on turn 1.
 */
const dealt = (slug, deckKey, count, withRewards = false) => {
    const character = characterBySlug.value[slug];
    const saved = deckByKey.value[deckKey];
    // In a Tale playthrough, the rewards this character has earned join their
    // deck — dealt like any other card, by its own start zone. Only the first
    // seat playing a character gets them; the server worked out which cards.
    const deck = withRewards && saved?.rewards?.length
        ? { ...saved, deck: [...saved.deck, ...saved.rewards] }
        : saved;
    const seat = {
        slug,
        current: at(character?.health, character?.health_equation, count),
        deckKey,
        gold: at(character?.gold_per_round, character?.gold_per_round_equation, count),
        pouch: 0,
        counters: [],
        townUsed: [],
        // Board cards dealt to this player (a townsfolk), kept out of the
        // zones so no player-card move — a discard, a reshuffle — reaches them.
        entities: [],
        ...dealSeat(deck, props.playerCards),
    };

    drawCards(seat, at(character?.hand_size, character?.hand_size_equation, count));

    return seat;
};

/**
 * The board cards a beat puts out when it is reached, or the setup's when null.
 * Only the scenario's own: a module's board cards (the Drowned, the Stormcrow)
 * are what its cards create, so they come out when a card says so — by hand,
 * from "Put a card out…" — never at setup, though no beat claims them either.
 */
const boardFor = (beatId) => props.boardCards.filter((c) => !c.module_id && (c.added_by_beat_id ?? null) === beatId && c.start !== 'dealt');

/**
 * The setup cards dealt one to each player rather than put on the board — the
 * Meddler's townsfolk. A beat's card and a module's never are.
 */
const dealtCards = computed(() => props.boardCards.filter((c) => !c.module_id && !c.added_by_beat_id && c.start === 'dealt'));

/** Puts a board card out and hands back the instance, so a caller can carry a counter onto it. */
const placeBoard = (id, target = session) => {
    const instance = { uid: target.boardUid++, id, damage: 0, counter: 0 };
    target.board.push(instance);
    return target.board[target.board.length - 1];
};

// A setup qty of 0 puts none out: a card another card brings out, by hand.
const placeAll = (cards, target = session) => {
    for (const card of cards) {
        for (let n = 0; n < (card.qty ?? 1); n++) placeBoard(card.id, target);
    }
};

/** Which seat is the first in a lineup to play a character: the one their rewards go to. */
const firstSeatOf = (party, slug) => party.findIndex((member) => member.slug === slug);

const freshSession = (party = []) => {
    const fresh = freshTable(party);

    // The entity board as setup lays it out, then whatever beat 1 puts out on
    // being reached — a beat's board cards come out when it is reached and its
    // deck cards when it advances, because that is how the beats are written.
    placeAll(boardFor(null), fresh);
    if (props.beats.length) placeAll(boardFor(props.beats[0].id), fresh);

    // Each deal group on its own: one of its cards to each seat in lineup
    // order, the rest kept aside face down. More seats than cards deals what
    // there is, and Start says who went without.
    for (const group of dealByGroup(dealtCards.value, fresh.party.length)) {
        group.seats.forEach((ids, i) => {
            for (const id of ids) fresh.party[i].entities.push({ uid: fresh.boardUid++, id, exhausted: false, counter: 0 });
        });
        fresh.boardAside.push(...group.aside.map((id) => ({ uid: fresh.boardUid++, id, counter: 0 })));
    }

    return fresh;
};

const freshTable = (party = []) => ({
    started: true,
    drawPile: shuffle(startingInstanceKeys.value),
    line: [],
    discardPile: [],
    // Seeded against the party this game starts with, so "1 + 1 per player" is
    // a real number on the dial rather than something to work out by hand.
    omen: omenSeed(party.length),
    dread: at(props.scenario.starting_dread, props.scenario.starting_dread_equation, party.length),
    beatIndex: props.beats.length ? 0 : -1,
    incomingChoice: props.defaultArrow,
    // A new game keeps who is playing, the deck each sat down with and the
    // counters each keeps, and deals everything else again.
    party: party.map((member, index) => ({
        ...dealt(member.slug, member.deckKey, party.length, firstSeatOf(party, member.slug) === index),
        counters: (member.counters ?? []).map((c) => ({ name: c.name, value: 0 })),
    })),
    revealCount: 0,
    lastReveal: null,
    round: 1,
    step: 0,
    firstPlayer: 0,
    board: [],
    boardAside: [],
    boardUid: 1,
    kills: {},
    districts: {},
    beatCounters: {},
    shroudFirst: false,
    log: [],
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
        Object.assign(session, { ...freshSession(), started: false });
        undoStack.value = [];
        return;
    }

    // A card edited or removed since this game started leaves a dangling key;
    // drop it rather than crash the page over it. Same for a character deleted
    // out from under a party.
    const known = (key) => cardByKey.value[key] !== undefined;

    // Worked out first: a dial this save has no number for is seeded against
    // the party being restored, not against whoever the page is still holding.
    const knownCard = (instance) => props.playerCards[instance.id] !== undefined;
    const knownBoard = new Set(props.boardCards.map((c) => c.id));

    // A seat is a saved deck, so one whose deck has since been deleted — or one
    // saved before seats had decks at all — leaves the table; a card deleted
    // since is dropped out of whichever pile it was in.
    const party = (saved.party ?? []).filter((member) => member.zones && deckByKey.value[member.deckKey] && characterBySlug.value[member.slug]).map((member) => {
        return normaliseSeat({
            gold: 0,
            pouch: 0,
            counters: [],
            townUsed: [],
            ...member,
            zones: Object.fromEntries(Object.entries(member.zones).map(([zone, list]) => [zone, list.filter(knownCard)])),
            entities: (member.entities ?? []).filter((instance) => knownBoard.has(instance.id)),
        });
    });

    Object.assign(session, {
        // A game saved before there was a setup screen is a game under way if
        // anyone sat down or anything happened in it.
        started: saved.started ?? (party.length > 0 || (saved.log ?? []).length > 0),
        drawPile: (saved.drawPile ?? []).filter(known),
        line: (saved.line ?? []).filter((entry) => known(entry.key)),
        discardPile: (saved.discardPile ?? []).filter((entry) => known(entry.key)),
        omen: saved.omen ?? omenSeed(party.length),
        dread: saved.dread ?? at(props.scenario.starting_dread, props.scenario.starting_dread_equation, party.length),
        beatIndex: saved.beatIndex ?? (props.beats.length ? 0 : -1),
        incomingChoice: saved.incomingChoice ?? props.defaultArrow,
        party,
        revealCount: saved.revealCount ?? 0,
        lastReveal: saved.lastReveal ?? null,
        round: saved.round ?? 1,
        step: saved.step ?? 0,
        firstPlayer: saved.firstPlayer ?? 0,
        board: (saved.board ?? []).filter((instance) => knownBoard.has(instance.id)),
        // Dealt cards nobody was given; a save from before there were any has none.
        boardAside: (saved.boardAside ?? []).filter((instance) => knownBoard.has(instance.id)),
        boardUid: saved.boardUid ?? 1,
        kills: saved.kills ?? {},
        districts: saved.districts ?? {},
        beatCounters: saved.beatCounters ?? {},
        shroudFirst: saved.shroudFirst ?? false,
        log: saved.log ?? [],
    });
    undoStack.value = [];
};

/**
 * Undo, and the log beside it. Every move on the table goes through act(): a
 * snapshot of the session before it, and a line saying what happened. The
 * snapshots live in memory only — a reload keeps the game and drops its undo
 * history, which is the same bargain a real table makes.
 */
const undoStack = ref([]);
const UNDO_DEPTH = 80;

const note = (text) => {
    session.log.unshift({ round: session.round, step: STEPS[session.step]?.label ?? '', text });
    if (session.log.length > 400) session.log.length = 400;
};

const act = (label, fn) => {
    // The label travels with the snapshot, so undoing can say what it undid.
    undoStack.value.push({ snapshot: JSON.stringify(session), label });
    if (undoStack.value.length > UNDO_DEPTH) undoStack.value.shift();

    // Noted first, so anything the move notes on the way past reads after it.
    if (label) note(label);
    fn();
};

/** A line that says what was just undone, and fades. Not part of the game. */
const toast = ref(null);
let toastTimer = null;
const say = (text) => {
    clearTimeout(toastTimer);
    toast.value = { text, id: Date.now() };
    toastTimer = setTimeout(() => (toast.value = null), 2200);
};

const undo = () => {
    const last = undoStack.value.pop();
    if (!last) return;
    Object.assign(session, JSON.parse(last.snapshot));
    say(last.label ? `Undid: ${last.label}` : 'Undid the last change');
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
        xValue: null,
        ...extra,
    });
};

/** A single card, for the effects that ask for one outside the reveal step. */
const drawOne = () => {
    if (session.drawPile.length === 0) return;

    act(`Drew ${cardByKey.value[session.drawPile[0]]?.name} onto the storyline`, () => {
        pushReveal(session.drawPile[0]);
        session.drawPile = session.drawPile.slice(1);
    });
};

/** The reveal step: cards come out until their cost meets or exceeds the pool. */
const reveal = () => act('Revealed', revealNow);

const revealNow = () => {
    const result = resolveReveal(
        session.drawPile.map((key) => cardByKey.value[key]),
        {
            pool: session.omen,
            dreadX: session.dread,
        },
    );

    const step = session.revealCount + 1;
    // taken is a prefix of the draw pile, so the keys line up by position.
    session.drawPile.slice(0, result.taken.length).forEach((key, i) => {
        const entry = result.taken[i];

        pushReveal(key, {
            step,
            xValue: entry.isX ? entry.cost : null,
            xMax: entry.isX ? entry.max : null,
            // Shrouded: its cost has already counted towards the reveal above,
            // and it comes out face down, so nobody sees its arrow first.
            faceDown: session.shroudFirst && i === 0,
        });
    });

    if (session.shroudFirst && result.taken.length) note('The first card revealed is Shrouded: face down, arrow and effect hidden.');

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
        endedOnX: result.endedOnX,
        xValue: result.endedOnX ? result.taken[result.taken.length - 1].cost : null,
        xMax: result.endedOnX ? result.taken[result.taken.length - 1].max : null,
        dreadTriggered: result.dreadTriggered,
        dreadX: session.dread,
        ranOut: result.ranOut,
        unmatched: result.unmatched,
    };
};

const toggleFlip = (index) => act(`Redirected ${cardByKey.value[session.line[index].key]?.name}`, () => {
    session.line[index].flipped = !session.line[index].flipped;
    recomputeFrom(index + 1);
});

/**
 * Face down is a way of holding a card, not a rule: the Mist's Shrouded is
 * why it exists, but the table only hides the face and the arrow. The chain
 * underneath still runs, because the arrow is still printed.
 */
const toggleFaceDown = (index) => act(null, () => {
    session.line[index].faceDown = !session.line[index].faceDown;
});

/** The switch the Mist needs: shroud the first card of every reveal from now on. */
const toggleShroudFirst = () => act(session.shroudFirst ? 'Stopped shrouding the first card revealed' : 'Shrouding the first card revealed each round', () => {
    session.shroudFirst = !session.shroudFirst;
});

/** The Watchfire's move, and any other "turn Shrouded cards face up": every face-down card on the line. */
const turnShroudedUp = () => {
    const count = session.line.filter((entry) => entry.faceDown).length;
    if (!count) return;

    act(`Turned ${count} Shrouded ${count === 1 ? 'card' : 'cards'} face up`, () => {
        session.line.forEach((entry) => (entry.faceDown = false));
    });
};

/**
 * Shrouded stays hidden "until it resolves or is lit", and a card cannot be
 * resolved without reading it, so ticking one off turns it face up.
 */
const toggleResolved = (index) => act(null, () => {
    const entry = session.line[index];
    entry.resolved = !entry.resolved;
    if (entry.resolved) entry.faceDown = false;
});

/**
 * A board card whose text names {shrouded} — the Mist. The table never acts on
 * it; it only says when the board and the switch disagree.
 */
const boardShrouds = computed(() => session.board
    .map((instance) => props.boardCards.find((card) => card.id === instance.id))
    .filter((card) => card && /\{shrouded\}/.test(card.text ?? ''))
    .map((card) => card.name));

/**
 * One card off the storyline and onto the discard pile. Its neighbours become
 * neighbours, which is Redirect's "remove" form and is why the chain from here
 * on is worked out again — that form is a candidate, not settled, so nothing
 * else about it is assumed.
 */
const discardCard = (index) => act(`Took ${cardByKey.value[session.line[index].key]?.name} off the storyline`, () => {
    const [entry] = session.line.splice(index, 1);
    // The discard pile is open, and its top card's arrow is what the next
    // storyline reads, so nothing goes onto it face down.
    entry.faceDown = false;
    session.discardPile.push(entry);
    recomputeFrom(index);
});

/**
 * The end of the entity phase: the whole storyline is discarded in order, so
 * the last card resolved ends up on top of the pile — which is where the next
 * storyline's first card reads its arrow from.
 */
const discardLine = () => {
    if (session.line.length === 0) return;

    act('Discarded the storyline', () => {
        session.line.forEach((entry) => (entry.faceDown = false));
        session.discardPile.push(...session.line);
        session.line = [];
    });
};

const setIncomingChoice = (arrow) => act(null, () => {
    session.incomingChoice = arrow;
    recomputeFrom(0);
});

/**
 * The rules pair this with raising Dread X by 1, but that is a placeholder and
 * open question 10, so the shuffle is by hand and the dial is left alone.
 */
const reshuffleDiscardPile = () => {
    if (session.discardPile.length === 0) return;
    if (!confirm('Shuffle the discard pile back into the draw pile? The storyline stays where it is.')) return;

    act('Shuffled the entity discard back in', () => {
        session.drawPile = shuffle([...session.drawPile, ...session.discardPile.map((entry) => entry.key)]);
        session.discardPile = [];
    });
};

/**
 * Looking at the top of the entity deck. How many is a ref of the page's, not
 * session state, like the active seat: undo restores the game, never shuts or
 * opens a view of it. The look itself is one move — whatever went back on top
 * in its new order, whatever went under everything, whatever was discarded
 * (in order, so the last one is the discard's new top) — so one undo takes it
 * all back.
 */
const peekCount = ref(0);

const applyPeek = ({ looked, top, bottom, discard }) => {
    const parts = [`${top.length} back on top`];
    if (bottom.length) parts.push(`${bottom.length} to the bottom`);
    if (discard.length) parts.push(`discarded ${discard.map((key) => cardByKey.value[key]?.name).join(', ')}`);

    act(`Looked at the top ${looked} of the entity deck: ${parts.join(', ')}`, () => {
        session.drawPile = [...top, ...session.drawPile.slice(looked), ...bottom];
        discard.forEach((key) => session.discardPile.push({
            key,
            printedArrow: cardByKey.value[key]?.arrow,
            flipped: false,
            decidingArrow: null,
            resolves: null,
            step: null,
            xValue: null,
        }));
    });
    peekCount.value = 0;
};

const addOmen = (delta) => {
    session.omen = Math.max(0, session.omen + delta);
};

const adjustOmen = (delta) => act(`Omen ${delta > 0 ? '+' : ''}${delta}`, () => addOmen(delta));

const adjustDread = (delta) => act(`Dread ${delta > 0 ? '+' : ''}${delta}`, () => {
    session.dread = Math.max(0, session.dread + delta);
});

/**
 * A seat is a deck built in the deck builder and saved there: the deck says
 * which character it is, so picking one is picking who plays. The same
 * character can sit down twice with two decks, which is a playtest's business.
 */
const seated = (deckKey) => session.party.some((member) => member.deckKey === deckKey);

// Joining mid-game deals a seat; the dials are never re-seeded for it.
const toggleDeck = (deckKey) => {
    const deck = deckByKey.value[deckKey];
    if (!deck) return;

    act(`${deck.name} ${seated(deckKey) ? 'left the table' : 'sat down'}`, () => {
        // A board card dealt to a seat that leaves goes back on the set-aside
        // pile rather than off the table with them. A seat that sits down is
        // dealt nothing: the deal happens at Start, the same as the dials.
        session.party.find((member) => member.deckKey === deckKey)?.entities?.forEach((instance) => table.setAside(instance));

        session.party = seated(deckKey)
            ? session.party.filter((member) => member.deckKey !== deckKey)
            // A seat added mid-game gets the rewards only if nobody already
            // seated plays that character.
            : [...session.party, dealt(deck.character, deckKey, session.party.length + 1, !session.party.some((m) => m.slug === deck.character))];
        session.firstPlayer = Math.min(session.firstPlayer, Math.max(0, session.party.length - 1));
    });
};

const advanceBeat = () => {
    if (session.beatIndex < 0 || session.beatIndex >= props.beats.length) return;

    const beat = props.beats[session.beatIndex];

    act(`Beat ${beat.order}, ${beat.name}, advanced`, () => {
        session.dread = Math.max(0, session.dread + at(beat.dread_change || 0, beat.dread_change_equation));
        session.drawPile = shuffle([...session.drawPile, ...(beatInstanceKeys.value[beat.id] ?? [])]);
        session.beatIndex += 1;
        session.kills = {};

        const next = props.beats[session.beatIndex];
        if (next) {
            const out = boardFor(next.id);
            placeAll(out);
            if (out.length) note(`Beat ${next.order} put ${out.map((c) => c.name).join(', ')} on the board.`);
        }
    });
};

const adjustBeatCounter = (beat, delta) => act(`${beat.name} counters ${delta > 0 ? '+' : ''}${delta}`, () => {
    session.beatCounters[beat.id] = Math.max(0, (session.beatCounters[beat.id] ?? 0) + delta);
});

/**
 * Draw for a seat through the deck-out rule. The rule is tunable
 * (`playerDeckOutReshuffle`, `playerDeckOutOmen`) and a placeholder, so this
 * only reads it, and says when it fired.
 */
const drawFor = (seat, count) => drawCards(seat, count, {
    reshuffle: !!props.config.playerDeckOutReshuffle,
    onDeckOut: () => {
        const omen = Number(props.config.playerDeckOutOmen ?? 0);
        if (omen) addOmen(omen);
        note(`${characterBySlug.value[seat.slug]?.name}'s deck ran out: discard shuffled back in${omen ? `, +${omen} omen` : ''}.`);
    },
});

/**
 * Do what the current step of the round does, then move to the next. Only the
 * mechanical part is done — reveal, ready and draw, gold, and the end of the
 * round — because what a card or a board card says is the table's to read.
 */
const runStep = () => {
    const step = STEPS[session.step];

    act(step.label, () => {
        if (step.key === 'reveal') revealNow();

        if (step.key === 'draw') {
            for (const seat of session.party) {
                seat.zones.play.forEach((c) => (c.exhausted = false));
                seat.entities.forEach((c) => (c.exhausted = false));
                drawFor(seat, Math.max(0, handSizeOf(seat.slug) - seat.zones.hand.length));
            }
        }

        if (step.key === 'gold') {
            const carries = !!props.config.goldCarriesOver;
            for (const seat of session.party) seat.gold = goldAfterGoldStep(seat.gold, goldPerRoundOf(seat.slug), carries);
        }

        if (step.key === 'end') {
            const omen = Number(props.config.omenAtEndOfRound ?? 0);
            if (omen) addOmen(omen);
            session.discardPile.push(...session.line);
            session.line = [];

            // Gold is left alone here. It used to be zeroed, which threw away
            // the Gold step's gold before the next Actions could spend it; the
            // Gold step topping up to the card's number is what keeps it from
            // building up (goldAfterGoldStep).
            for (const seat of session.party) seat.townUsed = [];

            if (session.party.length) session.firstPlayer = (session.firstPlayer + 1) % session.party.length;
            session.round += 1;
        }

        session.step = (session.step + 1) % STEPS.length;
    });
};

const jumpToStep = (index) => act(null, () => (session.step = index));

/**
 * The setup screen: who sits down, before anything is dealt. Shown until a
 * game has started, and again when New game is pressed — which no longer needs
 * a confirm, since nothing is replaced until Start is.
 *
 * The lineup is a ref of the page's, not session state, for the same reason the
 * active seat is: it is a choice about the next game, so undo never touches it,
 * and it survives a module change (a different saved game) while setting up.
 * The last lineup is remembered per scenario so a rematch is one click.
 */
const setupOpen = ref(false);
const showSetup = computed(() => !session.started || setupOpen.value);
const lineupKey = computed(() => `cardforge-play-lineup:${props.scenario.slug}`);

const rememberedLineup = () => {
    try {
        const raw = JSON.parse(localStorage.getItem(lineupKey.value) ?? '[]');
        return Array.isArray(raw) ? raw.filter((key) => deckByKey.value[key]) : [];
    } catch {
        return [];
    }
};

const lineup = ref(session.party.length ? session.party.map((m) => m.deckKey) : rememberedLineup());

const newGame = () => {
    lineup.value = session.party.length ? session.party.map((m) => m.deckKey) : rememberedLineup();
    setupOpen.value = true;
};

const startGame = () => {
    const party = lineup.value
        .filter((key) => deckByKey.value[key])
        .map((key) => ({
            slug: deckByKey.value[key].character,
            deckKey: key,
            // A rematch keeps the named counters a seat was keeping, at zero.
            counters: session.party.find((m) => m.deckKey === key)?.counters ?? [],
        }));
    if (!party.length) return;

    Object.assign(session, freshSession(party));
    undoStack.value = [];
    note(`New game: ${party.map((m) => characterBySlug.value[m.slug]?.name ?? m.slug).join(', ')}.`);

    // Who was dealt which board card in each group, and who went without.
    const groupNames = [...new Set(dealtCards.value.map((c) => c.deal_group ?? ''))];
    for (const group of groupNames) {
        const inGroup = (instance) => (boardCardOf(instance).deal_group ?? '') === group;
        const given = session.party
            .map((seat) => [seat, seat.entities.find(inGroup)])
            .filter(([, instance]) => instance)
            .map(([seat, instance]) => `${boardCardOf(instance).name} to ${nameOf(seat)}`);
        const without = session.party.filter((seat) => !seat.entities.some(inGroup)).map(nameOf);
        const aside = session.boardAside.filter(inGroup).length;

        if (given.length) note(`Dealt ${group ? `${group}: ` : ''}${given.join(', ')}; ${aside} kept aside.`);
        if (without.length) note(`No ${group || 'dealt card'} left for ${without.join(', ')}.`);
    }

    // Say which rewards went where, so the log shows what the Tale dealt.
    party.forEach((member, index) => {
        const rewards = deckByKey.value[member.deckKey]?.rewards ?? [];
        if (!rewards.length || firstSeatOf(party, member.slug) !== index) return;
        const names = rewards.map((id) => props.playerCards[id]?.name ?? `#${id}`).join(', ');
        note(`Rewards dealt: ${names} to ${characterBySlug.value[member.slug]?.name ?? member.slug}.`);
    });

    try {
        localStorage.setItem(lineupKey.value, JSON.stringify(lineup.value));
    } catch {
        // Remembering the lineup is a convenience; losing it loses nothing.
    }

    activeSeat.value = 0;
    setupOpen.value = false;
};

/** The dials and a seat's numbers at a party size, for the setup screen to show. */
const dialsAt = (count) => ({
    dread: at(props.scenario.starting_dread, props.scenario.starting_dread_equation, count),
    omen: omenSeed(count),
});

const seatAt = (slug, count) => {
    const character = characterBySlug.value[slug];

    return {
        health: at(character?.health, character?.health_equation, count),
        hand: at(character?.hand_size, character?.hand_size_equation, count),
        gold: at(character?.gold_per_round, character?.gold_per_round_equation, count),
    };
};

// In a playthrough the run stays in the URL, so picking a module keeps the Tale.
const reload = (modules) =>
    router.get(`/scenarios/${props.scenario.slug}/play`, props.tale ? { modules, run: props.tale.run.id } : { modules }, { preserveState: true, preserveScroll: true, replace: true });

// The step's own modules are the Tale's choice and stay on.
const toggleModule = (slug) => {
    if (props.fixedModules.includes(slug)) return;
    reload(props.chosen.includes(slug) ? props.chosen.filter((s) => s !== slug) : [...props.chosen, slug]);
};

// Record results: the Tale's log entries, for the characters at the table.
const recordOpen = ref(false);
const recordParty = computed(() => {
    const seen = new Set();

    return session.party
        .filter((member) => (seen.has(member.slug) ? false : seen.add(member.slug)))
        .map((member) => ({ slug: member.slug, name: characterBySlug.value[member.slug]?.name ?? member.slug }));
});

const lineCards = computed(() => session.line.map((entry) => cardByKey.value[entry.key]));
const zoom = useCardZoom();

// The full-size card pages through its neighbours, so a Shrouded one is left
// out of the list rather than shown by stepping past it.
const zoomLine = (index) => {
    const shown = session.line.map((entry, i) => (entry.faceDown ? null : i)).filter((i) => i !== null);
    if (!shown.includes(index)) return;

    zoom.open(shown.map((i) => lineCards.value[i]), shown.indexOf(index), 'entity');
};

/** What a seat, the board and the town are handed to change the table with. */
const table = reactive({
    act,
    note,
    addOmen,
    placeBoard: (id) => placeBoard(id),
    boardCardsById: computed(() => Object.fromEntries(props.boardCards.map((c) => [c.id, c]))),
    // The dealt cards nobody holds. Callers wrap these in act() themselves.
    takeAside: (uid) => {
        const index = session.boardAside.findIndex((c) => c.uid === uid);
        return index === -1 ? null : session.boardAside.splice(index, 1)[0];
    },
    setAside: (instance) => session.boardAside.push({ uid: instance.uid, id: instance.id, counter: instance.counter ?? 0 }),
    nextBoardUid: () => session.boardUid++,
    zoom: (list, index, kind) => zoom.open(list, index, kind),
    config: computed(() => props.config),
    cardsById: computed(() => props.playerCards),
});

/**
 * The dealt cards nobody holds, opened face up: each can be given to a seat or
 * put on the entity board — by hand, because what brings one out is a card's
 * text and the table does not read it. Whether it is open is a page ref, like
 * every other viewer, so undo never shuts it.
 */
const viewingAside = ref(false);
// Once the last card is given out the viewer is shut, not just hidden, so the
// next card set aside does not pop it open again unasked.
watch(() => session.boardAside.length, (count) => {
    if (!count) viewingAside.value = false;
});
const boardCardOf = (instance) => table.boardCardsById[instance.id] ?? {};

const giveFromAside = (uid, seatIndex) => {
    const seat = session.party[seatIndex];
    const card = boardCardOf({ id: session.boardAside.find((c) => c.uid === uid)?.id });

    act(`gave ${card.name} to ${nameOf(seat)}`, () => {
        const instance = table.takeAside(uid);
        if (instance) seat.entities.push({ ...instance, exhausted: false });
    });
};

const asideToBoard = (uid) => {
    const card = boardCardOf({ id: session.boardAside.find((c) => c.uid === uid)?.id });

    act(`put ${card.name} on the entity board`, () => {
        const instance = table.takeAside(uid);
        if (instance) placeBoard(instance.id).counter = instance.counter ?? 0;
    });
};

const asideMenu = (instance) => ({
    title: boardCardOf(instance).name,
    items: [
        ...session.party.map((seat, index) => ({ label: `Give to ${nameOf(seat)}`, action: () => giveFromAside(instance.uid, index) })),
        ...(session.party.length ? [{ divider: true }] : []),
        { label: 'Put on the entity board', action: () => asideToBoard(instance.uid) },
    ],
});

const healthOf = (card) => boardHealth(card.health, players.value, evaluateEquation);
const nameOf = (seat) => characterBySlug.value[seat.slug]?.name ?? seat.slug;
const currentStep = computed(() => STEPS[session.step] ?? STEPS[0]);

// A zoomed entity card is a storyline card, except while the deck is being
// looked at: those are still in the deck and resolve nothing yet.
const zoomEntry = computed(() => (zoom.card && zoom.kind === 'entity' && !peekCount.value ? session.line[zoom.position - 1] ?? null : null));
const zoomCaption = computed(() => {
    const entry = zoomEntry.value;
    if (!entry) return '';

    const parts = [entry.resolves === 'single' ? 'single effect' : `resolves ${entry.resolves} half`];
    if (entry.xValue !== null && entry.xValue !== undefined) parts.push(describeX(entry.xValue, entry.xMax).text);

    return parts.join(' · ');
});

// A hero's own two colours, so the health tracker reads like their card does.
const partyStyle = (character) => {
    const [from, to] = [character?.colour, character?.colour_secondary].map(normalise).filter(Boolean);

    return { background: band(from, to, '135deg') ?? '#3f2b56' };
};

const storyComplete = computed(() => props.beats.length > 0 && session.beatIndex >= props.beats.length);
const currentBeat = computed(() => props.beats[session.beatIndex] ?? null);

/** A beat's change to Dread, as written and at this table, or '' for none. */
const beatDread = (beat) => {
    if (!beat) return '';
    if (beat.dread_change_equation) {
        const value = at(beat.dread_change, beat.dread_change_equation);
        return `Dread ${showScaled(beat.dread_change, beat.dread_change_equation)} = ${value > 0 ? '+' : ''}${value}`;
    }

    return beat.dread_change ? `Dread ${beat.dread_change > 0 ? '+' : ''}${beat.dread_change}` : '';
};

/**
 * Which seat the dock lays out. A ref of the page's own and deliberately not
 * part of the session: undo restores the game, never the view of it, so
 * undoing a move can never switch seats. Clamped whenever the party changes
 * size, since a seat leaving can take the active index with it.
 */
const activeSeat = ref(0);
watch(() => session.party.length, (length) => {
    activeSeat.value = Math.min(activeSeat.value, Math.max(0, length - 1));
});

/**
 * The active seat's moves, made once here and handed to the dock, so a click,
 * a menu item, a drop and a hotkey all call the same function. Bound to
 * whichever seat is active at the moment the move runs.
 */
const activeSeatState = computed(() => session.party[activeSeat.value] ?? null);
const seatActions = useSeatActions({
    seat: activeSeatState,
    name: computed(() => (activeSeatState.value ? nameOf(activeSeatState.value) : '')),
    handSize: computed(() => (activeSeatState.value ? handSizeOf(activeSeatState.value.slug) : 0)),
    table,
});

// The drawer is a view of the table too, so it is not in the session either.
const drawerOpen = ref(false);
// A playthrough's drawer opens on the Tale, where Record results is.
const drawerTab = ref(props.tale ? 'tale' : 'log');
const openDrawer = (tab) => {
    drawerTab.value = tab;
    drawerOpen.value = true;
};

const makeFirst = (index) => act('First player token moved', () => (session.firstPlayer = index));

// The resolve step's work is done once every card on the storyline is ticked
// off, so the Next button asks to be pressed. Nothing resolves on its own.
const stepReady = computed(() =>
    currentStep.value.key === 'resolve' && session.line.length > 0 && session.line.every((entry) => entry.resolved),
);

const helpOpen = ref(false);
const dock = ref(null);

useHotkeys({
    space: runStep,
    'mod+z': undo,
    d: () => activeSeatState.value && seatActions.draw(1),
    1: () => session.party.length > 0 && (activeSeat.value = 0),
    2: () => session.party.length > 1 && (activeSeat.value = 1),
    3: () => session.party.length > 2 && (activeSeat.value = 2),
    4: () => session.party.length > 3 && (activeSeat.value = 3),
    s: () => dock.value?.toggleTray('shop'),
    t: () => dock.value?.toggleTray('town'),
    l: () => {
        if (drawerOpen.value && drawerTab.value === 'log') drawerOpen.value = false;
        else openDrawer('log');
    },
    '?': () => (helpOpen.value = !helpOpen.value),
    escape: () => {
        if (helpOpen.value) helpOpen.value = false;
        else if (dock.value?.closeAll()) return;
        else drawerOpen.value = false;
    },
}, {
    // The menu and the full-size card each own the keyboard while open, and
    // nothing on the table answers a key while the setup screen covers it.
    blocked: () => menu.open || !!zoom.card || showSetup.value || !!peekCount.value,
});

// Built as one string rather than spans, or the template's own line breaks put
// a space in front of the commas.
const omenNote = computed(() => {
    const sources = ['cards played'];
    if (props.config.omenPerTownAction !== null) sources.push(`${props.config.omenPerTownAction} per town action`);
    if (props.config.omenAtEndOfRound !== null) sources.push(`${props.config.omenAtEndOfRound} at the end of a round`);

    const last = sources.pop();

    return `Built up by ${sources.length ? `${sources.join(', ')} and ${last}` : last}. Emptied by a reveal.`;
});
</script>

<template>
    <Head :title="`${scenario.name} — playtest`" />

    <TableSetup
        v-if="showSetup"
        v-model:lineup="lineup"
        :scenario="scenario"
        :decks="decks"
        :character-by-slug="characterBySlug"
        :party-style="partyStyle"
        :available="available"
        :others="others"
        :chosen="chosen"
        :dials-at="dialsAt"
        :seat-at="seatAt"
        :starting-dread="startingDread"
        :starting-omen="startingOmen"
        :in-progress="session.started"
        :tale="tale"
        :tale-notice="taleNotice"
        :fixed-modules="fixedModules"
        :player-cards="playerCards"
        @start="startGame"
        @cancel="setupOpen = false"
        @toggle-module="toggleModule"
    />

    <template v-else>
    <TopBar
        :scenario="scenario"
        :round="session.round"
        :step="session.step"
        :omen="session.omen"
        :config="config"
        :can-undo="undoStack.length > 0"
        :drawer-open="drawerOpen"
        :ready="stepReady"
        :help-open="helpOpen"
        @toggle-help="helpOpen = !helpOpen"
        @jump="jumpToStep"
        @next="runStep"
        @undo="undo"
        @new-game="newGame"
        @toggle-drawer="drawerOpen = !drawerOpen"
    />

    <div class="relative flex min-h-0 flex-1">
        <EntityRail
            :omen="session.omen"
            :dread="session.dread"
            :omen-note="omenNote"
            :starting-omen="startingOmen"
            :starting-dread="startingDread"
            :scenario="scenario"
            :players="players"
            :draw-count="session.drawPile.length"
            :discard-count="session.discardPile.length"
            :reveal-count="session.revealCount"
            :discard-top-name="discardTop ? cardByKey[discardTop.key]?.name ?? '' : ''"
            :discard-top-arrow="discardTop ? outgoingArrow(discardTop) : null"
            :beat="currentBeat"
            :beat-count="beats.length"
            :beat-counter="currentBeat ? session.beatCounters[currentBeat.id] ?? 0 : 0"
            :story-complete="storyComplete"
            :kills="session.kills"
            :beat-dread="beatDread(currentBeat)"
            :aside-count="session.boardAside.length"
            :has-dealt="dealtCards.length > 0"
            @show-aside="viewingAside = true"
            @omen="adjustOmen"
            @dread="adjustDread"
            @draw-one="drawOne"
            @reveal="reveal"
            @reshuffle="reshuffleDiscardPile"
            @advance-beat="advanceBeat"
            @beat-counter="(delta) => adjustBeatCounter(currentBeat, delta)"
            @show-beats="openDrawer('beats')"
            @discard-card="discardCard"
            @peek="(count) => (peekCount = count)"
        />

        <main class="flex min-w-0 flex-1 flex-col gap-2 p-2">
            <StorylineRow
                style="flex: 24 1 0%"
                :line="session.line"
                :card-by-key="cardByKey"
                :last-reveal="session.lastReveal"
                :discard-top="discardTop"
                :incoming-arrow="incomingArrow"
                :incoming-choice="session.incomingChoice"
                :first-card-arrow-source="firstCardArrowSource"
                :default-arrow="defaultArrow"
                :outgoing-arrow="outgoingArrow"
                :shroud-first="session.shroudFirst"
                :board-shrouds="boardShrouds"
                @zoom="zoomLine"
                @toggle-resolved="toggleResolved"
                @toggle-flip="toggleFlip"
                @toggle-face-down="toggleFaceDown"
                @toggle-shroud-first="toggleShroudFirst"
                @turn-shrouded-up="turnShroudedUp"
                @discard="discardCard"
                @discard-line="discardLine"
                @set-incoming="setIncomingChoice"
            />

            <BoardTable
                style="flex: 27 1 0%"
                :board="session.board"
                :board-cards="boardCards"
                :kills="session.kills"
                :health-of="healthOf"
                :table="table"
            />

            <SeatDock
                ref="dock"
                v-model:active="activeSeat"
                style="flex: 49 1 0%"
                :party="session.party"
                :first-player="session.firstPlayer"
                :character-by-slug="characterBySlug"
                :deck-by-key="deckByKey"
                :full-health="fullHealth"
                :hand-size="handSizeOf"
                :gold-per-round="goldPerRoundOf"
                :party-style="partyStyle"
                :town="town"
                :districts="session.districts"
                :name-of="nameOf"
                :table="table"
                :actions="seatActions"
                :actions-step="currentStep.key === 'actions'"
                @open-players="openDrawer('players')"
            />
        </main>

        <SideDrawer
            v-if="drawerOpen"
            v-model:tab="drawerTab"
            :log="session.log"
            :beats="beats"
            :beat-index="session.beatIndex"
            :story-complete="storyComplete"
            :party="session.party"
            :first-player="session.firstPlayer"
            :decks="decks"
            :character-by-slug="characterBySlug"
            :deck-by-key="deckByKey"
            :party-style="partyStyle"
            :scenario="scenario"
            :available="available"
            :others="others"
            :chosen="chosen"
            :beat-dread="beatDread"
            :tale="tale"
            :fixed-modules="fixedModules"
            @close="drawerOpen = false"
            @toggle-deck="toggleDeck"
            @make-first="makeFirst"
            @toggle-module="toggleModule"
            @record="recordOpen = true"
        />
    </div>

    <DeckPeek
        v-if="peekCount"
        :draw-pile="session.drawPile"
        :card-by-key="cardByKey"
        :count="peekCount"
        @apply="applyPeek"
        @close="peekCount = 0"
        @zoom="(list, index) => zoom.open(list, index, 'entity')"
    />

    <!-- The dealt cards nobody holds; shuts itself once the last is given out. -->
    <PileViewer
        v-if="viewingAside && session.boardAside.length"
        title="Set aside · dealt cards"
        note="Nobody was given these. Right-click one to give it to a player or put it on the entity board."
        :items="session.boardAside"
        :card-of="boardCardOf"
        kind="board"
        :menu-for="asideMenu"
        :caption-for="(item) => boardCardOf(item).deal_group || 'no group'"
        @close="viewingAside = false"
        @zoom="(index) => zoom.open(session.boardAside.map(boardCardOf), index, 'board')"
    />

    <!-- The end of a scenario of a playthrough: what it leaves in the log. -->
    <TaleResults
        v-if="tale && recordOpen"
        :tale="tale"
        :party="recordParty"
        @close="recordOpen = false"
    />
    </template>

    <ContextMenu />
    <HoverPreview />

    <!-- What undo just took back -->
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-300 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="toast"
            :key="toast.id"
            role="status"
            class="pointer-events-none fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-full border border-white/15 bg-stone-900/95 px-4 py-1.5 text-sm text-stone-100 shadow-2xl"
        >
            {{ toast.text }}
        </div>
    </Transition>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :highlight="zoomEntry && zoomEntry.resolves !== 'single' ? zoomEntry.resolves : null"
        :caption="zoomCaption"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>
