<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../markup';
import { scaledMarkup } from '../playerScaled';

/**
 * One of the designer's numbers, shown the way the card shows it: the plain
 * number, or the equation as typed with {perPlayer} drawn as the icon.
 *
 * Never worked out into a number. Nothing outside the playtest table knows how
 * many people are at the table, which is the same reason the printed card shows
 * the equation rather than a figure. Mirrors CardPresenter::scaled() and
 * renderScaled() in CardPreview.vue.
 */
const props = defineProps({
    value: { type: [Number, String], default: null },
    equation: { type: String, default: null },
});

const page = usePage();

const html = computed(() =>
    renderMarkup(scaledMarkup(props.value, props.equation), {
        icons: page.props.markup?.icons ?? {},
        paths: page.props.markup?.paths ?? {},
    })
);
</script>

<template>
    <span v-html="html" />
</template>
