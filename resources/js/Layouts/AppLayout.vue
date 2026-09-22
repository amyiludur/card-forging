<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import Icon from '../Components/Icon.vue';

const page = usePage();
const printPool = computed(() => page.props.nav?.printPool ?? 0);
const flash = computed(() => page.props.flash ?? {});
const current = computed(() => page.url);

const isActive = (prefix) => current.value === prefix || current.value.startsWith(`${prefix}/`) || current.value.startsWith(`${prefix}?`);
const pathIs = (path) => current.value.split('?')[0] === path;
// A library item's page and everything under it (edit, deck, play), but
// never a sibling whose slug happens to start the same way.
const under = (href) => current.value === href || current.value.startsWith(`${href}/`) || current.value.startsWith(`${href}?`);

// A list longer than this shows its first few and folds the rest behind
// "Show more", so twenty-odd characters do not push everything below them
// off the screen. The item being looked at is always shown.
const LIMIT = 8;

const libraries = computed(() => [
    {
        key: 'scenarios',
        title: 'Scenarios',
        all: '/scenarios',
        create: '/scenarios/create',
        noun: 'scenario',
        items: (page.props.nav?.scenarios ?? []).map((s) => ({ href: `/scenarios/${s.slug}`, label: s.name, icon: 'scenario' })),
    },
    {
        key: 'modules',
        title: 'Modules',
        all: '/modules',
        create: '/modules/create',
        noun: 'module',
        items: (page.props.nav?.modules ?? []).map((m) => ({ href: `/modules/${m.slug}`, label: m.name, icon: 'module' })),
    },
    {
        key: 'characters',
        title: 'Characters',
        all: '/characters',
        create: '/characters/create',
        noun: 'character',
        items: (page.props.nav?.characters ?? []).map((c) => ({ href: `/characters/${c.slug}`, label: c.name, icon: 'character' })),
    },
    {
        key: 'domains',
        title: 'Domains',
        all: '/domains',
        create: '/domains/create',
        noun: 'domain',
        items: (page.props.nav?.domains ?? []).map((d) => ({ href: `/domains/${d.slug}`, label: d.name, icon: d.is_neutral ? 'neutral' : 'domain' })),
    },
]);

const rulesActive = computed(() => isActive('/rules')
    && !current.value.startsWith('/rules/config')
    && !current.value.startsWith('/rules/keywords')
    && !current.value.startsWith('/rules/card-types'));

const fixed = computed(() => [
    {
        key: 'main',
        items: [
            { href: '/', label: 'Overview', icon: 'overview', active: current.value === '/' },
            { href: '/cards', label: 'All cards', icon: 'cards', active: isActive('/cards') },
            { href: '/decks', label: 'Deck builder', icon: 'zone-deck', active: isActive('/decks') },
            { href: '/print/pool', label: 'Print pool', icon: 'print', active: isActive('/print/pool'), badge: printPool.value },
        ],
    },
    {
        key: 'rules',
        title: 'Rules',
        items: [
            { href: '/rules', label: 'Rulebook', icon: 'rulebook', active: rulesActive.value },
            { href: '/rules/config', label: 'Tunable numbers', icon: 'config', active: current.value.startsWith('/rules/config') },
            { href: '/rules/keywords', label: 'Keywords', icon: 'story', active: current.value.startsWith('/rules/keywords') },
            { href: '/rules/card-types', label: 'Card types', icon: 'cards', active: current.value.startsWith('/rules/card-types') },
            { href: '/print/rules', label: 'Print the rulebook', icon: 'print', active: current.value.startsWith('/print/rules') },
        ],
    },
    {
        key: 'source',
        title: 'Source',
        items: [
            { href: '/design', label: 'Design folder', icon: 'folder', active: isActive('/design') },
        ],
    },
]);

// Which sections are folded and which have been opened past the limit.
// Remembered per browser, like the playtest table: a convenience, not a fact
// about the game, so a blocked localStorage just means everything starts open.
const STORAGE_KEY = 'card-forge.sidebar';
const readState = () => {
    try {
        const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) ?? '{}');
        return { collapsed: saved.collapsed ?? {}, expanded: saved.expanded ?? {} };
    } catch {
        return { collapsed: {}, expanded: {} };
    }
};
const state = ref(readState());
watch(state, (value) => {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(value));
    } catch {
        // Private window or blocked storage: the sidebar still works, it just forgets.
    }
}, { deep: true });

const toggleCollapsed = (key) => { state.value.collapsed[key] = !state.value.collapsed[key]; };
const toggleExpanded = (key) => { state.value.expanded[key] = !state.value.expanded[key]; };

const sectionActive = (section) => section.items.some((item) => under(item.href)) || (section.all && pathIs(section.all));

const visibleItems = (section) => {
    if (state.value.expanded[section.key] || section.items.length <= LIMIT) {
        return section.items;
    }

    const shown = section.items.slice(0, LIMIT);
    const active = section.items.slice(LIMIT).find((item) => under(item.href));

    return active ? [...shown, active] : shown;
};

// Jump to: one box that filters every list at once, so finding the
// Berserker among thirty characters is typing "ber" and pressing Enter.
const query = ref('');
const search = ref(null);
const needle = computed(() => query.value.trim().toLowerCase());
const matches = (label) => label.toLowerCase().includes(needle.value);

const results = computed(() => {
    if (!needle.value) {
        return [];
    }

    return [
        ...fixed.value.map((section) => ({ key: section.key, title: section.title, items: section.items.filter((item) => matches(item.label)) })),
        ...libraries.value.map((section) => ({ key: section.key, title: section.title, items: section.items.filter((item) => matches(item.label)) })),
    ].filter((section) => section.items.length);
});
const firstResult = computed(() => results.value[0]?.items[0] ?? null);

const jump = () => {
    if (firstResult.value) {
        router.visit(firstResult.value.href);
    }
};

// Clear the box once a page is picked, so the full sidebar comes back.
watch(current, () => { query.value = ''; });

// "/" anywhere outside a field goes to the box, the way most apps do it.
const onKey = (event) => {
    if (event.key !== '/' || event.metaKey || event.ctrlKey || event.altKey) {
        return;
    }
    const target = event.target;
    if (target instanceof HTMLElement && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))) {
        return;
    }
    if (!search.value || search.value.offsetParent === null) {
        return;
    }
    event.preventDefault();
    search.value.focus();
};
onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>

<template>
    <div class="flex min-h-screen">
        <aside class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col border-r border-stone-300 bg-stone-900 text-stone-300 lg:flex">
            <Link href="/" class="flex items-center gap-2 px-5 pb-3 pt-5 text-stone-50 hover:text-white">
                <Icon name="omen" class="text-lg" />
                <span class="font-serif text-lg font-semibold tracking-wide">Card Forge</span>
            </Link>

            <div class="px-3 pb-3">
                <label class="flex items-center gap-2 rounded-md border border-stone-700 bg-stone-800 px-2.5 py-1.5 text-sm focus-within:border-stone-500">
                    <Icon name="search" class="flex-none text-stone-500" />
                    <input
                        ref="search"
                        v-model="query"
                        type="search"
                        placeholder="Jump to…"
                        aria-label="Jump to a page"
                        class="nav-search min-w-0 flex-1 border-0 bg-transparent p-0 text-sm text-stone-100 placeholder-stone-500 focus:outline-none focus:ring-0"
                        @keydown.enter.prevent="jump"
                        @keydown.esc="query = ''; $event.target.blur()"
                    >
                    <kbd v-if="!query" class="rounded border border-stone-700 px-1 text-[10px] leading-4 text-stone-500">/</kbd>
                </label>
            </div>

            <nav class="nav-scroll min-h-0 flex-1 overflow-y-auto px-3 pb-6 text-sm">
                <!-- Filtering: every match from every list, nothing folded. -->
                <div v-if="needle" class="space-y-5">
                    <div v-for="section in results" :key="section.key">
                        <p v-if="section.title" class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-widest text-stone-500">{{ section.title }}</p>
                        <div class="space-y-0.5">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="nav-link"
                                :class="{ 'nav-link-active': item === firstResult }"
                            >
                                <Icon :name="item.icon" /> <span class="truncate">{{ item.label }}</span>
                                <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
                            </Link>
                        </div>
                    </div>
                    <p v-if="!results.length" class="px-3 text-stone-500">Nothing called “{{ query.trim() }}”.</p>
                </div>

                <div v-else class="space-y-5">
                    <div class="space-y-0.5">
                        <Link
                            v-for="item in fixed[0].items"
                            :key="item.href"
                            :href="item.href"
                            class="nav-link"
                            :class="{ 'nav-link-active': item.active }"
                        >
                            <Icon :name="item.icon" /> {{ item.label }}
                            <span v-if="item.badge" class="nav-badge">{{ item.badge }}</span>
                        </Link>
                    </div>

                    <div v-for="section in libraries" :key="section.key">
                        <div class="flex items-center">
                            <button
                                type="button"
                                class="nav-heading"
                                :class="{ 'nav-heading-active': state.collapsed[section.key] && sectionActive(section) }"
                                :aria-expanded="!state.collapsed[section.key]"
                                @click="toggleCollapsed(section.key)"
                            >
                                <Icon :name="state.collapsed[section.key] ? 'chevron-right' : 'chevron-down'" class="nav-chevron" />
                                {{ section.title }}
                                <span class="font-normal tracking-normal text-stone-600">{{ section.items.length }}</span>
                            </button>
                            <Link
                                :href="section.create"
                                class="nav-add"
                                :title="`New ${section.noun}`"
                                :aria-label="`New ${section.noun}`"
                            >
                                <Icon name="add" />
                            </Link>
                        </div>

                        <div v-if="!state.collapsed[section.key]" class="space-y-0.5">
                            <Link
                                v-for="item in visibleItems(section)"
                                :key="item.href"
                                :href="item.href"
                                class="nav-link"
                                :class="{ 'nav-link-active': under(item.href) }"
                                :title="item.label"
                            >
                                <Icon :name="item.icon" /> <span class="truncate">{{ item.label }}</span>
                            </Link>
                            <button
                                v-if="section.items.length > LIMIT"
                                type="button"
                                class="nav-link nav-link-quiet w-full"
                                @click="toggleExpanded(section.key)"
                            >
                                <Icon :name="state.expanded[section.key] ? 'chevron-down' : 'chevron-right'" />
                                {{ state.expanded[section.key] ? 'Show fewer' : `Show ${section.items.length - LIMIT} more` }}
                            </button>
                            <Link
                                :href="section.all"
                                class="nav-link nav-link-quiet"
                                :class="{ 'nav-link-active': pathIs(section.all) }"
                            >
                                <Icon :name="section.noun" />
                                All {{ section.title.toLowerCase() }}
                            </Link>
                            <Link v-if="!section.items.length" :href="section.create" class="nav-link nav-link-quiet">
                                <Icon name="add" /> New {{ section.noun }}
                            </Link>
                        </div>
                    </div>

                    <div v-for="section in fixed.slice(1)" :key="section.key">
                        <button
                            type="button"
                            class="nav-heading"
                            :class="{ 'nav-heading-active': state.collapsed[section.key] && section.items.some((item) => item.active) }"
                            :aria-expanded="!state.collapsed[section.key]"
                            @click="toggleCollapsed(section.key)"
                        >
                            <Icon :name="state.collapsed[section.key] ? 'chevron-right' : 'chevron-down'" class="nav-chevron" />
                            {{ section.title }}
                        </button>
                        <div v-if="!state.collapsed[section.key]" class="space-y-0.5">
                            <Link
                                v-for="item in section.items"
                                :key="item.href"
                                :href="item.href"
                                class="nav-link"
                                :class="{ 'nav-link-active': item.active }"
                            >
                                <Icon :name="item.icon" /> {{ item.label }}
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>

            <p class="border-t border-stone-800 px-5 py-4 text-[11px] leading-relaxed text-stone-500">
                Edits live in the database.
                <Link href="/design" class="text-stone-400 underline decoration-dotted hover:text-stone-200">Export them</Link>
                to write <code class="text-stone-400">design/</code> back out and commit it.
            </p>
        </aside>

        <main class="min-w-0 flex-1">
            <div
                v-if="flash.success || flash.error"
                class="flex items-center gap-2 border-b px-6 py-2.5 text-sm"
                :class="flash.error ? 'border-red-200 bg-red-50 text-red-800' : 'border-emerald-200 bg-emerald-50 text-emerald-900'"
            >
                <Icon v-if="flash.error" name="warning" />
                {{ flash.error || flash.success }}
            </div>

            <slot />
        </main>
    </div>
</template>

<style>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 0.375rem;
    padding: 0.375rem 0.75rem;
    color: rgb(214 211 209);
    text-align: left;
}
/* Dimmer than the label, so the text still leads. */
.nav-link .icon {
    flex: none;
    opacity: 0.55;
}
.nav-link-active .icon,
.nav-link:hover .icon {
    opacity: 1;
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
/* "Show more", "All characters", "New scenario": there, but not an item. */
.nav-link-quiet {
    color: rgb(120 113 108);
}
.nav-heading {
    display: flex;
    flex: 1;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem 0.25rem 0.25rem;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: rgb(120 113 108);
}
.nav-heading:hover {
    color: rgb(214 211 209);
}
/* A folded section holding the page you are on says so. */
.nav-heading-active {
    color: rgb(253 230 138);
}
.nav-chevron {
    flex: none;
    font-size: 9px;
    width: 0.75rem;
}
.nav-add {
    flex: none;
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
    font-size: 11px;
    color: rgb(120 113 108);
}
.nav-add:hover {
    background-color: rgb(41 37 36);
    color: rgb(250 250 249);
}
.nav-badge {
    margin-left: auto;
    border-radius: 9999px;
    background-color: rgb(68 64 60);
    padding: 0 0.375rem;
    font-size: 11px;
    font-weight: 400;
    color: rgb(231 229 228);
}
.nav-search::-webkit-search-cancel-button {
    filter: invert(0.6);
}
/* A thin dark scrollbar, so a long sidebar does not grow a bright gutter. */
.nav-scroll {
    scrollbar-width: thin;
    scrollbar-color: rgb(68 64 60) transparent;
}
</style>
