<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

/*
 * The Hireling line under a pile's types: how many there are, the term they
 * serve and what sacrificing one prevents. One implementation, used by the
 * character page, the domain page and the deck builder.
 *
 * The in-play limit is printed beside the count because that is what the count
 * wants reading against, but it is a limit on the table and not on the deck: a
 * deck of eight Hirelings is not wrong and nothing here says it is.
 */
const props = defineProps({
    hirelings: { type: Object, default: null },
});

const total = computed(() => props.hirelings?.total ?? 0);

// A curve as a span: "3" when every card agrees, "2–4" when they do not.
const span = (curve) => {
    if (!curve?.length) {
        return null;
    }

    const first = curve[0].value;
    const last = curve[curve.length - 1].value;

    return first === last ? `${first}` : `${first}–${last}`;
};

const uses = computed(() => span(props.hirelings?.uses));
const sacrifice = computed(() => span(props.hirelings?.sacrifice));
const maxInPlay = computed(() => props.hirelings?.max_in_play ?? null);
</script>

<template>
    <p v-if="total" class="mt-2 flex flex-wrap items-center gap-x-1 text-sm text-stone-700">
        <Icon name="hireling" class="text-stone-500" />
        <span>{{ total }} {{ total === 1 ? 'Hireling' : 'Hirelings' }}</span>
        <span v-if="uses">· uses {{ uses }}</span>
        <span v-if="sacrifice">· sacrifice {{ sacrifice }}</span>
        <span v-if="maxInPlay !== null" class="text-stone-500">· at most {{ maxInPlay }} in play at once</span>
    </p>
</template>
