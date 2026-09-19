<script setup>
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import { renderMarkup } from '../../markup';

const props = defineProps({
    keywords: { type: Array, default: () => [] },
    icons: { type: Array, default: () => [] },
    reserved: { type: Array, default: () => [] },
});

const page = usePage();
const paths = computed(() => page.props.markup?.paths ?? {});

// Each row edits in place; only the row being saved is sent, so one bad token
// never blocks the rest of the library.
const rows = reactive(props.keywords.map((keyword) => ({ ...keyword })));
const saving = ref(null);

const blank = () => ({
    token: '',
    name: '',
    icon: null,
    show_name: true,
    plain: '',
    description: '',
    is_placeholder: true,
    sort: props.keywords.length,
});

const adding = useForm(blank());

const errorsFor = (id) => (saving.value === id ? page.props.errors ?? {} : {});

// The card and the rules pages render a keyword exactly like this, so the row
// shows what the token will look like before it is saved anywhere.
const preview = (row) =>
    renderMarkup(`{${row.token || 'token'}}`, {
        paths: paths.value,
        keywords: { [row.token || 'token']: { ...row, name: row.name || 'Unnamed' } },
    });

const tokenOf = (row) => `{${row.token || 'token'}}`;

const save = (row) => {
    saving.value = row.id;
    router.put(`/rules/keywords/${row.id}`, row, { preserveScroll: true });
};

const add = () => {
    saving.value = 'new';
    adding.post('/rules/keywords', {
        preserveScroll: true,
        onSuccess: () => adding.defaults(blank()).reset(),
    });
};

const remove = (row) => {
    if (!confirm(`Delete ${row.name}? Text that says ${tokenOf(row)} will print it as typed.`)) return;

    router.delete(`/rules/keywords/${row.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Keywords" />

    <PageHeader
        title="Keywords"
        subtitle="The words a card can say in one word. Add one here and it can be typed as a token in any card or rules text — the card prints the keyword, and this page is what it means."
    />

    <div class="max-w-4xl space-y-6 px-6 py-6">
        <section class="rounded-lg border border-stone-300 bg-white">
            <h2 class="border-b border-stone-200 px-4 py-3 font-serif text-base font-semibold">
                <Icon name="add" /> New keyword
            </h2>

            <form class="space-y-3 p-4" @submit.prevent="add">
                <div class="flex flex-wrap gap-3">
                    <label class="w-40">
                        <span class="field-micro">Token</span>
                        <input v-model="adding.token" type="text" class="field font-mono" placeholder="unique">
                    </label>
                    <label class="w-48">
                        <span class="field-micro">Name</span>
                        <input v-model="adding.name" type="text" class="field" placeholder="Unique">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Icon</span>
                        <select v-model="adding.icon" class="field">
                            <option :value="null">none</option>
                            <option v-for="name in icons" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </label>
                    <label class="flex items-end gap-1.5 pb-2 text-xs text-stone-600" title="Off prints the icon alone">
                        <input v-model="adding.show_name" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        print the name
                    </label>
                </div>

                <label class="block">
                    <span class="field-micro">What it means</span>
                    <textarea v-model="adding.description" rows="2" class="field" placeholder="A deck may hold only one copy of this card." />
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-1.5 text-xs text-stone-600" title="A keyword still being decided reads as a draft wherever it appears">
                        <input v-model="adding.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        placeholder
                    </label>
                    <span class="text-xs text-stone-500">
                        Type <code class="rounded bg-stone-100 px-1 font-mono">{{ tokenOf(adding) }}</code> in card text →
                        <span v-html="preview(adding)" />
                    </span>
                    <button type="submit" class="btn-primary ml-auto" :disabled="adding.processing">
                        <Icon name="add" /> Add keyword
                    </button>
                </div>

                <p v-for="(message, field) in (saving === 'new' ? adding.errors : {})" :key="field" class="field-error">{{ message }}</p>
            </form>
        </section>

        <section v-if="rows.length" class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white">
            <div v-for="row in rows" :key="row.id" class="space-y-3 p-4">
                <div class="flex flex-wrap gap-3">
                    <label class="w-40">
                        <span class="field-micro">Token</span>
                        <input v-model="row.token" type="text" class="field font-mono">
                    </label>
                    <label class="w-48">
                        <span class="field-micro">Name</span>
                        <input v-model="row.name" type="text" class="field">
                    </label>
                    <label class="w-40">
                        <span class="field-micro">Icon</span>
                        <select v-model="row.icon" class="field">
                            <option :value="null">none</option>
                            <option v-for="name in icons" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </label>
                    <label class="w-32">
                        <span class="field-micro">Plain text</span>
                        <input v-model="row.plain" type="text" class="field" :placeholder="row.name">
                    </label>
                </div>

                <label class="block">
                    <span class="field-micro">What it means</span>
                    <textarea v-model="row.description" rows="2" class="field" />
                </label>

                <div class="flex flex-wrap items-center gap-3 text-xs text-stone-600">
                    <label class="flex items-center gap-1.5" title="Off prints the icon alone">
                        <input v-model="row.show_name" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        print the name
                    </label>
                    <label class="flex items-center gap-1.5">
                        <input v-model="row.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                        placeholder
                    </label>
                    <span>
                        <code class="rounded bg-stone-100 px-1 font-mono">{{ tokenOf(row) }}</code> →
                        <span v-html="preview(row)" />
                    </span>
                    <span class="text-stone-500">
                        {{ row.uses }} piece{{ row.uses === 1 ? '' : 's' }} of text
                    </span>

                    <span class="ml-auto flex gap-2">
                        <button type="button" class="btn-ghost" @click="save(row)"><Icon name="edit" /> Save</button>
                        <button type="button" class="btn-ghost text-red-700" @click="remove(row)"><Icon name="delete" /> Delete</button>
                    </span>
                </div>

                <p v-for="(message, field) in errorsFor(row.id)" :key="field" class="field-error">{{ message }}</p>
            </div>
        </section>

        <p v-else class="rounded-lg border border-dashed border-stone-300 bg-white p-6 text-center text-sm text-stone-500">
            No keywords yet. The game's own symbols —
            <code class="rounded bg-stone-100 px-1 font-mono">{omen}</code> and friends — are built in;
            everything else is yours to name.
        </p>

        <section class="rounded-lg border border-stone-300 bg-white p-4 text-sm text-stone-700">
            <h2 class="mb-2 flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="warning" /> How a keyword behaves
            </h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>A token is lowercase letters, digits and hyphens: <code class="rounded bg-stone-100 px-1 font-mono">unique</code>, <code class="rounded bg-stone-100 px-1 font-mono">bottom-draw</code>.</li>
                <li>The built-in icon tokens (<code class="rounded bg-stone-100 px-1 font-mono">{{ reserved.join(', ') }}</code>) are the game's symbols and cannot be taken.</li>
                <li>Plain text is what the design folder gets on export. Empty means the name.</li>
                <li>Renaming or deleting a keyword leaves the text that uses it exactly as typed — an unknown token prints as it was written, so nothing is silently rewritten.</li>
            </ul>
        </section>
    </div>
</template>
