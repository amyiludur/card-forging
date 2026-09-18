<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <header class="border-b border-stone-300 bg-stone-50 px-6 py-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="font-serif text-2xl font-semibold text-stone-900">{{ title }}</h1>
                <p v-if="subtitle" class="mt-0.5 text-sm text-stone-600">{{ subtitle }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <slot name="actions" />
            </div>
        </div>
        <slot />
    </header>
</template>
