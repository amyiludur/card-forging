<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import { band } from '../../colour';
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
    // The head band every card this pool carries prints. Both may be left
    // empty; one alone is a flat band and neither is the dark blue head a
    // domain card has always printed.
    colour: props.domain?.colour ?? '',
    colour_secondary: props.domain?.colour_secondary ?? '',
    set_icon: props.domain?.set_icon ?? '',
    is_neutral: props.domain?.is_neutral ?? false,
    is_placeholder: props.domain?.is_placeholder ?? true,
});

// The same band the card prints, so the swatch and the preview cannot disagree.
const headBand = computed(() => band(form.colour, form.colour_secondary, '135deg') ?? '#1e3a5f');

// A stand-in for one of this pool's cards, so the picker shows the head band
// every card here will actually print rather than just a swatch.
const previewCard = computed(() => ({
    name: form.name ? `${form.name} card` : 'Card name',
    colour: form.colour,
    colour_secondary: form.colour_secondary,
    type: 'action',
    text: form.identity || 'What a card from this pool reads like.',
    set_icon: form.set_icon,
    domain: form.name,
}));

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

    <form id="domain-form" class="grid gap-8 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_240px]" @submit.prevent="submit">
        <div class="max-w-2xl space-y-5">
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

            <div>
                <label class="field-label">Card colours</label>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input
                            :value="form.colour || '#1e3a5f'"
                            type="color"
                            class="h-9 w-14 cursor-pointer rounded border border-stone-300 bg-white p-1"
                            @input="form.colour = $event.target.value"
                        >
                        from
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input
                            :value="form.colour_secondary || form.colour || '#1e3a5f'"
                            type="color"
                            class="h-9 w-14 cursor-pointer rounded border border-stone-300 bg-white p-1"
                            @input="form.colour_secondary = $event.target.value"
                        >
                        to
                    </label>
                    <span class="h-9 w-40 rounded border border-stone-300" :style="{ background: headBand }" />
                    <button
                        v-if="form.colour || form.colour_secondary"
                        type="button"
                        class="btn-ghost"
                        @click="form.colour = ''; form.colour_secondary = ''"
                    >
                        Clear
                    </button>
                </div>
                <p class="field-hint">
                    The head band every card in this pool prints, as a slight gradient from the first colour to the
                    second. Leave them clear for the dark blue head a domain card has always printed.
                </p>
                <p v-if="form.errors.colour" class="field-error">{{ form.errors.colour }}</p>
                <p v-if="form.errors.colour_secondary" class="field-error">{{ form.errors.colour_secondary }}</p>
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
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="field-micro mb-2">Preview</p>
            <CardPreview :card="previewCard" kind="player" />
        </aside>
    </form>
</template>
