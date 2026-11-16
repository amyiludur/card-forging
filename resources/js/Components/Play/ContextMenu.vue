<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { closeMenu, menu } from './tableOverlays';

/**
 * The right-click menu on the playtest table, drawn once and opened by
 * whichever card asked (`openMenu()` in tableOverlays.js). It is where every
 * move a click does not do lives: the free moves, Redirect, Destroy, a counter.
 * The arrow keys move between items, Enter picks one, Escape closes it.
 */
const box = ref(null);
const left = ref(0);
const top = ref(0);

// Opened at the pointer, then pulled back inside the window once its own
// size is known.
watch(() => [menu.open, menu.x, menu.y], async () => {
    if (!menu.open) return;
    left.value = menu.x;
    top.value = menu.y;
    await nextTick();

    const rect = box.value?.getBoundingClientRect();
    if (!rect) return;
    left.value = Math.max(6, Math.min(menu.x, window.innerWidth - rect.width - 6));
    top.value = Math.max(6, Math.min(menu.y, window.innerHeight - rect.height - 6));
    box.value.querySelector('button:not(:disabled)')?.focus({ preventScroll: true });
});

const pick = (item) => {
    if (item.disabled) return;
    closeMenu();
    item.action?.();
};

const step = (delta) => {
    const buttons = [...(box.value?.querySelectorAll('button:not(:disabled)') ?? [])];
    if (!buttons.length) return;
    const at = buttons.indexOf(document.activeElement);
    buttons[(at + delta + buttons.length) % buttons.length].focus();
};

const onKeydown = (event) => {
    if (!menu.open) return;
    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); closeMenu(); }
    else if (event.key === 'ArrowDown') { event.preventDefault(); step(1); }
    else if (event.key === 'ArrowUp') { event.preventDefault(); step(-1); }
};

const onPointerDown = (event) => {
    if (menu.open && !box.value?.contains(event.target)) closeMenu();
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown, true);
    window.addEventListener('pointerdown', onPointerDown, true);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('wheel', closeMenu, { passive: true });
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKeydown, true);
    window.removeEventListener('pointerdown', onPointerDown, true);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('wheel', closeMenu);
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="menu.open"
            ref="box"
            role="menu"
            :aria-label="menu.title"
            class="fixed z-[60] min-w-[12rem] max-w-[18rem] overflow-hidden rounded-lg border border-white/15 bg-stone-900/95 py-1 text-sm text-stone-100 shadow-2xl backdrop-blur"
            :style="{ left: `${left}px`, top: `${top}px` }"
            @contextmenu.prevent
        >
            <p v-if="menu.title" class="truncate border-b border-white/10 px-3 pb-1 pt-0.5 text-[11px] font-semibold uppercase tracking-wider text-stone-400">
                {{ menu.title }}
            </p>
            <template v-for="(item, index) in menu.items" :key="index">
                <div v-if="item.divider" class="my-1 border-t border-white/10" />
                <button
                    v-else
                    type="button"
                    role="menuitem"
                    class="flex w-full items-baseline gap-3 px-3 py-1 text-left outline-none hover:bg-white/10 focus:bg-white/10 disabled:cursor-default disabled:opacity-40 disabled:hover:bg-transparent"
                    :class="item.danger ? 'text-red-300' : ''"
                    :disabled="item.disabled"
                    @click="pick(item)"
                >
                    <span class="flex-1">{{ item.label }}</span>
                    <span v-if="item.hint" class="text-[11px] text-stone-400">{{ item.hint }}</span>
                </button>
            </template>
        </div>
    </Teleport>
</template>
