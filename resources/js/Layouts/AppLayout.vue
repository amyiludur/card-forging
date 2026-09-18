<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const page = usePage();
const scenarios = computed(() => page.props.nav?.scenarios ?? []);
const modules = computed(() => page.props.nav?.modules ?? []);
const flash = computed(() => page.props.flash ?? {});
const current = computed(() => page.url);

const isActive = (prefix) => current.value === prefix || current.value.startsWith(`${prefix}/`) || current.value.startsWith(`${prefix}?`);
</script>

<template>
    <div class="flex min-h-full">
        <aside class="hidden w-60 shrink-0 flex-col border-r border-stone-300 bg-stone-900 text-stone-300 lg:flex">
            <Link href="/" class="flex items-baseline gap-2 px-5 py-5 text-stone-50 hover:text-white">
                <span class="text-lg">◆</span>
                <span class="font-serif text-lg font-semibold tracking-wide">Card Forge</span>
            </Link>

            <nav class="flex-1 space-y-6 px-3 pb-6 text-sm">
                <div class="space-y-0.5">
                    <Link href="/" class="nav-link" :class="{ 'nav-link-active': current === '/' }">Overview</Link>
                    <Link :href="'/cards'" class="nav-link" :class="{ 'nav-link-active': isActive('/cards') }">All cards</Link>
                </div>

                <div>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-widest text-stone-500">Scenarios</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="scenario in scenarios"
                            :key="scenario.slug"
                            :href="`/scenarios/${scenario.slug}`"
                            class="nav-link"
                            :class="{ 'nav-link-active': current.startsWith(`/scenarios/${scenario.slug}`) }"
                        >
                            {{ scenario.name }}
                        </Link>
                        <Link href="/scenarios/create" class="nav-link text-stone-500">+ New scenario</Link>
                    </div>
                </div>

                <div>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-widest text-stone-500">Modules</p>
                    <div class="space-y-0.5">
                        <Link
                            v-for="module in modules"
                            :key="module.slug"
                            :href="`/modules/${module.slug}`"
                            class="nav-link"
                            :class="{ 'nav-link-active': current.startsWith(`/modules/${module.slug}`) }"
                        >
                            {{ module.name }}
                        </Link>
                        <Link href="/modules/create" class="nav-link text-stone-500">+ New module</Link>
                    </div>
                </div>

                <div>
                    <p class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-widest text-stone-500">Rules</p>
                    <div class="space-y-0.5">
                        <Link href="/rules" class="nav-link" :class="{ 'nav-link-active': isActive('/rules') && !current.startsWith('/rules/config') }">Rulebook</Link>
                        <Link href="/rules/config" class="nav-link" :class="{ 'nav-link-active': current.startsWith('/rules/config') }">Tunable numbers</Link>
                    </div>
                </div>
            </nav>

            <p class="border-t border-stone-800 px-5 py-4 text-[11px] leading-relaxed text-stone-500">
                Edits live in the database. Run
                <code class="text-stone-400">design:export</code>
                to write them back to <code class="text-stone-400">design/</code> and commit.
            </p>
        </aside>

        <main class="min-w-0 flex-1">
            <div
                v-if="flash.success || flash.error"
                class="border-b px-6 py-2.5 text-sm"
                :class="flash.error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
            >
                {{ flash.error || flash.success }}
            </div>

            <slot />
        </main>
    </div>
</template>

<style>
.nav-link {
    display: block;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    color: rgb(214 211 209);
}
.nav-link:hover {
    background-color: rgb(41 37 36);
    color: rgb(250 250 249);
}
.nav-link-active {
    background-color: rgb(41 37 36);
    color: rgb(253 230 138);
    font-weight: 600;
}
</style>
