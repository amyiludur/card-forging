<script setup>
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import ScaledNumberField from '../../Components/ScaledNumberField.vue';

const props = defineProps({
    groups: { type: Object, default: () => ({}) },
    icons: { type: Object, default: () => ({}) },
});

const groupLabels = {
    omen: 'Omen',
    gold: 'Gold',
    dread: 'Dread',
    cards: 'Cards and characters',
    players: 'Player decks',
    general: 'Other',
};

// A range value is stored as an array; the input edits it as "0, 2".
const toInput = (value, type) => {
    if (type === 'range') return Array.isArray(value) ? value.join(', ') : '';
    // A map keeps its keys, so it is edited as one field per key.
    if (type === 'map') return { ...(value ?? {}) };
    if (type === 'bool') return Boolean(value);
    return value ?? '';
};

const form = useForm({
    values: Object.values(props.groups)
        .flat()
        .map((entry) => ({
            id: entry.id,
            key: entry.key,
            label: entry.label,
            description: entry.description,
            value_type: entry.value_type,
            value: toInput(entry.value, entry.value_type),
            equation_error: entry.equation_error,
            is_placeholder: entry.is_placeholder,
        })),
});

const rowsFor = (group) => form.values.filter((row) => group.some((entry) => entry.id === row.id));

/**
 * A tunable number switched between a plain number and an equation.
 *
 * One value either way, the way the design folder holds it: the row's type
 * follows the value rather than the other way round, so this and
 * RulesConfig::typeFor() on the server cannot disagree. Turning an equation off
 * leaves 0 rather than the number that was there before it, because — unlike a
 * scenario or a character — there is no second column here keeping it.
 */
const setEquation = (row, equation) => {
    if (equation === null || equation === '') {
        row.value_type = 'int';
        row.value = 0;
        row.equation_error = null;

        return;
    }

    row.value_type = 'equation';
    row.value = equation;
    row.equation_error = null;
};

const copied = ref(null);

// Built here rather than inline: a `}}` inside a mustache ends the interpolation.
const configToken = (key) => `{config:${key}}`;
const iconToken = (name) => `{${name}}`;

const copyToken = (key) => {
    navigator.clipboard?.writeText(`{config:${key}}`);
    copied.value = key;
    setTimeout(() => (copied.value = null), 1200);
};
</script>

<template>
    <Head title="Tunable numbers" />

    <PageHeader
        title="Tunable numbers"
        subtitle="Every number the rules lean on, in one place. Rules text and cards reference these, so changing one here changes it everywhere."
    >
        <template #actions>
            <button type="submit" form="config-form" class="btn-primary" :disabled="form.processing">
                <Icon name="edit" /> Save all
            </button>
        </template>
    </PageHeader>

    <form id="config-form" class="max-w-4xl space-y-8 px-6 py-6" @submit.prevent="form.put('/rules/config', { preserveScroll: true })">
        <section v-for="(entries, group) in groups" :key="group">
            <h2 class="mb-3 font-serif text-lg font-semibold text-stone-900">{{ groupLabels[group] ?? group }}</h2>

            <div class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white">
                <div v-for="row in rowsFor(entries)" :key="row.id" class="flex flex-wrap items-start gap-4 p-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="font-medium text-stone-900">{{ row.label }}</span>
                            <button
                                type="button"
                                class="font-mono text-[11px] text-stone-500 underline decoration-dotted hover:text-stone-900"
                                :title="`Copy {config:${row.key}} for use in rules or card text`"
                                @click="copyToken(row.key)"
                            >
                                {{ copied === row.key ? 'copied!' : configToken(row.key) }}
                            </button>
                        </div>
                        <p v-if="row.description" class="mt-0.5 text-sm text-stone-600">{{ row.description }}</p>
                    </div>

                    <!-- An equation and its "= 4 at 3 players" preview need
                         more room than a number field does. -->
                    <div :class="row.value_type === 'equation' ? 'w-full sm:w-72' : 'w-44'">
                        <label v-if="row.value_type === 'bool'" class="flex items-center gap-2 text-sm">
                            <input v-model="row.value" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                            {{ row.value ? 'yes' : 'no' }}
                        </label>
                        <input
                            v-else-if="row.value_type === 'range'"
                            v-model="row.value"
                            type="text"
                            class="field font-mono"
                            placeholder="0, 2"
                        >
                        <div v-else-if="row.value_type === 'map'" class="space-y-1">
                            <label v-for="(entry, key) in row.value" :key="key" class="flex items-center gap-2 text-sm">
                                <span class="w-20 shrink-0 text-stone-600">{{ key }}</span>
                                <input v-model="row.value[key]" type="number" class="field font-mono">
                            </label>
                        </div>
                        <!-- A number, or an equation counting the players.
                             Which of the two it is comes from the value itself
                             (RulesConfig::typeFor()), so the toggle here writes
                             the value and the server reads the type back off
                             it. -->
                        <ScaledNumberField
                            v-else-if="row.value_type === 'int' || row.value_type === 'equation'"
                            :value="row.value_type === 'equation' ? 0 : row.value"
                            :equation="row.value_type === 'equation' ? String(row.value ?? '') : null"
                            :equation-error="row.equation_error ?? ''"
                            input-class="field font-mono"
                            @update:value="row.value = $event"
                            @update:equation="setEquation(row, $event)"
                        />
                        <input v-else v-model="row.value" type="text" class="field">
                    </div>

                    <label class="flex w-28 items-center gap-1.5 text-xs text-stone-600" title="Placeholder values are flagged wherever they appear">
                        <input v-model="row.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        placeholder
                    </label>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="config" /> Markup you can type
            </h2>
            <p class="text-sm text-stone-700">
                In card text and rules text:
                <code v-for="(glyph, name) in icons" :key="name" class="mr-2 rounded bg-stone-100 px-1">
                    {{ iconToken(name) }} → <Icon :name="name" />
                </code>
            </p>
            <p class="mt-1 text-sm text-stone-700">
                And <code class="rounded bg-stone-100 px-1">{{ configToken('startingOmen') }}</code> to print a number from this page.
                Placeholder values render underlined, so a draft always looks like a draft.
            </p>
        </section>
    </form>
</template>
