import { reactive } from 'vue';

/**
 * The two things that float over the playtest table: the right-click menu and
 * the card shown at reading size while the pointer rests on one. There is only
 * ever one table on a page, so each is a single shared state, drawn once by
 * `ContextMenu.vue` and `HoverPreview.vue` in `Play.vue`, and opened from
 * whichever card asked. Neither is part of the game, so neither is in the
 * session and undo never touches them.
 */

/**
 * The menu. An item is `{ label, action, hint?, disabled?, danger? }`, or
 * `{ divider: true }`. Picking one runs its action and closes the menu.
 */
export const menu = reactive({ open: false, x: 0, y: 0, title: '', items: [] });

export function openMenu(event, title, items) {
    event.preventDefault();
    hideHover();

    // A menu opened from the keyboard has no pointer position, so it opens
    // at the element instead.
    let { clientX: x, clientY: y } = event;
    if (!x && !y && event.currentTarget?.getBoundingClientRect) {
        const rect = event.currentTarget.getBoundingClientRect();
        x = rect.left + rect.width / 2;
        y = rect.top + rect.height / 2;
    }

    Object.assign(menu, { open: true, x, y, title, items: items.filter(Boolean) });
}

export function closeMenu() {
    menu.open = false;
}

/** The hover preview: which card, and the box of the card it is beside. */
export const hover = reactive({ visible: false, card: null, kind: 'player', highlight: null, rect: null });

const HOVER_DELAY = 420;
let timer = null;
// Set while a card is being dragged, so a card passed over is not previewed.
let suppressed = false;

export function showHover(el, { card, kind, highlight = null }) {
    clearTimeout(timer);
    if (suppressed || menu.open || !card) return;

    timer = setTimeout(() => {
        if (suppressed || menu.open || !el.isConnected) return;
        Object.assign(hover, { visible: true, card, kind, highlight, rect: el.getBoundingClientRect() });
    }, HOVER_DELAY);
}

export function hideHover() {
    clearTimeout(timer);
    hover.visible = false;
}

export function suppressHover(on) {
    suppressed = on;
    if (on) hideHover();
}
