/**
 * Board cards dealt to the players at setup — the Meddler in the Dark's
 * townsfolk: every card whose `start` is `dealt`, each copy of its qty, is
 * shuffled together and one goes to each seat in lineup order. Whatever is
 * left is kept aside, face down, for the table to pull from by hand.
 *
 * Leaf module, no Vue, so it can be checked on its own. More seats than cards
 * deals what there is: nothing is invented and nothing used twice, and the
 * table says which seats went without — report, don't correct.
 */
import { shuffle } from './goldfish.js';

export function dealToSeats(cards, seatCount, shuffleFn = shuffle) {
    // A qty of 0 puts no copy in the deal, the same as on the board.
    const pile = shuffleFn(cards.flatMap((card) => Array.from({ length: Math.max(0, card.qty ?? 1) }, () => card.id)));
    const seats = Array.from({ length: seatCount }, (_, i) => (i < pile.length ? [pile[i]] : []));

    return { seats, aside: pile.slice(seatCount) };
}

/**
 * Each deal group — Townsfolk, Curses — dealt on its own, so a seat gets one
 * of each. A card with no group is in the unnamed group `''`, which is how
 * every dealt card was dealt before groups had names. Groups come in the order
 * their first card does.
 */
export function dealByGroup(cards, seatCount, shuffleFn = shuffle) {
    const groups = new Map();
    for (const card of cards) {
        const name = card.deal_group ?? '';
        if (!groups.has(name)) groups.set(name, []);
        groups.get(name).push(card);
    }

    return [...groups].map(([name, members]) => ({ name, ...dealToSeats(members, seatCount, shuffleFn) }));
}
