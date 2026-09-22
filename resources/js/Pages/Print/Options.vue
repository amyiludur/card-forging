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
    // Every card this page could print, one entry per card row. The sheet is
    // built from the same list, so ticking one here and printing agree.
    items: { type: Array, default: () => [] },
    selection: { type: Object, default: () => ({ only: '', except: '', qty: {} }) },
    isModule: { type: Boolean, default: false },
    // 'character', 'domain' and 'deck' print player cards; anything else a scenario's.
    kind: { type: String, default: 'scenario' },
    // Extra query the page must keep hold of. A built deck has no record of its
    // own, so the character, the domain and the cards taken live here.
    context: { type: Object, default: () => ({}) },
    // Saved print setups, offered the same way on every print options page —
    // a sticker sheet lined up once is not just this scenario's to reuse.
    presets: { type: Array, default: () => [] },
    // The pool keys of every card already in the print pool, so the picker
    // can say so rather than offer to add it twice.
    poolKeys: { type: Array, default: () => [] },
    // The pool page only: every printable card there is, to add from.
    catalogue: { type: Array, default: () => [] },
});

// Each owner prints through its own route; the options are identical.
const base = props.isModule
    ? `/print/module/${props.scenario.slug}`
    : props.kind === 'deck' || props.kind === 'pool'
        ? `/print/${props.kind}`
        : ['character', 'domain'].includes(props.kind)
            ? `/print/${props.kind}/${props.scenario.slug}`
            : `/print/${props.scenario.slug}`;

const form = ref({ ...props.options });

// A saved print setup, picked from the dropdown and applied straight to the
// form — the watcher below carries it to the URL like any other change. The
// select always resets to the placeholder after: it names an action, not a
// setting, so there is nothing for it to keep showing as "current".
const presetId = ref('');
const presetName = ref('');

const applyPreset = () => {
    const preset = props.presets.find((p) => p.id === Number(presetId.value));
    presetId.value = '';
    if (!preset) return;

    form.value = { ...preset.options };
};

const savePreset = () => {
    const name = presetName.value.trim();
    if (!name) return;

    router.post('/print-presets', { name, ...form.value }, {
        preserveScroll: true,
        onSuccess: () => {
            presetName.value = '';
        },
    });
};

const deletePreset = (preset) => {
    router.delete(`/print-presets/${preset.id}`, { preserveScroll: true });
};

// Which cards are held back. A run is rarely the whole deck — one card comes
// back smudged, a beat is still being rewritten — so the picker below unticks
// them and the sheet leaves them out. Read once from the URL the page arrived
// on; after that this is where the answer lives.
const excluded = ref(
    new Set(
        props.selection.only
            ? props.items.map((item) => item.key).filter((key) => !props.selection.only.split(',').includes(key))
            : (props.selection.except ? props.selection.except.split(',') : [])
    )
);

const isPrinted = (item) => !excluded.value.has(item.key);

/**
 * The picked run, as query parameters. Saying "only these three" and "all but
 * these three" are the same answer, so whichever list is shorter is the one
 * written — except that an empty "only" would read as the whole deck, so
 * nothing picked is always written as a list of what to hold back.
 */
const selectionParams = computed(() => {
    if (excluded.value.size === 0) return {};

    const kept = props.items.filter(isPrinted).map((item) => item.key);
    const dropped = props.items.filter((item) => !isPrinted(item)).map((item) => item.key);

    return kept.length && kept.length <= dropped.length
        ? { only: kept.join(',') }
        : { except: dropped.join(',') };
});

// A card prints as many copies as its own quantity unless a run asks for
// more (or fewer) of it — a spare, a replacement for one gone missing. Read
// once from the URL the page arrived on, same as `excluded`; after that this
// is where the answer lives. A value equal to the card's own quantity is not
// an override, it is just what would have printed anyway, so it never enters
// this map — an override left blank is absent, not the card's own number.
const printCounts = ref({ ...(props.selection.qty ?? {}) });

const printQtyFor = (item) => {
    const override = printCounts.value[item.key];
    return typeof override === 'number' && override > 0 ? override : item.qty;
};

const setQuantity = (item, value) => {
    const n = Math.max(1, Math.min(999, Math.round(Number(value)) || item.qty));

    // The pool keeps its own count per card, so a number typed there is the
    // pool's to hold rather than an override in the URL.
    if (props.kind === 'pool') {
        router.put(
            `/print-pool/${item.pool_id}`,
            { qty: n === item.card_qty ? null : n },
            { preserveScroll: true, preserveState: true }
        );
        return;
    }

    const next = { ...printCounts.value };

    if (n === item.qty) {
        delete next[item.key];
    } else {
        next[item.key] = n;
    }

    printCounts.value = next;
};

const resetQuantities = () => {
    printCounts.value = {};
};

const quantityParams = computed(() => {
    const entries = Object.entries(printCounts.value);
    return entries.length ? { qty: Object.fromEntries(entries) } : {};
});

// How many cards at the start of the run are already printed. A run, not a
// setup, so it is kept out of `form` and never saved into a print preset.
const offset = ref(props.selection.offset ?? 0);

const offsetParams = computed(() => {
    const n = Math.max(0, Math.round(Number(offset.value)) || 0);
    return n > 0 ? { offset: n } : {};
});

let timer = null;

// The server recomputes the grid, so the numbers shown are the ones that print.
watch(
    [form, selectionParams, quantityParams, offsetParams],
    ([value, selected, qty, skipped]) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(
                base,
                { ...props.context, ...value, ...selected, ...qty, ...skipped },
                { preserveState: true, preserveScroll: true, replace: true }
            );
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

// A quantity override is a nested object too, same reason `take[slug]=n`
// above is not something URLSearchParams will write for us.
const quantityQueryParts = computed(() =>
    Object.entries(printCounts.value).map(([key, value]) => `qty[${encodeURIComponent(key)}]=${encodeURIComponent(value)}`)
);

// An override left blank is not zero: it is absent, and the server reads an
// absent one as "follow the shared setting". So it stays out of the query.
const query = computed(() =>
    [
        ...contextParams.value,
        ...quantityQueryParts.value,
        ...new URLSearchParams(
            Object.fromEntries(
                Object.entries({ ...form.value, ...selectionParams.value, ...offsetParams.value })
                    .filter(([, value]) => value !== null && value !== undefined && value !== '' && !Number.isNaN(value))
                    .map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : value])
            )
        ).toString().split('&').filter(Boolean),
    ].join('&')
);

// Every group is named after the deck that prints it, so one rule covers them
// all and "Everything" needs no list of its own.
const inDeck = computed(() =>
    props.items.filter((item) => form.value.deck === 'all' || item.group === form.value.deck)
);

// The run, before the offset: every card that would print, in print order.
const runTotal = computed(() => inDeck.value.filter(isPrinted).reduce((total, item) => total + printQtyFor(item), 0));

const offsetUsed = computed(() => Math.min(offsetParams.value.offset ?? 0, runTotal.value));

const cardCount = computed(() => runTotal.value - offsetUsed.value);

// The card the run now starts on, so an offset can be checked against the
// last card that came out of the printer rather than counted by hand.
const startsOn = computed(() => {
    let position = 0;

    for (const item of inDeck.value.filter(isPrinted)) {
        const copies = printQtyFor(item);
        if (offsetUsed.value < position + copies) {
            return { item, copy: offsetUsed.value - position + 1, copies };
        }
        position += copies;
    }

    return null;
});

const heldBack = computed(() =>
    inDeck.value.filter((item) => !isPrinted(item)).reduce((total, item) => total + item.qty, 0)
);

// The whole deck, before anything was unticked: what the picker is a share of.
const deckTotal = computed(() => inDeck.value.reduce((total, item) => total + item.qty, 0));

const filter = ref('');

/** The picker, section by section, in the order the deck menu lists them. */
const sections = computed(() => {
    const needle = filter.value.trim().toLowerCase();
    const matching = needle
        ? inDeck.value.filter((item) => item.name.toLowerCase().includes(needle))
        : inDeck.value;

    return Object.keys(props.decks)
        .filter((key) => key !== 'all')
        .map((key) => ({
            key,
            label: props.decks[key] ?? key,
            items: matching.filter((item) => item.group === key),
        }))
        .filter((section) => section.items.length > 0);
});

const toggle = (key) => {
    const next = new Set(excluded.value);
    next.has(key) ? next.delete(key) : next.add(key);
    excluded.value = next;
};

/** Tick or untick a list of cards at once: a section, or everything shown. */
const setAll = (items, printed) => {
    const next = new Set(excluded.value);
    items.forEach((item) => (printed ? next.delete(item.key) : next.add(item.key)));
    excluded.value = next;
};

// ---- The print pool -------------------------------------------------------

const inPool = (item) => props.poolKeys.includes(item.pool_key ?? item.key);

/** Add cards to the pool, each with the number of copies this run would print. */
const addToPool = (items) => {
    const fresh = items.filter((item) => !inPool(item));
    if (!fresh.length) return;

    router.post(
        '/print-pool',
        {
            items: fresh.map((item) => ({
                key: item.pool_key ?? item.key,
                qty: printQtyFor(item) === item.qty && props.kind !== 'pool' ? null : printQtyFor(item),
            })),
        },
        { preserveScroll: true, preserveState: true }
    );
};

const removeFromPool = (item) => {
    router.delete(`/print-pool/${item.pool_id}`, { preserveScroll: true, preserveState: true });
};

const emptyPool = () => {
    if (!window.confirm('Take every card out of the print pool?')) return;
    router.delete('/print-pool', { preserveScroll: true, preserveState: true });
};

// The pool page's "add cards" list: every printable card, grouped by where it
// lives, filtered by name or by owner.
const catalogueFilter = ref('');
const poolGroups = computed(() => Object.fromEntries(Object.entries(props.decks).filter(([key]) => key !== 'all')));
const catalogueGroup = ref('all');

const catalogueSources = computed(() => {
    const needle = catalogueFilter.value.trim().toLowerCase();
    const bySource = new Map();

    for (const entry of props.catalogue) {
        if (catalogueGroup.value !== 'all' && entry.group !== catalogueGroup.value) continue;
        if (needle && !entry.name.toLowerCase().includes(needle) && !entry.source.toLowerCase().includes(needle)) continue;

        if (!bySource.has(entry.source)) bySource.set(entry.source, []);
        bySource.get(entry.source).push(entry);
    }

    return [...bySource.entries()]
        .map(([source, entries]) => ({ source, entries }))
        .sort((a, b) => a.source.localeCompare(b.source));
});

const catalogueShown = computed(() => catalogueSources.value.flatMap((source) => source.entries));

const addEntries = (entries) => {
    const fresh = entries.filter((entry) => !props.poolKeys.includes(entry.key));
    if (!fresh.length) return;

    router.post(
        '/print-pool',
        { items: fresh.map((entry) => ({ key: entry.key })) },
        { preserveScroll: true, preserveState: true }
    );
};

const catalogueOpen = ref(props.kind === 'pool' && props.items.length === 0);

const pickerOpen = ref(props.kind === 'pool' || excluded.value.size > 0 || Object.keys(printCounts.value).length > 0);

const sheets = computed(() => {
    const count = cardCount.value;
    if (count <= 0) return 0;

    const first = Math.max(1, props.layout.first_page ?? props.layout.per_page);
    if (count <= first) return 1;

    return 1 + Math.ceil((count - first) / Math.max(1, props.layout.per_page));
});

// The sticker fields open on their own once any of them is in use, so a shared
// link lands on the settings it is carrying rather than hiding them.
const stickerDefaults = {
    custom_sheet_width: 0,
    custom_sheet_height: 0,
    margin_top: null,
    margin_right: null,
    margin_bottom: null,
    margin_left: null,
    gutter_x: null,
    gutter_y: null,
    columns: 0,
    rows: 0,
    skip: 0,
    offset_x: 0,
    offset_y: 0,
    corner_radius: 2.5,
};

const stickerOpen = ref(
    props.options.sheet_size === 'custom' ||
        Object.entries(stickerDefaults).some(([key, fallback]) => (props.options[key] ?? null) !== fallback)
);

// The preview renders a real sheet at real millimetres, which is wider than the
// panel. Scale the frame down to fit rather than clipping the third column.
const frameWidth = 840;

// The pool changes without the URL changing, so the preview is told to reload
// whenever what it would print does.
const previewKey = computed(() => props.items.map((item) => `${item.key}x${item.qty}`).join(','));
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
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <label class="field-label">Saved print setups</label>
                <p class="field-hint">
                    Everything on this page — card size, sheet, margins, the sticker grid — saved under a name, so a
                    sheet lined up once does not have to be redone for the next scenario or character printed on it.
                </p>

                <div v-if="presets.length" class="mt-2 flex flex-wrap items-center gap-2">
                    <select v-model="presetId" class="field flex-1" @change="applyPreset">
                        <option value="">Load a saved setup…</option>
                        <option v-for="preset in presets" :key="preset.id" :value="preset.id">{{ preset.name }}</option>
                    </select>
                </div>
                <ul v-if="presets.length" class="mt-2 space-y-1 text-xs">
                    <li v-for="preset in presets" :key="preset.id" class="flex items-center justify-between gap-2 text-stone-600">
                        <span class="truncate">{{ preset.name }}</span>
                        <button type="button" class="text-stone-500 hover:text-red-700 hover:underline" @click="deletePreset(preset)">
                            Delete
                        </button>
                    </li>
                </ul>

                <div class="mt-3 flex gap-2">
                    <input
                        v-model="presetName"
                        type="text"
                        class="field flex-1"
                        placeholder="Name this setup, e.g. Avery 63.5×33.9"
                        @keydown.enter.prevent="savePreset"
                    >
                    <button type="button" class="btn-ghost text-xs" :disabled="!presetName.trim()" @click="savePreset">
                        Save
                    </button>
                </div>
            </div>

            <div>
                <label class="field-label">What to print</label>
                <select v-model="form.deck" class="field">
                    <option v-for="(label, key) in decks" :key="key" :value="key">{{ label }}</option>
                </select>
                <p v-if="kind === 'character'" class="field-hint">
                    {{ counts.player ?? 0 }} deck cards, kit and upgrades included, plus the character card.
                    Each card defaults to as many copies as its quantity — raise it in the picker below to print more.
                </p>
                <p v-else-if="kind === 'domain'" class="field-hint">
                    {{ counts.all ?? 0 }} cards in the pool, upgrades included.
                    Each card defaults to as many copies as its quantity — raise it in the picker below to print more.
                </p>
                <p v-else-if="kind === 'pool'" class="field-hint">
                    {{ counts.all ?? 0 }} cards gathered from anywhere, printed in the order they were added. Add them
                    below, or with "Add to pool" on any other print page.
                </p>
                <p v-else-if="kind === 'deck'" class="field-hint">
                    {{ counts.player ?? 0 }} cards in the deck as it is built, one sheet entry per copy.
                    Kit, upgrades and the character card print alongside it.
                </p>
                <p v-else class="field-hint">
                    Entity deck {{ counts.entity ?? 0 }} · board {{ counts.board ?? 0 }}<span v-if="!isModule"> · beats {{ counts.beats ?? 0 }} · town {{ counts.town ?? 0 }} · setup {{ counts.setup ?? 0 }}</span> cards.
                    Each card defaults to as many copies as its quantity — raise it in the picker below to print more.
                </p>
            </div>

            <details
                v-if="kind === 'pool'"
                class="rounded-lg border border-stone-300 bg-white"
                :open="catalogueOpen"
                @toggle="catalogueOpen = $event.target.open"
            >
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">
                    Add cards
                    <span class="ml-1 font-normal text-stone-600">from every scenario, module, character and domain</span>
                </summary>

                <div class="space-y-3 border-t border-stone-200 px-4 py-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="catalogueFilter" type="search" class="field min-w-[10rem] flex-1" placeholder="Filter by name or owner">
                        <select v-model="catalogueGroup" class="field w-auto">
                            <option value="all">Every kind</option>
                            <option v-for="(label, key) in poolGroups" :key="key" :value="key">{{ label }}</option>
                        </select>
                        <button
                            type="button"
                            class="btn-ghost text-xs"
                            :disabled="!catalogueShown.some((entry) => !poolKeys.includes(entry.key))"
                            @click="addEntries(catalogueShown)"
                        >
                            Add all shown
                        </button>
                    </div>

                    <div class="max-h-96 space-y-3 overflow-y-auto pr-1">
                        <div v-for="group in catalogueSources" :key="group.source" class="space-y-1">
                            <div class="flex items-center justify-between border-b border-stone-200 pb-1">
                                <span class="field-micro">{{ group.source }}</span>
                                <button type="button" class="text-xs text-stone-600 hover:underline" @click="addEntries(group.entries)">add all</button>
                            </div>

                            <div v-for="entry in group.entries" :key="entry.key" class="flex items-center gap-2 py-0.5 text-sm">
                                <span class="min-w-0 flex-1 truncate">{{ entry.name }}</span>
                                <span class="text-xs text-stone-500">{{ decks[entry.group] ?? entry.group }}</span>
                                <span v-if="entry.qty > 1" class="text-xs text-stone-500">×{{ entry.qty }}</span>
                                <span v-if="poolKeys.includes(entry.key)" class="w-12 text-right text-xs text-emerald-700">in pool</span>
                                <button
                                    v-else
                                    type="button"
                                    class="w-12 text-right text-xs font-semibold text-amber-700 hover:underline"
                                    @click="addEntries([entry])"
                                >
                                    + add
                                </button>
                            </div>
                        </div>

                        <p v-if="!catalogueSources.length" class="text-sm text-stone-600">No card matches.</p>
                    </div>
                </div>
            </details>

            <details
                class="rounded-lg border border-stone-300 bg-white"
                :open="pickerOpen"
                @toggle="pickerOpen = $event.target.open"
            >
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">
                    {{ kind === 'pool' ? 'In the pool' : 'Which cards' }}
                    <span class="ml-1 font-normal text-stone-600">{{ cardCount }} of {{ deckTotal }}</span>
                </summary>

                <div class="space-y-3 border-t border-stone-200 px-4 py-4">
                    <p class="text-xs leading-relaxed text-stone-600">
                        Everything is printed unless you say otherwise. Untick a card to leave it out, or untick the lot
                        and tick back the few you want — one card come back from the printer smudged does not need the
                        whole deck run again. Raise a card's number to print more copies than the deck calls for — the
                        Tentacle needs 3, print 6 for a full extra supply. The choice travels in the URL with the rest
                        of the settings.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <input v-model="filter" type="search" class="field min-w-[10rem] flex-1" placeholder="Filter by name">
                        <button type="button" class="btn-ghost text-xs" @click="setAll(inDeck, true)">Print all</button>
                        <button type="button" class="btn-ghost text-xs" @click="setAll(inDeck, false)">Print none</button>
                        <button
                            v-if="Object.keys(printCounts).length"
                            type="button"
                            class="btn-ghost text-xs"
                            @click="resetQuantities"
                        >
                            Reset copies
                        </button>
                        <button
                            v-if="kind !== 'pool'"
                            type="button"
                            class="btn-ghost text-xs"
                            :disabled="!inDeck.some((item) => isPrinted(item) && !inPool(item))"
                            title="Put every ticked card into the print pool, to print alongside cards from elsewhere"
                            @click="addToPool(inDeck.filter(isPrinted))"
                        >
                            Add ticked to pool
                        </button>
                        <button v-if="kind === 'pool' && items.length" type="button" class="btn-ghost text-xs" @click="emptyPool">
                            Empty pool
                        </button>
                    </div>

                    <!-- A long deck scrolls inside the panel rather than
                         pushing the rest of the settings off the page. -->
                    <div class="max-h-96 space-y-3 overflow-y-auto pr-1">
                        <div v-for="section in sections" :key="section.key" class="space-y-1">
                            <div class="flex items-center justify-between border-b border-stone-200 pb-1">
                                <span class="field-micro">{{ section.label }}</span>
                                <span class="space-x-2 text-xs">
                                    <button type="button" class="text-stone-600 hover:underline" @click="setAll(section.items, true)">all</button>
                                    <button type="button" class="text-stone-600 hover:underline" @click="setAll(section.items, false)">none</button>
                                </span>
                            </div>

                            <label
                                v-for="item in section.items"
                                :key="item.key"
                                class="flex items-center gap-2 py-0.5 text-sm"
                                :class="{ 'text-stone-400': !isPrinted(item) }"
                            >
                                <input
                                    type="checkbox"
                                    class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                                    :checked="isPrinted(item)"
                                    @change="toggle(item.key)"
                                >
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate">{{ item.name }}</span>
                                    <span v-if="item.source" class="block truncate text-xs text-stone-500">{{ item.source }}</span>
                                </span>
                                <span v-if="item.is_placeholder" class="text-xs text-amber-700">placeholder</span>
                                <input
                                    type="number"
                                    min="1"
                                    max="999"
                                    class="w-14 rounded border border-stone-300 bg-white px-1 py-0.5 text-right text-xs text-stone-700 disabled:bg-stone-100 disabled:text-stone-400"
                                    :value="printQtyFor(item)"
                                    :disabled="!isPrinted(item)"
                                    :title="`How many copies of ${item.name} to print`"
                                    @click.stop
                                    @change="setQuantity(item, $event.target.value)"
                                >
                                <span v-if="kind === 'pool' ? item.card_qty > 1 : item.qty > 1" class="text-xs text-stone-500">
                                    of {{ kind === 'pool' ? item.card_qty : item.qty }} required
                                </span>
                                <button
                                    v-if="kind === 'pool'"
                                    type="button"
                                    class="text-xs text-stone-500 hover:text-red-700"
                                    :title="`Take ${item.name} out of the pool`"
                                    @click.prevent.stop="removeFromPool(item)"
                                >
                                    ✕
                                </button>
                                <span v-else-if="inPool(item)" class="w-10 text-right text-xs text-emerald-700" title="Already in the print pool">pool ✓</span>
                                <button
                                    v-else
                                    type="button"
                                    class="w-10 text-right text-xs text-amber-700 hover:underline"
                                    :title="`Add ${item.name} to the print pool`"
                                    @click.prevent.stop="addToPool([item])"
                                >
                                    + pool
                                </button>
                            </label>
                        </div>

                        <p v-if="!sections.length" class="text-sm text-stone-600">
                            <template v-if="kind === 'pool'">The pool is empty. Add cards above, or from any print page.</template>
                            <template v-else>Nothing here to pick from: this deck has no cards yet.</template>
                        </p>
                    </div>
                </div>
            </details>

            <div>
                <label class="field-label">Offset (cards)</label>
                <input v-model.number="offset" type="number" step="1" min="0" class="field" placeholder="0">
                <p class="field-hint">
                    Skip this many cards from the start of the run — for a run that stopped part way, the printer jammed
                    after the first sheet. Not the same as "Leave blank", which leaves label cells empty.
                    <template v-if="offsetUsed > 0 && startsOn">
                        The run now starts at card {{ offsetUsed + 1 }} of {{ runTotal }}:
                        <strong>{{ startsOn.item.name }}</strong><span v-if="startsOn.copies > 1"> (copy {{ startsOn.copy }} of {{ startsOn.copies }})</span>.
                    </template>
                    <template v-else-if="offsetUsed > 0">
                        That skips the whole run of {{ runTotal }}: nothing is left to print.
                    </template>
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
                    <p v-if="form.sheet_size === 'custom'" class="field-hint">
                        Set the sheet's width and height under "Sticker sheets and alignment". Without them it falls back to A4.
                    </p>
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

            <details
                class="rounded-lg border border-stone-300 bg-white"
                :open="stickerOpen"
                @toggle="stickerOpen = $event.target.open"
            >
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">Sticker sheets and alignment</summary>

                <div class="space-y-5 border-t border-stone-200 px-4 py-4">
                    <p class="text-xs leading-relaxed text-stone-600">
                        A sheet of die-cut labels has its grid printed on the box. Type the numbers in and they are used
                        exactly as given — nothing here is worked out for you. Every field travels in the URL, so a sheet
                        you have lined up once can be bookmarked or shared as a link.
                    </p>

                    <div>
                        <label class="field-label">Sheet size (mm)</label>
                        <div class="grid gap-3 grid-cols-2">
                            <label class="block"><span class="field-micro">Width</span>
                                <input v-model.number="form.custom_sheet_width" type="number" step="0.1" min="0" class="field" placeholder="0 = stock"></label>
                            <label class="block"><span class="field-micro">Height</span>
                                <input v-model.number="form.custom_sheet_height" type="number" step="0.1" min="0" class="field" placeholder="0 = stock"></label>
                        </div>
                        <p class="field-hint">Overrides the sheet chosen above, the way a custom card size overrides the stock one.</p>
                    </div>

                    <div>
                        <label class="field-label">Margins per edge (mm)</label>
                        <div class="grid gap-3 grid-cols-2">
                            <label class="block"><span class="field-micro">Top</span>
                                <input v-model.number="form.margin_top" type="number" step="0.1" min="0" max="60" class="field" :placeholder="form.margin"></label>
                            <label class="block"><span class="field-micro">Right</span>
                                <input v-model.number="form.margin_right" type="number" step="0.1" min="0" max="60" class="field" :placeholder="form.margin"></label>
                            <label class="block"><span class="field-micro">Bottom</span>
                                <input v-model.number="form.margin_bottom" type="number" step="0.1" min="0" max="60" class="field" :placeholder="form.margin"></label>
                            <label class="block"><span class="field-micro">Left</span>
                                <input v-model.number="form.margin_left" type="number" step="0.1" min="0" max="60" class="field" :placeholder="form.margin"></label>
                        </div>
                        <p class="field-hint">
                            The distance from the paper's edge to the first label. Leave one empty and it follows the sheet
                            margin above; 0 is a real measurement, not an empty field.
                        </p>
                    </div>

                    <div>
                        <label class="field-label">Gap between labels (mm)</label>
                        <div class="grid gap-3 grid-cols-2">
                            <label class="block"><span class="field-micro">Across</span>
                                <input v-model.number="form.gutter_x" type="number" step="0.1" min="0" max="40" class="field" :placeholder="form.gutter"></label>
                            <label class="block"><span class="field-micro">Down</span>
                                <input v-model.number="form.gutter_y" type="number" step="0.1" min="0" max="40" class="field" :placeholder="form.gutter"></label>
                        </div>
                        <p class="field-hint">
                            Empty follows the gutter above. If your sheet quotes a pitch instead — corner to corner — match
                            it against the pitch reported on the right.
                        </p>
                    </div>

                    <div>
                        <label class="field-label">Grid</label>
                        <div class="grid gap-3 grid-cols-2">
                            <label class="block"><span class="field-micro">Columns</span>
                                <input v-model.number="form.columns" type="number" step="1" min="0" max="20" class="field" placeholder="0 = fit"></label>
                            <label class="block"><span class="field-micro">Rows</span>
                                <input v-model.number="form.rows" type="number" step="1" min="0" max="20" class="field" placeholder="0 = fit"></label>
                        </div>
                        <p class="field-hint">
                            A label sheet's grid is fixed, so say what it is rather than letting the layout fit what it can.
                            0 keeps the old behaviour. A grid that runs off the paper is reported, not shrunk.
                        </p>
                    </div>

                    <div class="grid gap-3 grid-cols-2">
                        <div>
                            <label class="field-label">Leave blank</label>
                            <input v-model.number="form.skip" type="number" step="1" min="0" class="field">
                            <p class="field-hint">Labels already peeled off the first sheet.</p>
                        </div>
                        <div>
                            <label class="field-label">Corner radius</label>
                            <input v-model.number="form.corner_radius" type="number" step="0.1" min="0" max="20" class="field">
                            <p class="field-hint">In mm. Match the label's own rounding.</p>
                        </div>
                    </div>

                    <div>
                        <label class="field-label">Printer nudge (mm)</label>
                        <div class="grid gap-3 grid-cols-2">
                            <label class="block"><span class="field-micro">Right +</span>
                                <input v-model.number="form.offset_x" type="number" step="0.1" min="-20" max="20" class="field"></label>
                            <label class="block"><span class="field-micro">Down +</span>
                                <input v-model.number="form.offset_y" type="number" step="0.1" min="-20" max="20" class="field"></label>
                        </div>
                        <p class="field-hint">
                            Shifts the whole grid, crop marks included, for a printer that feeds a millimetre out.
                            Print one sheet on plain paper, hold it against the labels, then nudge.
                        </p>
                    </div>
                </div>
            </details>
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

                <p class="mt-3 text-xs text-stone-600">
                    Card {{ layout.cell_w }} × {{ layout.cell_h }} mm · pitch {{ layout.pitch_x }} × {{ layout.pitch_y }} mm
                    <span v-if="layout.skipped"> · first sheet holds {{ layout.first_page }} after {{ layout.skipped }} blank</span>
                    <span v-if="heldBack"> · {{ heldBack }} left out of this run</span>
                    <span v-if="offsetUsed"> · starting at card {{ offsetUsed + 1 }} of {{ runTotal }}</span>
                </p>

                <p v-if="layout.overflows" class="mt-3 rounded bg-red-50 px-3 py-2 text-sm text-red-800">
                    At these settings the cards run past the sheet. Reduce the margin, the bleed or the card size.
                </p>
            </div>

            <div ref="previewBox" class="overflow-hidden rounded-lg border border-stone-300 bg-stone-800 p-2">
                <iframe
                    :key="previewKey"
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
