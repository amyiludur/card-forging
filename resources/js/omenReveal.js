/**
 * The reveal step, as `rules/01-core-rules.md` and `rules/02-turn-structure.md`
 * write it:
 *
 *   1. Reveal cards from the top of the entity deck until their total omen cost
 *      meets or exceeds the omen pool.
 *   2. Empty the omen pool.
 *
 * Three things ride along with that:
 *
 * - **Empowered.** If the total cost overshoots the pool, the last card revealed
 *   gains +1 effect strength for each point of excess (`empoweredPerPointOfExcess`).
 * - **X-cost cards.** The card's cost is whatever omen is still unmatched, so it
 *   always ends the reveal and never overshoots.
 * - **Dread.** Fewer than X cards revealed triggers the scenario's Dread effect,
 *   where X is the Dread dial.
 *
 * What this does **not** do is apply any of it. The effect a Dread check triggers
 * is the designer's placeholder sentence, and what an empty deck does is open
 * question 10, so both are reported and left alone — the same way the deck maths
 * report a deck of 21 rather than trimming it.
 *
 * Open question 12 is why `dreadTriggered` is false when an X-cost card ended the
 * reveal: the decisions file records that as what happens *currently*, not as
 * something settled.
 */

/**
 * @param  {Array<{omen_cost: ?number, omen_is_x: boolean}>} cards  the draw pile, top first
 * @param  {{pool: number, dreadX: number, empoweredPerPoint: number}} options
 */
export function resolveReveal(cards, { pool, dreadX, empoweredPerPoint = 1 }) {
    const taken = [];
    let total = 0;
    let endedOnX = false;

    for (const card of cards) {
        // A pool of 0 takes no cards at all, which is exactly what the Dread
        // check below is for.
        if (total >= pool) break;

        if (card.omen_is_x) {
            const cost = Math.max(0, pool - total);
            taken.push({ card, cost, isX: true });
            total += cost;
            endedOnX = true;
            break;
        }

        const cost = Math.max(0, Number(card.omen_cost ?? 0));
        taken.push({ card, cost, isX: false });
        total += cost;
    }

    const ranOut = total < pool;
    const excess = Math.max(0, total - pool);

    return {
        taken,
        total,
        pool,
        excess,
        empowered: excess * empoweredPerPoint,
        endedOnX,
        // The pile emptied before the pool was matched. What happens next is the
        // table's call, not this function's.
        ranOut,
        unmatched: ranOut ? pool - total : 0,
        dreadTriggered: !endedOnX && taken.length < dreadX,
    };
}
