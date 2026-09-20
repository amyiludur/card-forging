<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import { ink, onPaper } from '../../colour';

const props = defineProps({
    types: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
    scenarios: { type: Array, default: () => [] },
});

const page = usePage();

// Each row edits in place; only the row being saved is sent, so one taken slug
// never blocks the rest of the library.
const rows = reactive(props.types.map((type) => ({ ...type })));
const saving = ref(null);

const blank = () => ({ name: '', slug: '', description: '', colour: '#7f1d1d', icon: null, sort: props.types.length });

const adding = useForm(blank());

const errorsFor = (id) => (saving.value === id ? page.props.errors ?? {} : {});

const shared = computed(() => rows.filter((row) => !row.scenario_id));
const owned = computed(() => rows.filter((row) => row.scenario_id));

// The head band prints the colour as picked; the type line prints this, which
// is the same colour taken dark enough to read on the card's cream body.
const labelColour = (colour) => (colour ? onPaper(colour) : '#78716c');

// The swatch is the head band the card prints, ink and all, so a pale type
// reads here exactly as it will on the card.
const headSwatch = (colour) => ({ background: colour || '#1c1917', color: ink(colour || '#1c1917') });

const save = (row) => {
    saving.value = row.id;
    router.put(`/rules/card-types/${row.id}`, row, { preserveScroll: true });
};

const add = () => {
    saving.value = 'new';
    adding.post('/rules/card-types', {
        preserveScroll: true,
        onSuccess: () => adding.defaults(blank()).reset(),
    });
};

const remove = (row) => {
    const used = row.faces_count
        ? ` ${row.faces_count} card face${row.faces_count === 1 ? '' : 's'} will have no type.`
        : '';

    if (!confirm(`Delete ${row.name}?${used}`)) return;

    router.delete(`/rules/card-types/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Card types" />

    <PageHeader
        title="Card types"
        subtitle="What a card does in one word, and the colour it prints in. A shared type is offered on every card; a scenario's own is offered only on its cards, and is added from the scenario's own page."
    />

    <div class="max-w-4xl space-y-6 px-6 py-6">
        <section class="rounded-lg border border-stone-300 bg-white">
            <h2 class="border-b border-stone-200 px-4 py-3 font-serif text-base font-semibold">
                <Icon name="add" /> New shared type
            </h2>

            <form class="space-y-3 p-4" @submit.prevent="add">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="w-48">
                        <span class="field-micro">Name</span>
                        <input v-model="adding.name" type="text" class="field" placeholder="Attack">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Slug</span>
                        <input v-model="adding.slug" type="text" class="field font-mono" :placeholder="adding.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') || 'attack'">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Icon</span>
                        <select v-model="adding.icon" class="field">
                            <option :value="null">off the slug</option>
                            <option v-for="name in icons" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </label>
                    <label class="w-28">
                        <span class="field-micro">Colour</span>
                        <input v-model="adding.colour" type="color" class="h-9 w-full cursor-pointer rounded border border-stone-300 bg-white p-1">
                    </label>
                </div>

                <label class="block">
                    <span class="field-micro">What it does</span>
                    <textarea v-model="adding.description" rows="2" class="field" placeholder="Deals damage to players." />
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <span class="flex items-center gap-2 text-xs text-stone-500">
                        head
                        <span class="rounded px-2 py-1 font-serif text-xs font-semibold" :style="headSwatch(adding.colour)">
                            {{ adding.name || 'Card name' }}
                        </span>
                        type line
                        <span class="text-[11px] font-bold uppercase tracking-widest" :style="{ color: labelColour(adding.colour) }">
                            <Icon v-if="adding.icon" :name="adding.icon" /> {{ adding.name || 'Attack' }}
                        </span>
                    </span>
                    <button type="submit" class="btn-primary ml-auto" :disabled="adding.processing">
                        <Icon name="add" /> Add type
                    </button>
                </div>

                <p v-for="(message, field) in (saving === 'new' ? adding.errors : {})" :key="field" class="field-error">{{ message }}</p>
            </form>
        </section>

        <section v-if="shared.length" class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white">
            <h2 class="bg-stone-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-widest text-stone-500">
                Shared — offered on every card
            </h2>

            <div v-for="row in shared" :key="row.id" class="space-y-3 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="w-48">
                        <span class="field-micro">Name</span>
                        <input v-model="row.name" type="text" class="field">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Slug</span>
                        <input v-model="row.slug" type="text" class="field font-mono">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Icon</span>
                        <select v-model="row.icon" class="field">
                            <option :value="null">off the slug</option>
                            <option v-for="name in icons" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </label>
                    <label class="w-28">
                        <span class="field-micro">Colour</span>
                        <input v-model="row.colour" type="color" class="h-9 w-full cursor-pointer rounded border border-stone-300 bg-white p-1">
                    </label>
                </div>

                <label class="block">
                    <span class="field-micro">What it does</span>
                    <textarea v-model="row.description" rows="2" class="field" />
                </label>

                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
                    <span class="rounded px-2 py-1 font-serif text-xs font-semibold" :style="headSwatch(row.colour)">
                        {{ row.name }}
                    </span>
                    <span class="text-[11px] font-bold uppercase tracking-widest" :style="{ color: labelColour(row.colour) }">
                        <Icon v-if="row.icon_name" :name="row.icon_name" /> {{ row.name }}
                    </span>
                    <span class="text-stone-500">{{ row.faces_count }} card face{{ row.faces_count === 1 ? '' : 's' }}</span>

                    <span class="ml-auto flex gap-2">
                        <button type="button" class="btn-ghost" @click="save(row)"><Icon name="edit" /> Save</button>
                        <button type="button" class="btn-ghost text-red-700" @click="remove(row)"><Icon name="delete" /> Delete</button>
                    </span>
                </div>

                <p v-for="(message, field) in errorsFor(row.id)" :key="field" class="field-error">{{ message }}</p>
            </div>
        </section>

        <section v-if="owned.length" class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white">
            <h2 class="bg-stone-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-widest text-stone-500">
                One scenario's own — offered only on that scenario's cards
            </h2>

            <div v-for="row in owned" :key="row.id" class="space-y-3 p-4">
                <div class="flex flex-wrap items-end gap-3">
                    <label class="w-48">
                        <span class="field-micro">Name</span>
                        <input v-model="row.name" type="text" class="field">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Slug</span>
                        <input v-model="row.slug" type="text" class="field font-mono">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Icon</span>
                        <select v-model="row.icon" class="field">
                            <option :value="null">off the slug</option>
                            <option v-for="name in icons" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </label>
                    <label class="w-28">
                        <span class="field-micro">Colour</span>
                        <input v-model="row.colour" type="color" class="h-9 w-full cursor-pointer rounded border border-stone-300 bg-white p-1">
                    </label>
                </div>

                <label class="block">
                    <span class="field-micro">What it does</span>
                    <textarea v-model="row.description" rows="2" class="field" />
                </label>

                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
                    <Link :href="`/scenarios/${row.scenario_slug}`" class="rounded border border-stone-300 px-2 py-0.5 font-semibold text-stone-600 hover:border-stone-500">
                        <Icon name="scenario" /> {{ row.scenario }}
                    </Link>
                    <span class="rounded px-2 py-1 font-serif text-xs font-semibold" :style="headSwatch(row.colour)">
                        {{ row.name }}
                    </span>
                    <span class="text-stone-500">{{ row.faces_count }} card face{{ row.faces_count === 1 ? '' : 's' }}</span>

                    <span class="ml-auto flex gap-2">
                        <button type="button" class="btn-ghost" @click="save(row)"><Icon name="edit" /> Save</button>
                        <button type="button" class="btn-ghost text-red-700" @click="remove(row)"><Icon name="delete" /> Delete</button>
                    </span>
                </div>

                <p v-for="(message, field) in errorsFor(row.id)" :key="field" class="field-error">{{ message }}</p>
            </div>
        </section>

        <section class="rounded-lg border border-stone-300 bg-white p-4 text-sm text-stone-700">
            <h2 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="warning" /> How a type behaves
            </h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>The colour fills the card's head band. A split card whose halves are different types prints both, top colour at the top.</li>
                <li>The type line under it takes the same colour, darkened only as far as it has to be to stay legible on the card's cream body. The colour you picked is the one the head prints.</li>
                <li>A slug is unique across every type, shared or a scenario's own, so a design file saying <code class="rounded bg-stone-100 px-1 font-mono">"type": "tide"</code> can only mean one thing.</li>
                <li>Leaving the icon off looks for one named after the slug — which is how the five shipped types get theirs.</li>
                <li>Deleting a type leaves the cards that used it typed as nothing. It never rewrites a card.</li>
                <li v-if="scenarios.length">A scenario's own type is added on its page: <Link v-for="(s, i) in scenarios" :key="s.id" :href="`/scenarios/${s.slug}`" class="text-amber-800 underline">{{ i ? ', ' : '' }}{{ s.name }}</Link>.</li>
            </ul>
        </section>
    </div>
</template>
