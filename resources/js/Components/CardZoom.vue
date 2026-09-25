<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import CardPreview from './CardPreview.vue';

// Rendered only while a card is open, so mounting is what wires up the keyboard
// and unmounting is what tears it down.
const props = defineProps({
    card: { type: Object, required: true },
    kind: { type: String, default: 'entity' },
    editHref: { type: String, default: null },
    caption: { type: String, default: '' },
    position: { type: Number, default: 1 },
    // Marks the half that actually resolves, the same way the storyline row does.
    highlight: { type: String, default: null },
    total: { type: Number, default: 1 },
    // The card's print pool key. Left out, it is worked out from the kind and
    // the card's id; false hides the button, for a card with nothing to print.
    poolKey: { type: [String, Boolean], default: null },
});

const emit = defineEmits(['close', 'step']);

const closeButton = ref(null);
const page = usePage();

// A kind names the card face; the pool names the deck that prints it. They
// only differ for beats. A card not saved yet has no id and so no key.
const POOL_GROUPS = { entity: 'entity', board: 'board', beat: 'beats', town: 'town', setup: 'setup', character: 'character', player: 'player' };

const resolvedPoolKey = computed(() => {
    if (props.poolKey === false) return null;
    if (typeof props.poolKey === 'string') return props.poolKey;

    const group = POOL_GROUPS[props.kind];

    return group && props.card?.id ? `${group}:${props.card.id}` : null;
});

const inPool = computed(() => (page.props.nav?.printPoolKeys ?? []).includes(resolvedPoolKey.value));
const adding = ref(false);

// The pool keeps a key, not a copy, so an unsaved edit still prints once saved.
const addToPool = () => {
    if (!resolvedPoolKey.value || inPool.value) return;

    router.post(
        '/print-pool',
        { items: [{ key: resolvedPoolKey.value }] },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => (adding.value = true),
            onFinish: () => (adding.value = false),
        }
    );
};
const viewport = ref({ width: 1024, height: 768 });

// The biggest card that still fits, at the same 63.5 × 88.9 mm proportions the
// preview and the print sheet use. 560px is about 8.8× print size, past which
// the em-scaled text stops looking like a card and starts looking like a poster.
const width = computed(() => {
    const byHeight = ((viewport.value.height - 190) / 88.9) * 63.5;
    const byWidth = viewport.value.width - 140;

    return Math.round(Math.max(200, Math.min(560, byHeight, byWidth)));
});

const measure = () => {
    viewport.value = { width: window.innerWidth, height: window.innerHeight };
};

const onKeydown = (event) => {
    if (event.key === 'Escape') {
        emit('close');
    } else if (event.key === 'ArrowLeft') {
        emit('step', -1);
    } else if (event.key === 'ArrowRight') {
        emit('step', 1);
    }
};

onMounted(() => {
    measure();
    window.addEventListener('resize', measure);
    window.addEventListener('keydown', onKeydown);

    // Keeps the page behind from scrolling under the overlay.
    document.body.style.overflow = 'hidden';
    closeButton.value?.focus();
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measure);
    window.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-4 overflow-auto bg-stone-950/75 p-6"
            role="dialog"
            aria-modal="true"
            aria-label="Card at full size"
            @click.self="emit('close')"
        >
            <div class="flex items-center gap-4">
                <button
                    v-if="total > 1"
                    type="button"
                    class="zoom-step"
                    :disabled="position <= 1"
                    aria-label="Previous card"
                    @click="emit('step', -1)"
                >
                    ‹
                </button>

                <CardPreview :card="card" :kind="kind" :width="width" :highlight="highlight" />

                <button
                    v-if="total > 1"
                    type="button"
                    class="zoom-step"
                    :disabled="position >= total"
                    aria-label="Next card"
                    @click="emit('step', 1)"
                >
                    ›
                </button>
            </div>

            <p v-if="caption || total > 1" class="text-center text-sm text-stone-200">
                <span v-if="caption">{{ caption }}</span>
                <span v-if="caption && total > 1" class="text-stone-400"> · </span>
                <span v-if="total > 1" class="text-stone-400">{{ position }} of {{ total }}</span>
            </p>

            <div class="flex items-center gap-3">
                <Link v-if="editHref" :href="editHref" class="btn-ghost">Edit card</Link>
                <template v-if="resolvedPoolKey">
                    <Link v-if="inPool" href="/print/pool" class="btn-ghost">In print pool ✓</Link>
                    <button v-else type="button" class="btn-ghost" :disabled="adding" @click="addToPool">
                        Add to print pool
                    </button>
                </template>
                <button ref="closeButton" type="button" class="btn-ghost" @click="emit('close')">Close</button>
            </div>

            <p class="text-xs text-stone-400">
                Esc closes<span v-if="total > 1"> · ← and → walk the list</span>
            </p>
        </div>
    </Teleport>
</template>

<style scoped>
.zoom-step {
    display: flex;
    height: 2.75rem;
    width: 2.75rem;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 1px solid rgb(120 113 108);
    background: rgb(28 25 23 / 0.7);
    font-size: 1.5rem;
    line-height: 1;
    color: rgb(231 229 228);
}
.zoom-step:hover:not(:disabled) {
    border-color: rgb(214 211 209);
    color: rgb(250 250 249);
}
.zoom-step:disabled {
    opacity: 0.3;
}
</style>
