import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

/** A card's height over its width, as CardPreview draws it (poker proportions). */
export const PORTRAIT = 88.9 / 63.5;
export const LANDSCAPE = 63.5 / 88.9;

/**
 * The card width that fits a region's height. Every row on the table is given
 * its height by the layout — a flex share of the screen — and scrolls sideways
 * for anything wider, so the height is the one number a card has to fit: take
 * off whatever the row puts above and below the card (`chrome`), divide by the
 * card's own proportions, and clamp. The region's height must not depend on
 * the cards inside it, or this would chase its own tail.
 */
export function useCardFit(el, { ratio = PORTRAIT, chrome = 0, min = 70, max = 260 } = {}) {
    const width = ref(min);
    let observer = null;

    const measure = () => {
        const height = el.value?.clientHeight ?? 0;
        if (!height) return;

        const chromeNow = typeof chrome === 'function' ? chrome() : chrome;
        width.value = Math.round(Math.min(max, Math.max(min, (height - chromeNow) / ratio)));
    };

    const observe = (node) => {
        observer?.disconnect();
        if (node) observer.observe(node);
        measure();
    };

    onMounted(() => {
        observer = new ResizeObserver(measure);
        observe(el.value);
    });

    // A region shown with v-if mounts its element after this does.
    watch(el, (node) => observer && observe(node));

    onBeforeUnmount(() => observer?.disconnect());

    return width;
}

/** A region's own width, kept up to date — what the hand fans its cards out across. */
export function useWidth(el) {
    const width = ref(0);
    let observer = null;

    const measure = () => (width.value = el.value?.clientWidth ?? 0);

    onMounted(() => {
        observer = new ResizeObserver(measure);
        if (el.value) observer.observe(el.value);
        measure();
    });

    watch(el, (node) => {
        observer?.disconnect();
        if (node) observer?.observe(node);
        measure();
    });

    onBeforeUnmount(() => observer?.disconnect());

    return width;
}
