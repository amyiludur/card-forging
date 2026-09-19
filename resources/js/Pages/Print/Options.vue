<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';

const props = defineProps({
    scenario: { type: Object, required: true },
    options: { type: Object, required: true },
    cardSizes: { type: Object, default: () => ({}) },
    sheetSizes: { type: Object, default: () => ({}) },
    decks: { type: Object, default: () => ({}) },
    layout: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    isModule: { type: Boolean, default: false },
    // 'character', 'domain' and 'deck' print player cards; anything else a scenario's.
    kind: { type: String, default: 'scenario' },
    // Extra query the page must keep hold of. A built deck has no record of its
    // own, so the character, the domain and the cards taken live here.
    context: { type: Object, default: () => ({}) },
});

// Each owner prints through its own route; the options are identical.
const base = props.isModule
    ? `/print/module/${props.scenario.slug}`
    : props.kind === 'deck'
        ? '/print/deck'
        : ['character', 'domain'].includes(props.kind)
            ? `/print/${props.kind}/${props.scenario.slug}`
            : `/print/${props.scenario.slug}`;

const form = ref({ ...props.options });

let timer = null;

// The server recomputes the grid, so the numbers shown are the ones that print.
watch(
    form,
    (value) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(base, { ...props.context, ...value }, { preserveState: true, preserveScroll: true, replace: true });
        }, 200);
    },
    { deep: true }
);

// The context goes in by hand: a nested object like take[slug]=n is not
// something URLSearchParams will write for us.
const contextParams = computed(() => {
    const parts = [];

    for (const [key, value] of Object.entries(props.context)) {
        if (value === null || value === undefined) continue;

        if (typeof value === 'object') {
            for (const [inner, count] of Object.entries(value)) {
                parts.push(`${encodeURIComponent(key)}[${encodeURIComponent(inner)}]=${encodeURIComponent(count)}`);
            }
        } else {
            parts.push(`${encodeURIComponent(key)}=${encodeURIComponent(value)}`);
        }
    }

    return parts;
});

const query = computed(() =>
    [
        ...contextParams.value,
        ...new URLSearchParams(
            Object.fromEntries(Object.entries(form.value).map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : value]))
        ).toString().split('&').filter(Boolean),
    ].join('&')
);

const cardCount = computed(() => {
    if (form.value.deck === 'all') return props.counts.entity + props.counts.board + props.counts.beats;
    return props.counts[form.value.deck] ?? 0;
});

const sheets = computed(() => Math.ceil(cardCount.value / Math.max(1, props.layout.per_page)));

// The preview renders a real sheet at real millimetres, which is wider than the
// panel. Scale the frame down to fit rather than clipping the third column.
const frameWidth = 840;
const previewBox = ref(null);
const previewScale = ref(1);
let observer = null;

const fitPreview = () => {
    const width = previewBox.value?.clientWidth ?? frameWidth;
    previewScale.value = Math.min(1, width / frameWidth);
};

onMounted(() => {
    fitPreview();
    observer = new ResizeObserver(fitPreview);
    if (previewBox.value) observer.observe(previewBox.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <Head :title="`Print ${scenario.name}`" />

    <PageHeader :title="`Print — ${scenario.name}`" subtitle="Lay the cards out on sheets, then print or export a PDF.">
        <template #actions>
            <a :href="`${base}/sheet?${query}`" target="_blank" rel="noopener" class="btn-ghost">Open preview</a>
            <a :href="`${base}/pdf?${query}`" class="btn-primary">Download PDF</a>
        </template>
    </PageHeader>

    <div class="grid gap-8 px-6 py-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <div class="space-y-5">
            <div>
                <label class="field-label">What to print</label>
                <select v-model="form.deck" class="field">
                    <option v-for="(label, key) in decks" :key="key" :value="key">{{ label }}</option>
                </select>
                <p v-if="kind === 'character'" class="field-hint">
                    {{ counts.entity }} deck cards, kit and upgrades included, plus the character card.
                    Each card prints as many copies as its quantity.
                </p>
                <p v-else-if="kind === 'domain'" class="field-hint">
                    {{ counts.entity }} cards in the pool, upgrades included.
                    Each card prints as many copies as its quantity.
                </p>
                <p v-else-if="kind === 'deck'" class="field-hint">
                    {{ counts.entity }} cards in the deck as it is built, one sheet entry per copy.
                    Kit, upgrades and the character card print alongside it.
                </p>
                <p v-else class="field-hint">
                    Entity deck {{ counts.entity }} · board {{ counts.board }}<span v-if="!isModule"> · beats {{ counts.beats }}</span> cards.
                    Each card prints as many copies as its quantity.
                </p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Card size</label>
                    <select v-model="form.card_size" class="field">
                        <option v-for="(size, key) in cardSizes" :key="key" :value="key">{{ size.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="field-label">Sheet</label>
                    <select v-model="form.sheet_size" class="field">
                        <option v-for="(size, key) in sheetSizes" :key="key" :value="key">{{ size.label }}</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Custom width (mm)</label>
                    <input v-model.number="form.custom_width" type="number" step="0.1" min="0" class="field" placeholder="0 = use card size">
                </div>
                <div>
                    <label class="field-label">Custom height (mm)</label>
                    <input v-model.number="form.custom_height" type="number" step="0.1" min="0" class="field" placeholder="0 = use card size">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label">Bleed (mm)</label>
                    <input v-model.number="form.bleed" type="number" step="0.5" min="0" max="10" class="field">
                </div>
                <div>
                    <label class="field-label">Sheet margin</label>
                    <input v-model.number="form.margin" type="number" step="0.5" min="0" max="30" class="field">
                </div>
                <div>
                    <label class="field-label">Gutter</label>
                    <input v-model.number="form.gutter" type="number" step="0.5" min="0" max="20" class="field">
                </div>
            </div>

            <div class="space-y-2 rounded-lg border border-stone-300 bg-white p-4 text-sm">
                <label class="flex items-center gap-2">
                    <input v-model="form.crop_marks" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                    Crop marks in the sheet margin
                </label>
                <label class="flex items-center gap-2">
                    <input v-model="form.backs" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                    Card backs on alternating sheets (mirrored for duplex)
                </label>
                <label class="flex items-center gap-2">
                    <input v-model="form.auto_icons" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                    Turn "2 omen" into an icon automatically
                </label>
                <label class="flex items-center gap-2">
                    <input v-model="form.show_placeholders" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                    Mark placeholder cards on the print (proof copies)
                </label>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-3 font-serif text-base font-semibold">This will print</h2>
                <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    <div><dt class="field-micro">Cards</dt><dd class="text-lg font-semibold">{{ cardCount }}</dd></div>
                    <div><dt class="field-micro">Per sheet</dt><dd class="text-lg font-semibold">{{ layout.columns }} × {{ layout.rows }}</dd></div>
                    <div><dt class="field-micro">Sheets</dt><dd class="text-lg font-semibold">{{ sheets }}{{ form.backs ? ` + ${sheets} backs` : '' }}</dd></div>
                    <div><dt class="field-micro">Fits</dt><dd class="text-lg font-semibold" :class="layout.overflows ? 'text-red-700' : 'text-emerald-700'">{{ layout.overflows ? 'no' : 'yes' }}</dd></div>
                </dl>

                <p v-if="layout.overflows" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-800">
                    At these settings the cards run past the sheet. Reduce the margin, the bleed or the card size.
                </p>
            </div>

            <div ref="previewBox" class="overflow-hidden rounded-lg border border-stone-300 bg-stone-800 p-2">
                <iframe
                    :src="`${base}/sheet?${query}`"
                    class="rounded bg-white"
                    :style="{
                        width: `${frameWidth}px`,
                        height: `${Math.round(80 / previewScale)}vh`,
                        transform: `scale(${previewScale})`,
                        transformOrigin: 'top left',
                        marginBottom: `${Math.round((previewScale - 1) * 80)}vh`,
                    }"
                    title="Print preview"
                />
            </div>

            <p class="text-xs leading-relaxed text-stone-600">
                The PDF is rendered by headless Chromium on the server. If that is not available, open the preview in a
                new tab and use the browser's own print dialogue with "Save as PDF", margins set to none.
            </p>
        </div>
    </div>
</template>
