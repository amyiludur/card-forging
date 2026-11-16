import { drawCards, locate, moveCard, playedZone, purchaseDestination, shuffle, tuckCard, upgradeCard, upgradeFor } from '../../goldfish';

/**
 * Every move a seat can make, as plain functions over the seat they are given.
 * A click, a right-click menu and a drag all end up here, so the three can
 * never disagree about what "play" or "buy" does — and each goes through the
 * table's `act()`, prefixed with the character's name, so undo and the log see
 * every one of them.
 *
 * `seat`, `name` and `handSize` are refs (or computeds): the dock lays out
 * whichever seat is active, and the functions read it at the moment they run.
 */

/** Every free move a card can make, with the words the log and the menu use. */
export const MOVES = [
    ['hand', 'top', 'to hand'],
    ['play', 'top', 'into play'],
    ['discard', 'top', 'to discard'],
    ['deck', 'top', 'on top of deck'],
    ['deck', 'bottom', 'under deck'],
    ['shop', 'top', 'on top of shop deck'],
    ['shop', 'bottom', 'under shop deck'],
    ['removed', 'top', 'out of the game'],
];

export const moveLabel = (to, end = 'top') => MOVES.find(([z, e]) => z === to && e === end)?.[2] ?? to;

// Both decks have a top and a bottom, so a card can move within its own.
export const moveOptions = (zone) => MOVES.filter(([to]) => to !== zone || to === 'deck' || to === 'shop');

export function useSeatActions({ seat, name, handSize, table }) {
    const cards = () => table.cardsById ?? {};
    const cardOf = (instance) => cards()[instance?.id] ?? {};

    const act = (label, fn) => table.act(`${name.value}: ${label}`, fn);

    const canAfford = (cost) => (seat.value?.gold ?? 0) >= Number(cost ?? 0);

    const move = (instance, to, end = 'top') =>
        act(`${cardOf(instance).name} ${moveLabel(to, end)}`, () => moveCard(seat.value, instance.uid, to, end));

    /** Pays its gold, adds its omen, and lands where every played card lands. False when it cannot be paid for. */
    const play = (instance) => {
        const card = cardOf(instance);
        const cost = Number(card.gold_cost ?? 0);
        const omen = Number(card.omen_icons ?? 0);
        if (!canAfford(cost)) return false;

        act(`played ${card.name}${cost ? ` for ${cost} gold` : ''}${omen ? `, +${omen} omen` : ''}`, () => {
            seat.value.gold -= cost;
            if (omen) table.addOmen(omen);
            moveCard(seat.value, instance.uid, playedZone(card), 'top');
        });

        return true;
    };

    const destination = () => purchaseDestination(table.config?.shopPurchaseDestination);
    const destinationLabel = () => moveLabel(destination().zone, destination().end);

    const buy = (instance) => {
        const card = cardOf(instance);
        const cost = Number(card.shop_cost ?? 0);
        if (!canAfford(cost)) return false;

        const { zone, end } = destination();
        act(`bought ${card.name} for ${cost} gold, ${destinationLabel()}`, () => {
            seat.value.gold -= cost;
            moveCard(seat.value, instance.uid, zone, end);
        });

        return true;
    };

    const draw = (count, from = 'top') => {
        const reshuffle = !!table.config?.playerDeckOutReshuffle;
        const omen = Number(table.config?.playerDeckOutOmen ?? 0);
        const who = name.value;

        act(from === 'bottom' ? 'drew from the bottom' : `drew ${count}`, () => {
            drawCards(seat.value, count, {
                reshuffle,
                from,
                onDeckOut: () => {
                    if (omen) table.addOmen(omen);
                    table.note(`${who}'s deck ran out: discard shuffled back in${omen ? `, +${omen} omen` : ''}.`);
                },
            });
        });
    };

    const drawUp = () => draw(Math.max(0, handSize.value - seat.value.zones.hand.length));

    const upgradeOf = (instance) => (seat.value ? upgradeFor(seat.value, instance.uid, cards()) : null);

    const upgrade = (instance) => {
        const to = upgradeOf(instance);
        if (!to) return;
        act(`upgraded ${cardOf(instance).name} to ${cardOf(to).name}`, () => upgradeCard(seat.value, instance.uid, cards()));
    };

    const toggleExhausted = (instance) =>
        act(`${instance.exhausted ? 'readied' : 'exhausted'} ${cardOf(instance).name}`, () => {
            const found = locate(seat.value, instance.uid);
            if (found) found.card.exhausted = !found.card.exhausted;
        });

    const bump = (instance, delta) =>
        act(`${cardOf(instance).name} counter ${delta > 0 ? '+' : ''}${delta}`, () => {
            const found = locate(seat.value, instance.uid);
            if (found) found.card.counter = Math.max(0, (found.card.counter ?? 0) + delta);
        });

    const shuffleDeck = () => act('shuffled the deck', () => (seat.value.zones.deck = shuffle(seat.value.zones.deck)));
    const shuffleShop = () => act('shuffled the shop deck', () => (seat.value.zones.shop = shuffle(seat.value.zones.shop)));
    const readyAll = () => act('readied everything', () => seat.value.zones.play.forEach((c) => (c.exhausted = false)));

    /**
     * Put a card under one in play — the Revolver's Bullets. A free move like
     * the others: how many a card may hold is its own text, so nothing is
     * counted against it here.
     */
    const tuck = (instance, host) =>
        act(`tucked ${cardOf(instance).name} under ${cardOf(host).name}`, () => tuckCard(seat.value, instance.uid, host.uid));

    /** Every card in play this one could go under: not itself, not where it already is. */
    const tuckHosts = (instance) =>
        (seat.value?.zones.play ?? []).filter((host) => host.uid !== instance.uid && host.uid !== instance.under);

    const tuckItems = (instance) =>
        tuckHosts(instance).map((host) => ({ label: `Tuck under ${cardOf(host).name}`, action: () => tuck(instance, host) }));

    /** The free moves out of a zone, as menu items: the piles, then under a card in play. */
    const moveItems = (instance, zone) => [
        ...moveOptions(zone).map(([to, end, label]) => ({ label: `Move ${label}`, action: () => move(instance, to, end) })),
        ...tuckItems(instance),
    ];

    return {
        cardOf,
        act,
        canAfford,
        move,
        play,
        buy,
        destination,
        destinationLabel,
        draw,
        drawUp,
        upgradeOf,
        upgrade,
        toggleExhausted,
        bump,
        shuffleDeck,
        shuffleShop,
        readyAll,
        tuck,
        tuckHosts,
        moveItems,
    };
}
