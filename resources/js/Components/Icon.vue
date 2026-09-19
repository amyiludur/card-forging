<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * One icon, drawn from the path data App\Support\Icons shares through Inertia.
 * The server draws the same paths on the print sheet, so the editor and the
 * printed card cannot drift apart.
 */
const props = defineProps({
    name: { type: String, required: true },
    // Sized off the surrounding text so an icon sits in a line like a letter.
    title: { type: String, default: null },
});

const page = usePage();
const icon = computed(() => page.props.markup?.paths?.[props.name] ?? null);
</script>

<template>
    <svg
        v-if="icon"
        class="icon"
        :viewBox="`0 0 ${icon.w} ${icon.h}`"
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        :aria-hidden="title ? undefined : 'true'"
        :role="title ? 'img' : undefined"
        focusable="false"
    >
        <title v-if="title">{{ title }}</title>
        <path :d="icon.d" />
    </svg>
</template>
