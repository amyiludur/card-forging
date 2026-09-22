<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';

/**
 * The design folder, from a button.
 *
 * The page is deliberately blunt about which way each button moves data,
 * because the two commands are opposites and pressing the wrong one loses work:
 * import overwrites the editor, export overwrites the folder. Importing asks
 * first for that reason, and says what it would take with it.
 */
const props = defineProps({
    status: { type: Object, required: true },
});

const busy = ref(null);

const run = (url, label) => {
    busy.value = label;
    router.post(url, {}, {
        preserveScroll: true,
        onFinish: () => (busy.value = null),
    });
};

const confirmImport = () => {
    const warning = props.status.changes.length
        ? 'design/ has uncommitted changes, which will be read in as they stand.\n\n'
        : '';

    if (confirm(`${warning}Import design/ into the editor?\n\nThe folder is the source of truth, so anything in the editor that the folder does not have is deleted.`)) {
        run('/design/import', 'import');
    }
};

const publish = useForm({ message: '', push: true });

const submitPublish = () => publish.post('/design/publish', {
    preserveScroll: true,
    onSuccess: () => publish.reset('message'),
});

/** git's own two letters, said in words. */
const statusLabel = (code) => ({
    '??': 'new',
    M: 'changed',
    A: 'added',
    D: 'deleted',
    R: 'renamed',
}[code] ?? code);

const canCommit = computed(() => props.status.git && !props.status.detached && props.status.identity);
</script>

<template>
    <Head title="Design folder" />

    <PageHeader
        title="Design folder"
        subtitle="design/ is the game and the editor is a view of it. These are the same two commands as always — design:import reads the folder in, design:export writes it back out."
    />

    <div class="max-w-4xl space-y-6 px-6 py-6">
        <!-- Which way the data moves. Two cards rather than two buttons in a
             row, because they are opposites and one of them deletes. -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="design-export" class="text-stone-500" /> Editor → design/
                </h2>
                <p class="mt-1 text-sm text-stone-600">
                    Writes every scenario, character, card and number back into the folder as JSON and markdown.
                    Safe to run at any time: it only ever rewrites files, and git is what keeps the history.
                </p>
                <button type="button" class="btn-primary mt-3" :disabled="busy !== null" @click="run('/design/export', 'export')">
                    <Icon name="design-export" /> {{ busy === 'export' ? 'Exporting…' : 'Export' }}
                </button>
            </div>

            <div class="rounded-lg border border-red-300 bg-white p-4">
                <h2 class="flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="design-import" class="text-red-700" /> design/ → editor
                </h2>
                <p class="mt-1 text-sm text-stone-600">
                    Reads the folder back in. <strong class="text-red-800">This overwrites the editor.</strong>
                    Anything edited here and not exported is gone, and a card the folder does not have is deleted.
                </p>
                <button type="button" class="btn-ghost mt-3 border-red-300 text-red-800" :disabled="busy !== null" @click="confirmImport">
                    <Icon name="design-import" /> {{ busy === 'import' ? 'Importing…' : 'Import' }}
                </button>
            </div>
        </div>

        <!-- What git has to say about the folder right now. -->
        <div class="rounded-lg border border-stone-300 bg-white">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-200 px-4 py-3">
                <h2 class="flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="folder" class="text-stone-500" /> What has changed
                </h2>
                <p v-if="status.git" class="text-xs text-stone-500">
                    on <code class="rounded bg-stone-100 px-1">{{ status.branch ?? 'a detached HEAD' }}</code>
                    <span v-if="status.ahead"> · {{ status.ahead }} unpushed {{ status.ahead === 1 ? 'commit' : 'commits' }}</span>
                </p>
            </div>

            <div class="px-4 py-3">
                <p v-if="!status.git" class="text-sm text-stone-600">This is not a git repository, so there is nothing to commit.</p>

                <p v-else-if="status.clean" class="text-sm text-stone-600">
                    design/ matches the last commit. Export first if you have edited anything.
                </p>

                <ul v-else class="space-y-1 font-mono text-xs">
                    <li v-for="change in status.changes" :key="change.path" class="flex items-baseline gap-2">
                        <span
                            class="w-16 shrink-0 rounded px-1 text-center text-[10px] uppercase tracking-wide"
                            :class="change.status === 'D' ? 'bg-red-100 text-red-800' : change.status === '??' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900'"
                        >{{ statusLabel(change.status) }}</span>
                        <span class="min-w-0 break-all text-stone-700">{{ change.path }}</span>
                    </li>
                </ul>

                <p v-if="status.last_commit" class="mt-3 border-t border-stone-200 pt-2 text-xs text-stone-500">
                    Last commit <code class="rounded bg-stone-100 px-1">{{ status.last_commit.hash }}</code>
                    {{ status.last_commit.subject }} — {{ status.last_commit.when }}
                </p>
            </div>
        </div>

        <!-- Anything that would stop a commit, said before the button is pressed. -->
        <div v-if="status.blockers?.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4">
            <h2 class="flex items-center gap-2 font-serif text-base font-semibold text-amber-900">
                <Icon name="warning" /> Before this can commit
            </h2>
            <ul class="mt-1 list-inside list-disc space-y-0.5 text-sm text-amber-900">
                <li v-for="blocker in status.blockers" :key="blocker">{{ blocker }}</li>
            </ul>
        </div>

        <!-- Export, commit, push: one press, three steps, reported separately. -->
        <form v-if="canCommit" class="rounded-lg border border-stone-300 bg-white p-4" @submit.prevent="submitPublish">
            <h2 class="flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="commit" class="text-stone-500" /> Export, commit and push
            </h2>
            <p class="mt-1 text-sm text-stone-600">
                Exports first, then commits <strong>design/ only</strong> — never the app's own code — and pushes
                <code class="rounded bg-stone-100 px-1">{{ status.branch }}</code> to origin.
                It stops at the first step that fails rather than carrying on.
            </p>

            <label class="field-label mt-3">Commit message</label>
            <input
                v-model="publish.message"
                type="text"
                class="field"
                placeholder="Kraken: starting Dread counts the players"
                maxlength="200"
            >
            <p v-if="publish.errors.message" class="field-error">{{ publish.errors.message }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-4">
                <button type="submit" class="btn-primary" :disabled="publish.processing || busy !== null">
                    <Icon :name="publish.push ? 'push' : 'commit'" />
                    {{ publish.processing ? 'Working…' : (publish.push ? 'Export, commit and push' : 'Export and commit') }}
                </button>

                <label class="flex items-center gap-1.5 text-sm text-stone-600" :title="status.remote ? '' : 'There is no origin remote to push to'">
                    <input
                        v-model="publish.push"
                        type="checkbox"
                        :disabled="!status.remote"
                        class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                    >
                    push to origin
                </label>

                <span v-if="status.identity" class="text-xs text-stone-500">
                    as {{ status.identity.name }} &lt;{{ status.identity.email }}&gt;
                </span>
            </div>
        </form>
    </div>
</template>
