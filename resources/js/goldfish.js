/**
 * The player's side of the playtest table: one seat's cards and the piles they
 * move between. Leaf module, no Vue, so the moves are plain functions on plain
 * objects — the table keeps them in `localStorage` and snapshots them for undo
 * as JSON, and nothing here may hold anything JSON would lose.
 *
 * Like the rest of the table this moves cards, it does not read them. A card's
 * own text — Fired, Tuck, Bottom draw — is the player's to carry out with the
 * free moves; what is automatic is only what the rules say every card does:
 * paying its gold, adding its omen, and where a played or bought card lands.
 */

/** The round, as `rules/02-turn-structure.md` writes it. */
export const STEPS = [
    { phase: 'player', key: 'reveal', label: 'Reveal' },
    { phase: 'player', key: 'actions', label: 'Actions' },
    { phase: 'player', key: 'draw', label: 'Ready and draw' },
    { phase: 'player', key: 'gold', label: 'Gold' },
    { phase: 'entity', key: 'upkeep', label: 'Board upkeep' },
    { phase: 'entity', key: 'resolve', label: 'Storyline resolves' },
    { phase: 'entity', key: 'end', label: 'End of round' },
];

/**
 * How many of a shop deck are face up, from `shopRowSize`. Unset or nonsense
 * shows the whole shop, which is what the table did before the row existed.
 */
export const shopRow = (setting) => {
    const size = Number(setting);
    return Number.isInteger(size) && size > 0 ? size : Infinity;
};

/**
 * `tucked` is every card lying under another, each naming its host in `under`.
 * It is a zone like the others rather than a list inside the host, so locate()
 * and moveCard() find a tucked card the same way they find any other — which is
 * what lets one be played "as if it were in your hand" with no special case.
 */
export const ZONES = ['hand', 'play', 'deck', 'discard', 'shop', 'aside', 'removed', 'tucked'];

export const ZONE_LABELS = {
    hand: 'Hand',
    play: 'In play',
    deck: 'Deck',
    discard: 'Discard',
    shop: 'Shop',
    aside: 'Upgrades set aside',
    removed: 'Out of the game',
    tucked: 'Tucked under a card',
};

export const shuffle = (list) => {
    const copy = [...list];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
};

const emptyZones = () => Object.fromEntries(ZONES.map((zone) => [zone, []]));

/**
 * A deck laid out for the first round. Each card starts where its own
 * `start_zone` says — the deck, the shop or in play — and upgrades are set
 * aside for the Smithy. Both decks are shuffled: the shop is a deck too, of
 * which only the top `shopRowSize` cards are face up to buy at a time.
 */
export function dealSeat(deck, cardsById, shuffleFn = shuffle) {
    const zones = emptyZones();
    let uid = 0;
    const instance = (id) => ({ uid: ++uid, id, exhausted: false, counter: cardsById[id]?.uses ?? 0 });

    for (const id of [...(deck?.deck ?? []), ...(deck?.kit ?? [])]) {
        const start = cardsById[id]?.start_zone;
        const zone = start === 'shop' ? 'shop' : start === 'play' ? 'play' : 'deck';
        zones[zone].push(instance(id));
    }
    for (const id of deck?.upgrades ?? []) zones.aside.push(instance(id));

    zones.deck = shuffleFn(zones.deck);
    zones.shop = shuffleFn(zones.shop);

    return { zones, nextUid: uid + 1 };
}

/** Where a card is in a seat, or null. */
export function locate(seat, uid) {
    for (const zone of ZONES) {
        const index = seat.zones[zone].findIndex((c) => c.uid === uid);
        if (index !== -1) return { zone, index, card: seat.zones[zone][index] };
    }
    return null;
}

/**
 * Take a card out of wherever it is and put it on top of (or under) a zone.
 * "Top" of a deck — the player's or the shop — is the front of the list: the
 * next card drawn, or the first of the shop's face-up row. Anything
 * a card leaves play for comes back ready, the way a real card is turned
 * upright when it is picked up.
 */
export function moveCard(seat, uid, to, end = 'top') {
    const found = locate(seat, uid);
    if (!found) return null;

    const [card] = seat.zones[found.zone].splice(found.index, 1);
    if (to !== 'play') card.exhausted = false;
    // Woken is a fact about this time in play: a Dormant card back in play is dormant again.
    if (to !== 'play') delete card.awake;
    if (to !== 'tucked') delete card.under;

    // A host leaving play leaves its tucked cards behind, in play. Where they
    // should really go is the card's text to say, and the table does not read it.
    if (found.zone === 'play' && to !== 'play') releaseTucked(seat, uid);

    if ((to === 'deck' || to === 'shop') && end === 'top') seat.zones[to].unshift(card);
    else seat.zones[to].push(card);

    return card;
}

/** The cards under a card, in the order they were tucked. */
export const tuckedUnder = (seat, hostUid) => (seat.zones.tucked ?? []).filter((c) => c.under === hostUid);

/**
 * Put a card under one in play. The table does not count against a "max 2
 * tucked" — that is card text — so it only refuses what cannot lie anywhere:
 * no host in play, or a card tucked under itself.
 */
export function tuckCard(seat, uid, hostUid) {
    if (uid === hostUid) return null;
    if (!seat.zones.play.some((c) => c.uid === hostUid)) return null;

    const card = moveCard(seat, uid, 'tucked');
    if (card) card.under = hostUid;

    return card;
}

/** Everything under a card comes out into play. Returns what came out. */
export function releaseTucked(seat, hostUid) {
    const freed = tuckedUnder(seat, hostUid);
    if (!freed.length) return [];

    seat.zones.tucked = seat.zones.tucked.filter((c) => c.under !== hostUid);
    for (const card of freed) {
        delete card.under;
        seat.zones.play.push(card);
    }

    return freed;
}

/**
 * A seat put right after loading: every zone present (a save from before a zone
 * existed has none), and a tucked card whose host is no longer in play — a card
 * deleted since — comes out into play rather than lying under nothing, unseen.
 */
export function normaliseSeat(seat) {
    for (const zone of ZONES) seat.zones[zone] ??= [];

    const hosts = new Set(seat.zones.play.map((c) => c.uid));
    const orphans = seat.zones.tucked.filter((c) => !hosts.has(c.under));
    seat.zones.tucked = seat.zones.tucked.filter((c) => hosts.has(c.under));
    for (const card of orphans) {
        delete card.under;
        seat.zones.play.push(card);
    }

    return seat;
}

/**
 * Draw cards into the hand, top or bottom of the deck. An empty deck is
 * reshuffled from the discard only when the tunable rule says so
 * (`playerDeckOutReshuffle`); `onDeckOut` is told each time, so the table can
 * add the omen `playerDeckOutOmen` charges and say that it did.
 */
export function drawCards(seat, count, { reshuffle = false, from = 'top', onDeckOut = () => {}, shuffleFn = shuffle } = {}) {
    const drawn = [];

    for (let n = 0; n < count; n++) {
        if (seat.zones.deck.length === 0) {
            if (!reshuffle || seat.zones.discard.length === 0) break;

            seat.zones.deck = shuffleFn(seat.zones.discard.map((c) => ({ ...c, exhausted: false })));
            seat.zones.discard = [];
            onDeckOut();
        }

        const card = from === 'bottom' ? seat.zones.deck.pop() : seat.zones.deck.shift();
        seat.zones.hand.push(card);
        drawn.push(card);
    }

    return drawn;
}

/**
 * The Gold step, as `rules/02-turn-structure.md` writes it: each player
 * "generates the gold on their character card up to their remaining max". So it
 * tops a player up to that number rather than adding to what is left — unspent
 * gold does not build up, which is what "gold does not carry over" keeps — and
 * the end of the round takes nothing away, or the gold made here could never be
 * spent in the next round's Actions. With `goldCarriesOver` on it adds instead.
 */
export const goldAfterGoldStep = (current, perRound, carriesOver = false) => {
    const have = Math.max(0, Number(current) || 0);
    const make = Math.max(0, Number(perRound) || 0);

    return carriesOver ? have + make : Math.max(have, make);
};

/**
 * Where a card lands once it is played: an item or a Hireling stays out in
 * front of its player, everything else is spent and goes to the discard.
 */
export const playedZone = (card) => (card?.type === 'item' || card?.type === 'hireling' ? 'play' : 'discard');

/**
 * Where a bought card goes, from `shopPurchaseDestination`. The designer has
 * decided on the top of the deck; this still only reads the tunable number,
 * and anything it does not recognise falls back to that.
 */
export const purchaseDestination = (setting) => {
    const written = String(setting ?? '').toLowerCase();

    if (written.includes('hand')) return { zone: 'hand', end: 'top' };
    if (written.includes('discard')) return { zone: 'discard', end: 'top' };
    if (written.includes('bottom')) return { zone: 'deck', end: 'bottom' };

    return { zone: 'deck', end: 'top' };
};

/**
 * The upgrade waiting for this card, if it is set aside: the card names its
 * upgrade by slug, the same link the Smithy reads on the character page.
 */
export function upgradeFor(seat, uid, cardsById) {
    const found = locate(seat, uid);
    const slug = found ? cardsById[found.card.id]?.upgrades_to : null;
    if (!slug || found.zone === 'aside') return null;

    return seat.zones.aside.find((c) => cardsById[c.id]?.slug === slug) ?? null;
}

/** Swap a card for its upgrade in place; the old card is set aside. */
export function upgradeCard(seat, uid, cardsById) {
    const upgrade = upgradeFor(seat, uid, cardsById);
    if (!upgrade) return null;

    const found = locate(seat, uid);
    const asideIndex = seat.zones.aside.findIndex((c) => c.uid === upgrade.uid);
    seat.zones.aside.splice(asideIndex, 1);

    const old = seat.zones[found.zone][found.index];
    // The upgrade lies exactly as the card did — turned, and woken, or not.
    seat.zones[found.zone].splice(found.index, 1, { ...upgrade, exhausted: old.exhausted, awake: old.awake });
    const { awake, ...setAside } = old;
    seat.zones.aside.push({ ...setAside, exhausted: false });

    // The upgrade takes the card's place on the table, so it takes what was
    // tucked under it too: a Revolver's Bullets stay put when it becomes the
    // Peacemaker.
    for (const card of seat.zones.tucked ?? []) {
        if (card.under === old.uid) card.under = upgrade.uid;
    }

    return upgrade;
}

/**
 * A board card's health at this table, or null when it has none the table can
 * count. Board health is free text — `2`, `4{perPlayer}`, `12 per player` — so
 * anything the equation reader cannot make a number of is shown as written
 * and tracked as damage only.
 */
export function boardHealth(health, players, evaluate) {
    const written = String(health ?? '').trim();
    if (written === '') return null;
    if (/^\d+$/.test(written)) return Number(written);

    const equation = written.replace(/[{}]/g, '').replace(/\bper\s+player\b/gi, 'perPlayer');
    const value = evaluate(equation, Math.max(1, players));

    return Number.isFinite(value) ? value : null;
}
