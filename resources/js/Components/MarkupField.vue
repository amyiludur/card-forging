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

const preview = computed(() =>
    renderMarkup(props.modelValue, {
        icons: icons.value,
        paths: paths.value,
        config: config.value,
        keywords: keywords.value,
        dreadRule: props.dreadRule,
        dreadAmount: props.dreadAmount,
        cardName: props.cardName,
        autoIcons: true,
    })
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
            <button type="button" class="text-xs text-stone-500 underline decoration-dotted hover:text-stone-800" @click="showTokens = !showTokens">
                {{ showTokens ? 'hide' : 'insert' }} icons, keywords &amp; numbers
            </button>
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
            <template v-if="dreadRule !== null || dreadAmount !== null">
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
        />

        <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-stone-500">{{ hint }}</p>

        <p v-if="modelValue" class="mt-1 rounded bg-stone-100 px-2 py-1 text-sm text-stone-800" v-html="preview" />
    </div>
</template>
