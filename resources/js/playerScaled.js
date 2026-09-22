// The browser half of App\Support\PlayerScaled. Same grammar, same answers, so
// the editor's "at 3 players" preview, the playtest table's dials and the
// printed card can never disagree about what an equation says.
//
// A scalable number is either a plain number, exactly as it always was, or an
// equation naming the player count:
//
//     2                 a plain number, unchanged
//     1 + 1perPlayer    the Kraken's starting Dread
//     2 * 1perPlayer    twice a player each
//     3 + 2(perPlayer)  parentheses and implicit multiplication both work
//
// There is no division: a number of players does not divide into halves, and a
// rounding rule would be a decision about the game rather than about the tool.

/** What the player count is called inside an equation. */
export const IDENTIFIER = 'perPlayer';

/** The same, as the markup token that draws the icon. */
export const TOKEN = '{perPlayer}';

// Deliberately not \b-anchored on the left: the designer writes 1perPlayer, and
// a digit and a letter are both word characters, so \b would not find it there.
// Case-insensitive, so perplayer reads the same — the stored equation keeps
// whatever was typed and only what is drawn is made canonical.
const MENTIONS = new RegExp(`(?<![A-Za-z_])${IDENTIFIER}(?![A-Za-z0-9_])`, 'i');

/** True when a piece of text names the player count. */
export const mentionsPerPlayer = (text) => MENTIONS.test(String(text ?? ''));

/** True once an equation is written that actually counts the players. */
export const isScaled = (equation) => {
    const written = String(equation ?? '').trim();

    return written !== '' && mentionsPerPlayer(written);
};

class EquationError extends Error {}

const lex = (equation) => {
    // The token form is accepted as well as the bare word, so an equation
    // copied out of card text still reads.
    const text = String(equation ?? '').replace(/[{}]/g, '');
    const pattern = new RegExp(`\\s*(\\d+|${IDENTIFIER}|[-+*()])|\\s*(\\S)`, 'gi');
    const tokens = [];

    for (const match of text.matchAll(pattern)) {
        if (match[2]) throw new EquationError(`'${match[2]}' is not something an equation can use.`);
        // perplayer and perPlayer are the same word; the canonical spelling is
        // what the parser below compares against.
        tokens.push(match[1].toLowerCase() === IDENTIFIER.toLowerCase() ? IDENTIFIER : match[1]);
    }

    if (tokens.length === 0) throw new EquationError('The equation is empty.');

    return tokens;
};

const isDigits = (token) => /^\d+$/.test(token);

/**
 * Integers, the player count, + - * and parentheses, with a number next to a
 * bracket or the player count meaning multiplication. A recursive descent
 * parser rather than eval(), because this runs on text the designer types.
 */
const parse = (tokens, players) => {
    let at = 0;

    const term = () => {
        const token = tokens[at];

        if (token === undefined) throw new EquationError('The equation stops before it says what to do.');

        if (token === '-') {
            at++;
            return -term();
        }

        if (token === '+') {
            at++;
            return term();
        }

        if (token === '(') {
            at++;
            const value = sum();
            if (tokens[at] !== ')') throw new EquationError('A bracket is left open.');
            at++;
            return value;
        }

        if (token === IDENTIFIER) {
            at++;
            return players;
        }

        if (isDigits(token)) {
            at++;
            return Number(token);
        }

        throw new EquationError(`Unexpected '${token}'.`);
    };

    const product = () => {
        let value = term();

        for (;;) {
            if (tokens[at] === '*') {
                at++;
                value *= term();
                continue;
            }

            // 1perPlayer and 2(1 + perPlayer): a term straight after a term is
            // a multiplication, which is how the designer writes these.
            const next = tokens[at];

            if (next !== undefined && (next === '(' || next === IDENTIFIER || isDigits(next))) {
                value *= term();
                continue;
            }

            return value;
        }
    };

    const sum = () => {
        let value = product();

        while (tokens[at] === '+' || tokens[at] === '-') {
            const operator = tokens[at++];
            const right = product();
            value = operator === '+' ? value + right : value - right;
        }

        return value;
    };

    const value = sum();

    if (at < tokens.length) throw new EquationError(`Unexpected '${tokens[at]}'.`);

    return value;
};

/** The equation at a table of this many players, or null when it cannot be read. */
export const evaluateEquation = (equation, players) => {
    try {
        return parse(lex(equation), players);
    } catch (error) {
        if (error instanceof EquationError) return null;
        throw error;
    }
};

/**
 * Why an equation cannot be read, or null when it can. Checked at one player:
 * a table of one is as good as any for finding out whether the thing parses.
 */
export const equationError = (equation) => {
    if (String(equation ?? '').trim() === '') return null;

    try {
        parse(lex(equation), 1);
        return null;
    } catch (error) {
        if (error instanceof EquationError) return error.message;
        throw error;
    }
};

/**
 * What a value is worth at a table of this many players, or null when there is
 * no answer: an equation that cannot be read, or no player count to count.
 */
export const scaledAt = (number, equation, players) => {
    if (String(equation ?? '').trim() === '') {
        return Number.isFinite(Number(number)) && number !== null && number !== '' ? Number(number) : null;
    }

    return players === null || players === undefined ? null : evaluateEquation(equation, players);
};

/**
 * A value as card text: the equation with the player count written as the
 * {perPlayer} token, so it renders through the markup like any other icon and
 * the preview and the print sheet draw the same thing. Mirrors
 * PlayerScaled::markup().
 */
export const scaledMarkup = (number, equation) => {
    const written = String(equation ?? '').trim();

    if (written === '') return number === null || number === undefined ? '' : String(number);

    return written.replace(new RegExp(`\\{?(?<![A-Za-z_])${IDENTIFIER}(?![A-Za-z0-9_])\\}?`, 'gi'), TOKEN);
};
