<script setup>
import { computed } from 'vue';
import CardPreview from '../CardPreview.vue';
import { hover } from './tableOverlays';
import { LANDSCAPE, PORTRAIT } from './useCardFit';

/**
 * The card under the pointer at reading size, beside it rather than over it,
 * so a row of small cards can be read without opening each one. Drawn once in
 * Play.vue and fed by `showHover()`; it never takes the pointer, so moving off
 * the card it shows closes it. A card already drawn about this big is left
 * alone, since there is nothing more to read.
 */
const GAP = 12;

const size = computed(() => {
    const landscape = hover.kind === 'entity';
    const width = landscape ? 440 : 300;
    return { width, height: Math.round(width * (landscape ? LANDSCAPE : PORTRAIT)) };
});

const show = computed(() => hover.visible && hover.card && hover.rect && hover.rect.width < size.value.width * 0.8);

const position = computed(() => {
    const rect = hover.rect;
    const { width, height } = size.value;
    if (!rect) return {};

    const right = rect.right + GAP;
    const leftSide = rect.left - GAP - width;
    let left = right + width <= window.innerWidth - 6 ? right : leftSide;
    // Neither side has room: centre it over the card, inside the window.
    if (left < 6) left = Math.max(6, Math.min(rect.left + rect.width / 2 - width / 2, window.innerWidth - width - 6));

    const top = Math.max(6, Math.min(rect.top + rect.height / 2 - height / 2, window.innerHeight - height - 6));

    return { left: `${left}px`, top: `${top}px` };
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-75 ease-in"
            leave-to-class="opacity-0"
        >
            <div
                v-if="show"
                class="pointer-events-none fixed z-[45] rounded-xl text-stone-900 shadow-[0_12px_40px_rgba(0,0,0,0.6)]"
                :style="position"
                aria-hidden="true"
            >
                <CardPreview :card="hover.card" :kind="hover.kind" :width="size.width" :highlight="hover.highlight" />
            </div>
        </Transition>
    </Teleport>
</template>
