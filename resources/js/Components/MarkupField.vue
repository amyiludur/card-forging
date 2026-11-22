<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
import Icon from './Icon.vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    label: { type: String, default: '' },
    rows: { type: Number, default: 3 },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    // The Dread rule this field's card would print for {dreadRule}. Null means
    // the card has no scenario behind it — a module card, or a player card —
    // so the token is not offered and would not resolve.
    dreadRule: { type: String, default: null },
    // The same for {dreadAmount}: the scenario's starting Dread as card text,
    // so an equation shows here the way it prints. Null on the same cards the
    // rule is null on, and for the same reason.
    dreadAmount: { type: String, default: null },
    // And {omenAmount}: the omen the scenario's pool starts on — its own, or the
    // shared tunable number when it sets none. Null on the same cards.
    omenAmount: { type: String, default: null },
    // The name of the card this field is on, for {this}. Null means the text
    // is on no card — a scenario's rules, say — so the token is not offered.
    // An empty string is a card not named yet: offered, and reported as ?this.
    cardName: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const page = usePage();
const textarea = ref(null);
const showTokens = ref(false);

const icons = computed(() => page.props.markup?.icons ?? {});
const paths = computed(() => page.props.markup?.paths ?? {});
const config = computed(() => page.props.markup?.config ?? {});
const keywords = computed(() => page.props.markup?.keywords ?? {});
const cards = computed(() => page.props.markup?.cards ?? {});

// Every card the picker can name, one row each. The token names the owner
// whenever there is one: that form always follows a rename, even once a
// second card of the same name turns up.
const KIND_LABELS = { entity: 'entity', board: 'board', town: 'district', beat: 'beat', player: 'player', tale: 'Tale card' };
const cardSearch = ref('');
const cardRows = computed(() =>
    Object.entries(cards.value).flatMap(([key, list]) =>
        list.map((card) => ({
            ...card,
            kindLabel: KIND_LABELS[card.kind] ?? card.kind,
            token: card.owner ? `{card:${card.owner}/${key}}` : `{card:${key}}`,
        })),
    ),
);
const cardMatches = computed(() => {
    const words = cardSearch.value.toLowerCase().split(/\s+/).filter(Boolean);
    const rows = words.length
        ? cardRows.value.filter((row) => words.every((w) => `${row.name} ${row.owner_name} ${row.kindLabel}`.toLowerCase().includes(w)))
        : cardRows.value;

    return rows.slice(0, 30);
});

const preview = computed(() =>
    renderMarkup(props.modelValue, {
        icons: icons.value,
        paths: paths.value,
        config: config.value,
        keywords: keywords.value,
        cards: cards.value,
        dreadRule: props.dreadRule,
        dreadAmount: props.dreadAmount,
        omenAmount: props.omenAmount,
        cardName: props.cardName,
        autoIcons: true,
    })
);

const showHelp = ref(false);

// Put new text in and select a range of it, the way every editor's own
// buttons do, so a second click or a keystroke carries straight on.
const replaceRange = (start, end, text, selectFrom, selectTo) => {
    const value = props.modelValue ?? '';
    emit('update:modelValue', value.slice(0, start) + text + value.slice(end));

    requestAnimationFrame(() => {
        textarea.value?.focus();
        textarea.value?.setSelectionRange(selectFrom, selectTo);
    });
};

// **bold** or *italic* around the selection, or around a placeholder word
// that is left selected for typing over. Wrapping something already wrapped
// takes the marks off again.
const wrap = (mark, placeholder) => {
    const el = textarea.value;
    const value = props.modelValue ?? '';
    const start = el?.selectionStart ?? value.length;
    const end = el?.selectionEnd ?? value.length;
    const selected = value.slice(start, end);
    const n = mark.length;

    if (value.slice(start - n, start) === mark && value.slice(end, end + n) === mark) {
        replaceRange(start - n, end + n, selected, start - n, end - n);
        return;
    }

    const body = selected || placeholder;
    replaceRange(start, end, `${mark}${body}${mark}`, start + n, start + n + body.length);
};

const BULLET = /^\s*[-*•]\s+/;
const NUMBER = /^\s*\d+[.)]\s+/;

// Every line the selection touches becomes a list item — or stops being one,
// when they all already are. Numbers are written in order.
const makeList = (numbered) => {
    const el = textarea.value;
    const value = props.modelValue ?? '';
    const start = el?.selectionStart ?? value.length;
    let end = el?.selectionEnd ?? value.length;
    // Whole lines selected end at the start of the next one; that line is not in it.
    if (end > start && value[end - 1] === '\n') end -= 1;
    const from = value.lastIndexOf('\n', start - 1) + 1;
    const endAt = value.indexOf('\n', end);
    const to = endAt === -1 ? value.length : endAt;
    const lines = value.slice(from, to).split('\n');
    const marker = numbered ? NUMBER : BULLET;

    const text = lines.every((line) => marker.test(line))
        ? lines.map((line) => line.replace(marker, '')).join('\n')
        : lines.map((line, i) => (numbered ? `${i + 1}. ` : '- ') + line.replace(BULLET, '').replace(NUMBER, '')).join('\n');

    replaceRange(from, to, text, from, from + text.length);
};

const onKeydown = (event) => {
    if (!(event.ctrlKey || event.metaKey) || event.altKey) return;
    const key = event.key.toLowerCase();

    if (key === 'b') {
        event.preventDefault();
        wrap('**', 'bold');
    } else if (key === 'i') {
        event.preventDefault();
        wrap('*', 'italic');
    }
};

// What each form looks like, drawn by the same renderer as the preview so the
// cheat sheet can never promise something the card does not do.
const firstKeyword = computed(() => Object.keys(keywords.value)[0] ?? null);
const firstConfig = computed(() => Object.keys(config.value)[0] ?? null);
const cheatSheet = computed(() =>
    [
        ['**bold**'],
        ['*italic*'],
        ['- one\n- two', 'a line starting "- " is a bullet'],
        ['1. first\n2. second', 'a line starting "1. " is numbered'],
        ['line one\nline two', 'a new line is a new line on the card'],
        ['{omen} {gold} {damage}', 'icons — the insert panel has them all'],
        ['2 omen', 'a number before omen, gold or damage gets its icon'],
        firstKeyword.value && [`{${firstKeyword.value}}`, 'a keyword from the keywords page'],
        firstKeyword.value && [`{${firstKeyword.value}:2}`, 'a keyword with its own number'],
        firstConfig.value && [`{config:${firstConfig.value}}`, 'a tunable number, kept up to date'],
        cardRows.value[0] && [cardRows.value[0].token, "another card's name — renaming that card updates this"],
        props.cardName !== null && ['{this}', "this card's name"],
        props.dreadRule !== null && ['{dreadRule}', "the scenario's Dread rule"],
    ]
        .filter(Boolean)
        .map(([typed, note]) => ({
            typed,
            note,
            html: renderMarkup(typed, {
                icons: icons.value,
                paths: paths.value,
                config: config.value,
                keywords: keywords.value,
                cards: cards.value,
                dreadRule: props.dreadRule,
                cardName: props.cardName || 'This card',
                autoIcons: true,
            }),
        }))
);

// Insert a token at the caret, so the designer never has to type the braces.
const insert = (token) => {
    const el = textarea.value;
    const value = props.modelValue ?? '';

    if (!el) {
        emit('update:modelValue', value + token);
        return;
    }

    const start = el.selectionStart ?? value.length;
    const end = el.selectionEnd ?? value.length;
    const next = value.slice(0, start) + token + value.slice(end);

    emit('update:modelValue', next);

    requestAnimationFrame(() => {
        el.focus();
        el.setSelectionRange(start + token.length, start + token.length);
    });
};
</script>

<template>
    <div>
        <div class="mb-1 flex items-baseline justify-between gap-3">
            <label v-if="label" class="text-sm font-medium text-stone-700">{{ label }}</label>
            <span v-else />
            <div class="flex items-center gap-1">
                <!-- Formatting: each button does what typing the marks by hand does. -->
                <button type="button" class="markup-tool font-bold" title="Bold — **text** (Ctrl+B)" @click="wrap('**', 'bold')">B</button>
                <button type="button" class="markup-tool italic" title="Italic — *text* (Ctrl+I)" @click="wrap('*', 'italic')">I</button>
                <button type="button" class="markup-tool" title='Bulleted list — lines starting "- "' @click="makeList(false)">•≡</button>
                <button type="button" class="markup-tool" title='Numbered list — lines starting "1. "' @click="makeList(true)">1.≡</button>
                <button
                    type="button"
                    class="markup-tool"
                    :class="{ 'is-active': showHelp }"
                    title="What can I type here?"
                    @click="showHelp = !showHelp"
                >?</button>
                <button type="button" class="ml-2 text-xs text-stone-500 underline decoration-dotted hover:text-stone-800" @click="showTokens = !showTokens">
                    {{ showTokens ? 'hide' : 'insert' }} icons, keywords &amp; numbers
                </button>
            </div>
        </div>

        <div v-if="showHelp" class="mb-1.5 rounded border border-stone-200 bg-stone-50 p-2 text-xs">
            <table class="w-full">
                <thead>
                    <tr class="text-left text-[11px] text-stone-500">
                        <th class="pb-1 pr-3 font-medium">Type</th>
                        <th class="pb-1 pr-3 font-medium">Card shows</th>
                        <th class="pb-1 font-medium" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in cheatSheet" :key="row.typed" class="border-t border-stone-200 align-top">
                        <td class="whitespace-pre py-1 pr-3 font-mono text-stone-700">{{ row.typed }}</td>
                        <td class="py-1 pr-3 text-stone-900" v-html="row.html" />
                        <td class="py-1 text-stone-500">{{ row.note }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="showTokens" class="mb-1.5 flex flex-wrap gap-1 rounded border border-stone-200 bg-stone-50 p-2">
            <button
                v-for="(glyph, name) in icons"
                :key="name"
                type="button"
                class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                @click="insert(`{${name}}`)"
            >
                <Icon :name="name" class="mr-0.5" /> {{ name }}
            </button>
            <template v-if="Object.keys(keywords).length">
                <span class="w-full pt-1 text-[11px] text-stone-500">Keywords — defined on the keywords page:</span>
                <button
                    v-for="(keyword, token) in keywords"
                    :key="token"
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="keyword.description ?? keyword.name"
                    @click="insert(`{${token}}`)"
                >
                    <Icon v-if="keyword.icon" :name="keyword.icon" class="mr-0.5" /> {{ keyword.name }}
                </button>
            </template>
            <template v-if="cardRows.length">
                <span class="w-full pt-1 text-[11px] text-stone-500">Another card — prints its name, and renaming that card in the editor updates this:</span>
                <input
                    v-model="cardSearch"
                    type="search"
                    placeholder="Find a card by name, owner or kind…"
                    class="w-full rounded border-stone-300 px-2 py-1 text-xs"
                />
                <button
                    v-for="row in cardMatches"
                    :key="row.token + row.kind"
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="row.token"
                    @click="insert(row.token)"
                >
                    {{ row.name }} <span class="text-stone-400">· {{ row.owner_name || 'no owner' }}, {{ row.kindLabel }}</span>
                </button>
                <span v-if="cardRows.length > cardMatches.length && cardMatches.length === 30" class="w-full text-[11px] text-stone-400">
                    Showing 30 — type to narrow it down.
                </span>
                <span v-else-if="!cardMatches.length" class="w-full text-[11px] text-stone-400">No card matches.</span>
            </template>
            <template v-if="cardName !== null">
                <span class="w-full pt-1 text-[11px] text-stone-500">This card — written as its name, so renaming the card renames it here:</span>
                <button
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="cardName || 'This card has no name yet.'"
                    @click="insert('{this}')"
                >
                    this
                </button>
            </template>
            <template v-if="dreadRule !== null || dreadAmount !== null || omenAmount !== null">
                <span class="w-full pt-1 text-[11px] text-stone-500">This scenario — written onto the card, so editing the scenario edits the card:</span>
                <button
                    v-if="dreadRule !== null"
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="dreadRule || 'This scenario has no Dread effect written yet.'"
                    @click="insert('{dreadRule}')"
                >
                    <Icon name="dread" class="mr-0.5" /> dreadRule
                </button>
                <button
                    v-if="dreadAmount !== null"
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="`The Dread this scenario starts on: ${dreadAmount || 'not set'}`"
                    @click="insert('{dreadAmount}')"
                >
                    <Icon name="dread" class="mr-0.5" /> dreadAmount
                </button>
                <button
                    v-if="omenAmount !== null"
                    type="button"
                    class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                    :title="`The omen this scenario's pool starts on: ${omenAmount || 'not set'}`"
                    @click="insert('{omenAmount}')"
                >
                    <Icon name="omen" class="mr-0.5" /> omenAmount
                </button>
            </template>
            <span class="w-full pt-1 text-[11px] text-stone-500">Tunable numbers — these update everywhere when the value changes:</span>
            <button
                v-for="(entry, key) in config"
                :key="key"
                type="button"
                class="rounded border border-stone-300 bg-white px-1.5 py-0.5 text-xs hover:border-stone-500"
                :title="entry.label"
                @click="insert(`{config:${key}}`)"
            >
                {{ key }}
            </button>
        </div>

        <textarea
            ref="textarea"
            :value="modelValue"
            :rows="rows"
            class="w-full rounded border-stone-300 font-mono text-sm shadow-sm focus:border-amber-600 focus:ring-amber-600"
            @input="emit('update:modelValue', $event.target.value)"
            @keydown="onKeydown"
        />

        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-stone-500">{{ hint }}</p>

        <!-- A div, not a <p>: the preview can hold a list now. -->
        <div v-if="modelValue" class="mt-1 rounded bg-stone-100 px-2 py-1 text-sm text-stone-800" v-html="preview" />
    </div>
</template>

<style scoped>
.markup-tool {
    min-width: 1.6rem;
    border: 1px solid #d6d3d1;
    border-radius: 0.25rem;
    background: #fff;
    padding: 0 0.3rem;
    font-size: 0.75rem;
    line-height: 1.25rem;
    color: #44403c;
}
.markup-tool:hover,
.markup-tool.is-active {
    border-color: #78716c;
}
.markup-tool.is-active {
    background: #f5f5f4;
}
</style>
