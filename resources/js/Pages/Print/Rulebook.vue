<script setup>
import { computed, ref, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    options: { type: Object, required: true },
    sheetSizes: { type: Object, default: () => ({}) },
    maxColumns: { type: Number, default: 3 },
    layout: { type: Object, required: true },
    counts: { type: Object, default: () => ({ documents: 0, printing: 0 }) },
    // Every document this page could print. The sheet is built from the same
    // list, so ticking one here and printing agree.
    items: { type: Array, default: () => [] },
    selection: { type: Object, default: () => ({ only: '', except: '', qty: {} }) },
    // Tunable numbers the printed rules quote that are not decided yet.
    placeholderNumbers: { type: Array, default: () => [] },
});

const base = '/print/rules';

const form = ref({ ...props.options });

// Which documents are held back. Read once from the URL the page arrived on;
// after that this is where the answer lives. Same rule as the card picker.
const excluded = ref(
    new Set(
        props.selection.only
            ? props.items.map((item) => item.key).filter((key) => !props.selection.only.split(',').includes(key))
            : (props.selection.except ? props.selection.except.split(',') : [])
    )
);

const isPrinted = (item) => !excluded.value.has(item.key);

const toggle = (item) => {
    const next = new Set(excluded.value);
    next.has(item.key) ? next.delete(item.key) : next.add(item.key);
    excluded.value = next;
};

const setAll = (printed) => {
    const next = new Set(excluded.value);
    props.items.forEach((item) => (printed ? next.delete(item.key) : next.add(item.key)));
    excluded.value = next;
};

/**
 * The picked run, as query parameters. Saying "only these three" and "all but
 * these three" are the same answer, so whichever list is shorter is the one
 * written — except that an empty "only" would read as the whole rulebook, so
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

let timer = null;

// The server recomputes the page, so the numbers shown are the ones that print.
watch(
    [form, selectionParams],
    ([value, selected]) => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            router.get(base, { ...value, ...selected }, { preserveState: true, preserveScroll: true, replace: true });
        }, 200);
    },
    { deep: true }
);

// An edge left blank is not zero: it is absent, and the server reads an absent
// one as "follow the shared margin". So it stays out of the query.
const query = computed(() =>
    new URLSearchParams(
        Object.fromEntries(
            Object.entries({ ...form.value, ...selectionParams.value })
                .filter(([, value]) => value !== null && value !== undefined && value !== '' && !Number.isNaN(value))
                .map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : value])
        )
    ).toString()
);

const printing = computed(() => props.items.filter(isPrinted).length);
const heldBack = computed(() => props.items.length - printing.value);
const words = computed(() => props.items.filter(isPrinted).reduce((total, item) => total + item.words, 0));

// The four edges open on their own once any of them is in use, so a shared
// link lands on the settings it is carrying rather than hiding them.
const edgesOpen = ref(
    props.options.sheet_size === 'custom' ||
        ['margin_top', 'margin_right', 'margin_bottom', 'margin_left'].some((key) => props.options[key] !== null)
);
</script>

<template>
    <Head title="Print the rulebook" />

    <PageHeader title="Print — Rulebook" subtitle="The rules as pages of text, then print or export a PDF.">
        <template #actions>
            <a :href="`${base}/sheet?${query}`" target="_blank" rel="noopener" class="btn-ghost">Open preview</a>
            <a :href="`${base}/pdf?${query}`" class="btn-primary"><Icon name="print" /> Download PDF</a>
        </template>
    </PageHeader>

    <div class="grid gap-8 px-6 py-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <div class="space-y-5">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <p class="field-micro">This run</p>
                <p class="mt-1 text-sm text-stone-700">
                    {{ printing }} of {{ items.length }}
                    {{ items.length === 1 ? 'document' : 'documents' }}, about
                    {{ words.toLocaleString() }} words.
                </p>
                <p v-if="heldBack" class="field-hint">
                    {{ heldBack }} held back. They are still part of the rulebook — the sheet says how many were left
                    out of the run.
                </p>
            </div>

            <div class="space-y-3 rounded-lg border border-stone-300 bg-white p-4">
                <p class="field-micro">The page</p>

                <div>
                    <label class="field-label">Sheet</label>
                    <select v-model="form.sheet_size" class="field">
                        <option v-for="(size, key) in sheetSizes" :key="key" :value="key">{{ size.label }}</option>
                    </select>
                </div>

                <div v-if="form.sheet_size === 'custom'" class="grid grid-cols-2 gap-3">
                    <label class="block"><span class="field-micro">Width (mm)</span>
                        <input v-model.number="form.custom_sheet_width" type="number" step="0.1" min="0" class="field" placeholder="0 = A4"></label>
                    <label class="block"><span class="field-micro">Height (mm)</span>
                        <input v-model.number="form.custom_sheet_height" type="number" step="0.1" min="0" class="field" placeholder="0 = A4"></label>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Margin (mm)</label>
                        <input v-model.number="form.margin" type="number" step="0.5" min="0" max="60" class="field">
                    </div>
                    <div>
                        <label class="field-label">Text size (pt)</label>
                        <input v-model.number="form.font_size" type="number" step="0.5" min="6" max="18" class="field">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="field-label">Columns</label>
                        <input v-model.number="form.columns" type="number" step="1" min="1" :max="maxColumns" class="field">
                    </div>
                    <div>
                        <label class="field-label">Column gap (mm)</label>
                        <input v-model.number="form.column_gap" type="number" step="0.5" min="0" max="30" class="field" :disabled="form.columns < 2">
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.new_page_per_document" type="checkbox" class="rounded border-stone-400">
                    Start each document on a new page
                </label>
            </div>

            <details class="rounded-lg border border-stone-300 bg-white" :open="edgesOpen">
                <summary class="cursor-pointer px-4 py-3 text-sm font-semibold">The four edges</summary>
                <div class="space-y-3 border-t border-stone-200 p-4">
                    <p class="field-hint">
                        Each edge on its own, in millimetres — a rulebook that will be bound wants a wider inside
                        margin than outside one. Left empty an edge follows the margin above; empty is absent, not 0.
                    </p>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="block"><span class="field-micro">Top</span>
                            <input v-model="form.margin_top" type="number" step="0.5" min="0" max="60" class="field" :placeholder="String(form.margin)"></label>
                        <label class="block"><span class="field-micro">Right</span>
                            <input v-model="form.margin_right" type="number" step="0.5" min="0" max="60" class="field" :placeholder="String(form.margin)"></label>
                        <label class="block"><span class="field-micro">Bottom</span>
                            <input v-model="form.margin_bottom" type="number" step="0.5" min="0" max="60" class="field" :placeholder="String(form.margin)"></label>
                        <label class="block"><span class="field-micro">Left</span>
                            <input v-model="form.margin_left" type="number" step="0.5" min="0" max="60" class="field" :placeholder="String(form.margin)"></label>
                    </div>
                </div>
            </details>

            <div class="space-y-2 rounded-lg border border-stone-300 bg-white p-4">
                <p class="field-micro">What else goes in the book</p>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.contents" type="checkbox" class="rounded border-stone-400">
                    A contents list, from the headings
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.tunable_numbers" type="checkbox" class="rounded border-stone-400">
                    The tunable numbers as an appendix
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.keyword_glossary" type="checkbox" class="rounded border-stone-400">
                    The keyword library as a glossary
                </label>

                <p class="field-hint">
                    Both appendices list what is already in the editor, placeholders flagged as placeholders. Nothing
                    here writes rules.
                </p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="stat">
                    <p class="stat-value">{{ layout.sheet_w }} × {{ layout.sheet_h }}</p>
                    <p class="stat-label">the sheet, in millimetres</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ layout.column_w }} mm</p>
                    <p class="stat-label">
                        the measure{{ options.columns > 1 ? ` — ${options.columns} columns of it` : '' }}
                    </p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ printing }}</p>
                    <p class="stat-label">documents in this run</p>
                </div>
            </div>

            <p v-if="layout.overflows" class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
                These margins leave no room to print in. The sheet still renders exactly as asked, so you can see it —
                nothing is shrunk for you.
            </p>

            <p v-if="placeholderNumbers.length" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                This run quotes {{ placeholderNumbers.length }}
                {{ placeholderNumbers.length === 1 ? 'number that is' : 'numbers that are' }} still a placeholder:
                <code v-for="key in placeholderNumbers" :key="key" class="mx-0.5">{{ key }}</code>.
                They print as they stand; the sheet says so too.
            </p>

            <div class="rounded-lg border border-stone-300 bg-white">
                <div class="flex items-center justify-between gap-3 border-b border-stone-200 px-4 py-3">
                    <p class="text-sm font-semibold">Documents</p>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="text-stone-600 hover:underline" @click="setAll(true)">All</button>
                        <button type="button" class="text-stone-600 hover:underline" @click="setAll(false)">None</button>
                    </div>
                </div>

                <ul v-if="items.length" class="divide-y divide-stone-100">
                    <li v-for="item in items" :key="item.key" class="flex items-center gap-3 px-4 py-2 text-sm">
                        <input :id="item.key" type="checkbox" :checked="isPrinted(item)" class="rounded border-stone-400" @change="toggle(item)">
                        <label :for="item.key" class="flex-1 cursor-pointer" :class="{ 'text-stone-400 line-through': !isPrinted(item) }">
                            {{ item.name }}
                        </label>
                        <span class="text-xs text-stone-500">{{ item.words.toLocaleString() }} words</span>
                    </li>
                </ul>

                <p v-else class="px-4 py-6 text-sm text-stone-600">
                    No rules documents yet. Run <code>php artisan design:import</code>, or write one on the Rulebook page.
                </p>
            </div>

            <p class="text-xs leading-relaxed text-stone-500">
                Everything on this page travels in the URL, so a rulebook laid out once is a bookmark. The preview is
                the same HTML the PDF is rendered from — page breaks fall where the words run out of paper, so the
                preview shows one long sheet rather than the pages themselves.
            </p>
        </div>
    </div>
</template>
