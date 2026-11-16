import { onBeforeUnmount, onMounted } from 'vue';

/**
 * The playtest table's keyboard: Space moves the round on, Ctrl+Z undoes, and
 * a handful of single keys reach the rest. Nothing fires while typing in a
 * field, and nothing fires while `blocked()` says something else owns the
 * keyboard — the right-click menu, the full-size card.
 *
 * `keys` maps a key (as `event.key`, lower-cased) to a handler; `mod+z` is
 * Ctrl or Cmd with Z, and `mod+shift+z` the same with Shift.
 */
export const HOTKEYS = [
    ['Space', 'do the next step of the round'],
    ['Ctrl+Z', 'undo'],
    ['D', 'draw a card (the seat in front of you)'],
    ['1 – 4', 'switch seat'],
    ['S', 'open or close the shop'],
    ['T', 'open or close the town'],
    ['L', 'open or close the log'],
    ['Esc', 'close whatever is open'],
    ['?', 'this list'],
];

const typing = (target) =>
    target instanceof HTMLElement &&
    (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

export function useHotkeys(keys, { blocked = () => false } = {}) {
    const onKeydown = (event) => {
        if (event.defaultPrevented || typing(event.target) || blocked()) return;

        const mod = event.ctrlKey || event.metaKey;
        let key = event.key === ' ' ? 'space' : event.key.toLowerCase();
        if (mod) key = `mod+${event.shiftKey ? 'shift+' : ''}${key}`;
        else if (event.altKey) return;

        const handler = keys[key];
        if (!handler) return;

        // Space would otherwise press whichever button has focus as well.
        event.preventDefault();
        handler(event);
    };

    onMounted(() => window.addEventListener('keydown', onKeydown));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
}
