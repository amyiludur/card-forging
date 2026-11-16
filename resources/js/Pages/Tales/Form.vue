<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';

const props = defineProps({
    tale: { type: Object, default: null },
});

const form = useForm({
    name: props.tale?.name ?? '',
    status: props.tale?.status ?? 'draft; every number is a placeholder for playtesting',
    overview: props.tale?.overview ?? '',
    set_icon: props.tale?.set_icon ?? '',
    is_placeholder: props.tale?.is_placeholder ?? true,
});

const submit = () => (props.tale ? form.put(`/tales/${props.tale.slug}`) : form.post('/tales'));

const destroy = () => {
    if (confirm(`Delete ${props.tale.name}? Its modes, log entries, Tale cards, rewards and its own modules go with it. The scenarios it lists stay.`)) {
        router.delete(`/tales/${props.tale.slug}`);
    }
};
</script>

<template>
    <Head :title="tale ? `Edit ${tale.name}` : 'New Tale'" />

    <PageHeader :title="tale ? `Edit ${tale.name}` : 'New Tale'">
        <template #actions>
            <button v-if="tale" type="button" class="btn-ghost text-red-700" @click="destroy">Delete</button>
            <Link :href="tale ? `/tales/${tale.slug}` : '/tales'" class="btn-ghost">Cancel</Link>
        </template>
    </PageHeader>

    <form class="max-w-3xl space-y-5 px-6 py-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-[1fr,7rem]">
            <div>
                <label class="field-label">Name</label>
                <input v-model="form.name" type="text" class="field" placeholder="The Drowned Coast">
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="field-label">Set icon</label>
                <input v-model="form.set_icon" type="text" maxlength="8" class="field font-mono" placeholder="DC">
                <p class="field-hint">Printed on the Tale's cards.</p>
            </div>
        </div>

        <div>
            <label class="field-label">Overview</label>
            <textarea v-model="form.overview" rows="6" class="field" placeholder="The opening of the box: what has happened, and why the party is here." />
            <p class="field-hint">Read before the first scenario. Each scenario's own story is written on the Scenarios tab.</p>
        </div>

        <div>
            <label class="field-label">Status note</label>
            <input v-model="form.status" type="text" class="field">
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input v-model="form.is_placeholder" type="checkbox">
            Still a placeholder
        </label>

        <div class="flex gap-2">
            <button type="submit" class="btn-primary" :disabled="form.processing">{{ tale ? 'Save' : 'Create Tale' }}</button>
        </div>
    </form>
</template>
