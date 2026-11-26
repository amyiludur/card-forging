<script setup>
import { onBeforeUnmount, onMounted, watch } from 'vue';
import TableCard from './TableCard.vue';
import { dragging } from './useTableDrag';

/**
 * One pile opened up: every card in it as its real face, in pile order — a
 * deck top first. Clicking a card shows it full size; right-click has the free
 * moves out of the pile (`menuFor`), and a card can be dragged out (`dragFor`)
 * onto the table behind. While one is in the air the viewer fades out of the
 * way so the piles under it can take the drop, and it closes once the card
 * has landed.
 */
const props = defineProps({
    title: { type: String, required: true },
    note: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    cardOf: { type: Function, required: true },
    kind: { type: String, default: 'player' },
    width: { type: Number, default: 150 },
    menuFor: { type: Function, default: null },
    dragFor: { type: Function, default: null },
    // A line under each card — which deal group a set-aside board card is in.
    captionFor: { type: Function, default: null },
});

const emit = defineEmits(['close', 'zoom']);

const onKeydown = (event) => {
    // The card zoom sits over the viewer and closes on Escape itself.
    if (event.key === 'Escape' && !document.querySelector('[aria-label="Card at full size"]')) emit('close');
};

// A drag that started in here has ended — landed or not — so the viewer goes.
watch(() => dragging.payload, (now, before) => {
    if (before && !now && before.fromViewer) emit('close');
});

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-40 flex items-center justify-center bg-black/60 p-6 transition-opacity duration-150"
            :class="dragging.payload?.fromViewer ? 'pointer-events-none opacity-0' : ''"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
            @click.self="emit('close')"
        >
            <div class="table-felt flex max-h-full w-full max-w-6xl flex-col overflow-hidden rounded-xl border border-white/15 shadow-2xl">
                <div class="flex items-center gap-3 border-b border-white/10 px-4 py-2">
                    <h2 class="font-serif text-lg font-semibold text-stone-50">{{ title }}</h2>
                    <span class="text-xs tabular-nums text-stone-400">{{ items.length }} {{ items.length === 1 ? 'card' : 'cards' }}</span>
                    <span v-if="note" class="text-xs text-stone-400">· {{ note }}</span>
                    <span class="text-xs text-stone-500">· right-click a card to move it, or drag it out</span>
                    <button type="button" class="table-btn ml-auto" @click="emit('close')">Close</button>
                </div>
                <div class="table-scroll min-h-0 flex-1 p-4">
                    <div v-if="items.length" class="flex flex-wrap gap-4">
                        <div v-for="(item, index) in items" :key="item.uid ?? index" class="flex flex-col items-center gap-1">
                            <TableCard
                                :card="cardOf(item)"
                                :kind="kind"
                                :width="width"
                                cursor="zoom-in"
                                :activate="() => emit('zoom', index)"
                                :menu="menuFor ? () => menuFor(item, index) : null"
                                :drag="dragFor ? { ...dragFor(item, index), fromViewer: true } : null"
                            />
                            <span v-if="captionFor" class="text-xs text-stone-300">{{ captionFor(item, index) }}</span>
                        </div>
                    </div>
                    <p v-else class="py-10 text-center text-sm text-stone-400">Empty.</p>
                </div>
            </div>
        </div>
    </Teleport>
</template>
