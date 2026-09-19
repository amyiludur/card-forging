import { computed, reactive, ref } from 'vue';

/**
 * State for one card zoom overlay on a page.
 *
 * A page can hold several lists of cards (a starting deck and the cards a beat
 * adds later, say). open() takes the list the clicked card belongs to, so the
 * arrow keys walk that list and stop at its ends rather than jumping between
 * sections.
 */
export function useCardZoom(defaultKind = 'entity') {
    const cards = ref([]);
    const index = ref(-1);
    const kind = ref(defaultKind);

    const card = computed(() => (index.value < 0 ? null : cards.value[index.value] ?? null));
    const total = computed(() => cards.value.length);
    const position = computed(() => index.value + 1);

    const open = (list, at = 0, cardKind = defaultKind) => {
        cards.value = Array.isArray(list) ? list : [list];
        index.value = Math.min(Math.max(at, 0), cards.value.length - 1);
        kind.value = cardKind;
    };

    const close = () => {
        index.value = -1;
        cards.value = [];
    };

    // Walks the list without wrapping: the ends are where the section ends.
    const step = (delta) => {
        if (index.value < 0) {
            return;
        }

        index.value = Math.min(Math.max(index.value + delta, 0), cards.value.length - 1);
    };

    // reactive() so templates can read zoom.card without .value.
    return reactive({ card, kind, total, position, open, close, step });
}
