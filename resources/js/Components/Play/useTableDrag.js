import { reactive, ref } from 'vue';
import { hideHover, suppressHover } from './tableOverlays';

/**
 * Dragging a card about the table, with the browser's own drag and drop and
 * nothing added. A card says what it is when it is picked up (`startDrag`), a
 * pile or a row says what it will take (`useDropZone`), and the drop runs the
 * same function a click or a menu item would — so a drag is only ever another
 * way of asking for a move that already exists, and it goes through `act()`
 * like every other.
 *
 * A payload is `{ source, zone?, uid?, index? }`: `source` is `seat` for one
 * of the active seat's cards (with the zone it is in), `line` for a card on
 * the storyline (with its index).
 */
export const dragging = reactive({ payload: null });

export function startDrag(event, payload) {
    dragging.payload = payload;
    suppressHover(true);
    hideHover();

    event.dataTransfer.effectAllowed = 'move';
    // Firefox will not start a drag that carries no data.
    event.dataTransfer.setData('text/plain', payload.uid != null ? String(payload.uid) : String(payload.index ?? ''));
}

export function endDrag() {
    dragging.payload = null;
    suppressHover(false);
}

/**
 * A place a card can be dropped. `accepts(payload)` says whether this card may
 * land here; `onDrop(payload, { under })` does the move, where `under` is
 * Shift held at the drop — the bottom of a deck rather than its top.
 */
export function useDropZone(accepts, onDrop) {
    const over = ref(false);
    let depth = 0;

    const wants = () => dragging.payload && accepts(dragging.payload);

    const listeners = {
        dragenter: (event) => {
            if (!wants()) return;
            event.preventDefault();
            depth += 1;
            over.value = true;
        },
        dragover: (event) => {
            if (!wants()) return;
            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
        },
        dragleave: () => {
            depth = Math.max(0, depth - 1);
            if (depth === 0) over.value = false;
        },
        drop: (event) => {
            depth = 0;
            over.value = false;
            // A zone inside this one took the drop already — a card dropped on
            // a card in play is tucked under it, not played into the row it
            // sits in — and the event is still bubbling up through this one.
            if (event.defaultPrevented) return;
            if (!wants()) return;
            event.preventDefault();
            const payload = dragging.payload;
            endDrag();
            onDrop(payload, { under: event.shiftKey });
        },
    };

    /** Whether a card in the air could land here, to light the zone up. */
    const ready = () => !!wants();

    return { over, ready, listeners };
}
