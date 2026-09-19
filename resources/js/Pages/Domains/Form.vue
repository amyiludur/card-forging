<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    domain: { type: Object, default: null },
});

const form = useForm({
    name: props.domain?.name ?? '',
    slug: props.domain?.slug ?? '',
    title: props.domain?.title ?? '',
    status: props.domain?.status ?? 'draft; every number is a placeholder for playtesting',
    identity: props.domain?.identity ?? '',
    set_icon: props.domain?.set_icon ?? '',
    is_neutral: props.domain?.is_neutral ?? false,
    is_placeholder: props.domain?.is_placeholder ?? true,
});

const submit = () => {
    if (props.domain) {
        form.put(`/domains/${props.domain.slug}`);
    } else {
        form.post('/domains');
    }
};
</script>

<template>
    <Head :title="domain ? `Edit ${domain.name}` : 'New domain'" />

    <PageHeader
        :title="domain ? `Edit ${domain.name}` : 'New domain'"
        subtitle="A domain is a pool of cards shared between characters. How many domains a character takes is not settled, so nothing here expects a pool to be any particular size."
    >
        <template #actions>
            <Link v-if="domain" :href="`/domains/${domain.slug}`" class="btn-ghost">Cancel</Link>
            <Link v-else href="/domains" class="btn-ghost">Cancel</Link>
            <button type="submit" form="domain-form" class="btn-primary" :disabled="form.processing"><Icon name="edit" /> Save</button>
        </template>
    </PageHeader>

    <form id="domain-form" class="max-w-2xl space-y-5 px-6 py-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_120px]">
            <div>
                <label class="field-label">Name</label>
                <input v-model="form.name" type="text" class="field" placeholder="Tide">
                <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="field-label">Set icon</label>
                <input v-model="form.set_icon" type="text" class="field" maxlength="16" placeholder="TD">
                <p class="field-hint">Printed on the card.</p>
            </div>
        </div>

        <div>
            <label class="field-label">Title</label>
            <input v-model="form.title" type="text" class="field" placeholder="not written yet">
        </div>

        <div>
            <label class="field-label">Identity</label>
            <textarea v-model="form.identity" rows="2" class="field" placeholder="What this domain does that the others do not." />
            <p class="field-hint">One line on how the pool plays, the way a character has one.</p>
        </div>

        <label class="flex items-start gap-2 text-sm text-stone-700">
            <input v-model="form.is_neutral" type="checkbox" class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600">
            <span>
                Colourless: this is the neutral pool
                <span class="block text-xs text-stone-600">
                    Neutral cards fill a domain slot without belonging to a colour. Whether they count at all is
                    <Link href="/rules/config" class="underline">a tunable number</Link>.
                </span>
            </span>
        </label>

        <div>
            <label class="field-label">Status</label>
            <input v-model="form.status" type="text" class="field">
            <p class="field-hint">Shown wherever the domain appears, so a draft always reads as a draft.</p>
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700">
            <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
            Placeholder: the cards here are invented and expected to change
        </label>
    </form>
</template>
