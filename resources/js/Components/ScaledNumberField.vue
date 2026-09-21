<script setup>
import { computed, nextTick, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
import { equationError, scaledAt, scaledMarkup } from '../playerScaled';
import Icon from './Icon.vue';

/**
 * One of the designer's numbers, with the toggle that turns it into an equation
 * counting the players.
 *
 * Off is a plain number field, exactly as it was before any of this existed.
 * On, the number is kept but set aside — it is what comes back when the toggle
 * goes off again — and the equation beside it is the value.
 *
 * The preview works the equation out at a table size the designer picks, which
 * is the only thing here that needs a player count: the card itself prints the
 * equation, because a printed card cannot know who is at the table.
 */
const props = defineProps({
    value: { type: [Number, String], default: 0 },
    equation: { type: String, default: null },
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    equationError: { type: String, default: '' },
    min: { type: [Number, String], default: null },
    max: { type: [Number, String], default: null },
    // The width of the plain number field, so this drops into forms that lay
    // their fields out in a grid.
    inputClass: { type: String, default: 'field' },
});

const emit = defineEmits(['update:value', 'update:equation']);

const page = usePage();
const field = ref(null);

const scaled = computed(() => (props.equation ?? '') !== '');

/** A table to try the equation against. Three is a guess, and it is only a preview. */
const players = ref(3);

const problem = computed(() => props.equationError || (scaled.value ? equationError(props.equation) : null));

const at = computed(() => scaledAt(props.value, props.equation, players.value));

const preview = computed(() =>
    renderMarkup(scaledMarkup(props.value, props.equation), {
        icons: page.props.markup?.icons ?? {},
        paths: page.props.markup?.paths ?? {},
    })
);

/**
 * Turning it on seeds the equation from the number that is already there, so
 * the designer edits what they had rather than an empty box. Turning it off
 * clears the equation and leaves the number alone — the number was never
 * touched, so it is still whatever it was.
 */
const toggle = async () => {
    const turningOn = !scaled.value;
    // An empty field seeds from 0 rather than from nothing: " + 1perPlayer" is
    // not an equation, and the designer would be shown an error they did not
    // make before typing a character.
    const seed = props.value === '' || props.value === null || props.value === undefined ? 0 : props.value;

    emit('update:equation', turningOn ? `${seed} + 1perPlayer` : null);

    if (turningOn) {
        await nextTick();
        field.value?.focus();
        field.value?.select();
    }
};
</script>

<template>
    <div>
        <div class="flex items-baseline justify-between gap-2">
            <label v-if="label" class="field-label">{{ label }}</label>
            <button
                type="button"
                class="text-[11px] text-stone-500 underline decoration-dotted hover:text-stone-900"
                :title="scaled ? 'Go back to a plain number' : 'Write this as an equation counting the players'"
                @click="toggle"
            >
                <Icon name="perPlayer" /> {{ scaled ? 'plain number' : 'per player' }}
            </button>
        </div>

        <input
            v-if="!scaled"
            :value="value"
            type="number"
            :min="min ?? undefined"
            :max="max ?? undefined"
            :class="inputClass"
            @input="emit('update:value', $event.target.value === '' ? '' : Number($event.target.value))"
        >

        <template v-else>
            <input
                ref="field"
                :value="equation"
                type="text"
                class="field font-mono"
                placeholder="1 + 1perPlayer"
                @input="emit('update:equation', $event.target.value)"
            >

            <p v-if="problem" class="field-error">{{ problem }}</p>
            <p v-else class="mt-1 flex flex-wrap items-center gap-1 text-xs text-stone-600">
                <span v-html="preview" />
                <span>=</span>
                <strong class="tabular-nums">{{ at }}</strong>
                <span>at</span>
                <input
                    v-model.number="players"
                    type="number"
                    min="1"
                    max="9"
                    class="w-12 rounded border border-stone-300 px-1 py-0 text-xs tabular-nums"
                    aria-label="Players to preview this equation at"
                >
                <span>{{ players === 1 ? 'player' : 'players' }}</span>
            </p>
            <p class="mt-1 text-[11px] text-stone-500">
                The card prints the equation, not a number: it cannot know who is at the table.
                Whole numbers, <code>perPlayer</code>, <code>+ - *</code> and brackets.
            </p>
        </template>

        <p v-if="error" class="field-error">{{ error }}</p>
        <p v-else-if="hint && !scaled" class="field-hint">{{ hint }}</p>
    </div>
</template>
