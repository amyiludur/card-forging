<script setup>
/**
 * A pile on the table drawn as a stack of card backs with its count on it —
 * the deck, a discard, the shop. It shows how many, never which: a pile is
 * opened in the PileViewer to see its faces. A pile can also be somewhere to
 * drop a card: `ready` lights it while a card that could land here is in the
 * air, and `active` while one is over it. The drop listeners themselves fall
 * through onto the root from whoever lays the pile out.
 */
defineProps({
    label: { type: String, required: true },
    count: { type: Number, default: 0 },
    // A line under the label: the top card's name, say.
    sub: { type: String, default: '' },
    tone: { type: String, default: 'player' },
    clickable: { type: Boolean, default: true },
    active: { type: Boolean, default: false },
    ready: { type: Boolean, default: false },
    width: { type: Number, default: 54 },
    title: { type: String, default: '' },
});

const emit = defineEmits(['open']);

const backs = { player: '#1e3a5f', entity: '#3b1d1d', shop: '#5b4220', aside: '#3f3f46', removed: '#27272a' };
</script>

<template>
    <component
        :is="clickable ? 'button' : 'div'"
        :type="clickable ? 'button' : null"
        class="group flex shrink-0 flex-col items-center gap-1 rounded-md p-0.5 text-center transition"
        :class="[clickable ? 'cursor-pointer' : '', active ? 'bg-amber-400/20' : ready ? 'bg-white/5' : '']"
        :title="title || (clickable ? `Look through ${label.toLowerCase()}` : label)"
        @click="clickable && emit('open')"
    >
        <span class="relative block" :style="{ width: `${width}px`, height: `${Math.round(width * 1.4)}px` }">
            <span
                v-for="layer in Math.min(3, Math.max(count, 1))"
                :key="layer"
                class="absolute inset-0 rounded-md border"
                :class="[
                    count ? 'border-white/30' : 'border-dashed border-white/20',
                    active ? 'ring-2 ring-amber-400' : ready ? 'ring-1 ring-amber-300/50' : '',
                    clickable ? 'transition group-hover:-translate-y-0.5' : '',
                ]"
                :style="{
                    background: count ? backs[tone] ?? backs.player : 'transparent',
                    transform: `translate(${(layer - 1) * 2}px, ${(layer - 1) * -2}px)`,
                    boxShadow: count ? '0 1px 2px rgba(0,0,0,0.4)' : 'none',
                }"
            />
            <span class="absolute inset-0 flex items-center justify-center font-serif text-lg font-bold tabular-nums text-stone-50" :class="count ? '' : 'text-stone-500'">
                {{ count }}
            </span>
        </span>
        <span class="table-label block max-w-[5.5rem] truncate leading-tight">{{ label }}</span>
        <span v-if="sub" class="block max-w-[5.5rem] truncate text-[10px] leading-tight text-stone-300">{{ sub }}</span>
    </component>
</template>
