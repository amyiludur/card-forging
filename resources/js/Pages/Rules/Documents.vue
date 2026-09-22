<script setup>
import { computed, ref, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';
import { renderRules } from '../../rulesMarkdown';

const props = defineProps({
    documents: { type: Array, default: () => [] },
    document: { type: Object, default: null },
    versions: { type: Array, default: () => [] },
    config: { type: Array, default: () => [] },
});

const form = useForm({
    title: props.document?.title ?? '',
    body: props.document?.body ?? '',
});

watch(
    () => props.document,
    (doc) => form.defaults({ title: doc?.title ?? '', body: doc?.body ?? '' }).reset(),
);

const configMap = computed(() =>
    Object.fromEntries(props.config.map((entry) => [entry.key, entry]))
);

// The icons and the keyword library come from the shared props, so the rules
// preview resolves the same tokens a card does.
const page = usePage();
const icons = computed(() => page.props.markup?.icons ?? {});
const paths = computed(() => page.props.markup?.paths ?? {});
const keywords = computed(() => page.props.markup?.keywords ?? {});

// The rulebook's own markdown, rendered by the half of RulesMarkdown that runs
// in the browser — the same one the print sheet runs server side, so the
// preview here is what comes out of the printer.
const preview = computed(() =>
    renderRules(form.body, {
        icons: icons.value,
        paths: paths.value,
        keywords: keywords.value,
        config: configMap.value,
    })
);

const creating = ref(false);
const newDocument = useForm({ title: '', slug: '' });

const createDocument = () =>
    newDocument
        .transform((data) => ({ ...data, slug: data.slug || data.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') }))
        .post('/rules', { onSuccess: () => { newDocument.reset(); creating.value = false; } });

const restore = (version) => {
    if (confirm('Restore this version? The current text is saved to history first.')) {
        router.post(`/rules/${props.document.slug}/restore/${version.id}`);
    }
};
</script>

<template>
    <Head :title="document ? document.title : 'Rules'" />

    <PageHeader :title="document ? document.title : 'Rulebook'" subtitle="Markdown, with live tunable numbers.">
        <template #actions>
            <Link href="/print/rules" class="btn-ghost"><Icon name="print" /> Print</Link>
            <button type="button" class="btn-ghost" @click="creating = !creating">New document</button>
            <button v-if="document" type="submit" form="rules-form" class="btn-primary" :disabled="form.processing">Save</button>
        </template>

        <div class="mt-4 flex flex-wrap gap-1">
            <Link
                v-for="item in documents"
                :key="item.slug"
                :href="`/rules/${item.slug}`"
                class="rounded border px-2.5 py-1 text-sm"
                :class="document?.slug === item.slug ? 'border-stone-900 bg-stone-900 text-stone-50' : 'border-stone-300 bg-white text-stone-700 hover:border-stone-500'"
            >
                {{ item.title }}
            </Link>
        </div>

        <form v-if="creating" class="mt-3 flex flex-wrap items-end gap-3 rounded border border-dashed border-stone-400 bg-white p-3" @submit.prevent="createDocument">
            <div class="min-w-48 flex-1">
                <label class="field-label">Title</label>
                <input v-model="newDocument.title" type="text" class="field" placeholder="06 Characters">
            </div>
            <button type="submit" class="btn-primary" :disabled="newDocument.processing">Create</button>
            <p v-if="newDocument.errors.slug" class="field-error w-full">{{ newDocument.errors.slug }}</p>
        </form>
    </PageHeader>

    <div v-if="document" class="px-6 py-6">
        <form id="rules-form" class="grid gap-6 xl:grid-cols-2" @submit.prevent="form.put(`/rules/${document.slug}`, { preserveScroll: true })">
            <div class="space-y-3">
                <div>
                    <label class="field-label">Title</label>
                    <input v-model="form.title" type="text" class="field">
                </div>

                <div>
                    <label class="field-label">Markdown</label>
                    <textarea v-model="form.body" rows="30" class="field font-mono text-[13px] leading-relaxed" />
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save</button>
                    <span v-if="form.recentlySuccessful" class="text-emerald-700">Saved.</span>
                    <span class="text-stone-500">Last saved {{ document.updated_at }}</span>
                </div>

                <p v-if="document.references.length" class="rounded bg-stone-100 px-3 py-2 text-xs text-stone-700">
                    This document reads
                    <code v-for="key in document.references" :key="key" class="mx-0.5">{{ key }}</code>
                    from the tunable numbers.
                </p>
            </div>

            <div class="space-y-4">
                <div>
                    <p class="field-label">Preview</p>
                    <article class="rules-prose rounded-lg border border-stone-300 bg-white p-5" v-html="preview" />
                </div>

                <div v-if="versions.length">
                    <p class="field-label">History</p>
                    <ul class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white text-sm">
                        <li v-for="version in versions" :key="version.id" class="flex items-center justify-between gap-3 px-3 py-2">
                            <span>
                                <span class="text-stone-800">{{ version.saved_at }}</span>
                                <span class="ml-2 text-xs text-stone-500">{{ version.note }} · {{ version.length }} chars</span>
                            </span>
                            <button type="button" class="text-xs text-amber-800 hover:underline" @click="restore(version)">restore</button>
                        </li>
                    </ul>
                </div>
            </div>
        </form>
    </div>

    <p v-else class="px-6 py-8 text-sm text-stone-600">
        No rules documents yet. Run <code>php artisan design:import</code>, or create one above.
    </p>
</template>
