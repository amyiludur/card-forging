<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import MarkupField from '../../Components/MarkupField.vue';
import CardPreview from '../../Components/CardPreview.vue';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    character: { type: Object, default: null },
    domains: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.character?.name ?? '',
    slug: props.character?.slug ?? '',
    title: props.character?.title ?? '',
    story: props.character?.story ?? '',
    status: props.character?.status ?? 'draft; every number is a placeholder for playtesting',
    identity: props.character?.identity ?? '',
    health: props.character?.health ?? 10,
    hand_size: props.character?.hand_size ?? 5,
    gold_per_round: props.character?.gold_per_round ?? 2,
    ability_name: props.character?.ability_name ?? '',
    ability_text: props.character?.ability_text ?? '',
    is_placeholder: props.character?.is_placeholder ?? true,
    // The shared half of the deck. How many a character takes is not settled,
    // so any number is allowed and the character page reports the total.
    domains: props.character?.domains ?? [],
});

const submit = () => {
    if (props.character) {
        form.put(`/characters/${props.character.slug}`);
    } else {
        form.post('/characters');
    }
};
</script>

<template>
    <Head :title="character ? `Edit ${character.name}` : 'New character'" />

    <PageHeader
        :title="character ? `Edit ${character.name}` : 'New character'"
        subtitle="Health, hand size and gold generation live on the character card rather than in the tunable numbers: they are per character."
    >
        <template #actions>
            <Link v-if="character" :href="`/characters/${character.slug}`" class="btn-ghost">Cancel</Link>
            <Link v-else href="/characters" class="btn-ghost">Cancel</Link>
            <button type="submit" form="character-form" class="btn-primary" :disabled="form.processing"><Icon name="edit" /> Save</button>
        </template>
    </PageHeader>

    <form id="character-form" class="grid gap-8 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_240px]" @submit.prevent="submit">
        <div class="max-w-2xl space-y-5">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field" placeholder="Gunslinger">
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                    <p class="field-hint">A working name is fine. The printed name and story come later.</p>
                </div>

                <div>
                    <label class="field-label">Title</label>
                    <input v-model="form.title" type="text" class="field" placeholder="not written yet">
                    <p v-if="form.errors.title" class="field-error">{{ form.errors.title }}</p>
                </div>
            </div>

            <div>
                <label class="field-label">Identity</label>
                <textarea v-model="form.identity" rows="2" class="field" placeholder="Fast, cheap and loud." />
                <p class="field-hint">One line on how the character plays. Printed as flavour on the character card.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label">Health</label>
                    <input v-model.number="form.health" type="number" min="1" max="99" class="field">
                    <p v-if="form.errors.health" class="field-error">{{ form.errors.health }}</p>
                </div>

                <div>
                    <label class="field-label">Hand size</label>
                    <input v-model.number="form.hand_size" type="number" min="1" max="20" class="field">
                    <p v-if="form.errors.hand_size" class="field-error">{{ form.errors.hand_size }}</p>
                    <p class="field-hint">Drawn up to in step 3 of the player phase.</p>
                </div>

                <div>
                    <label class="field-label">Gold per round</label>
                    <input v-model.number="form.gold_per_round" type="number" min="0" max="20" class="field">
                    <p v-if="form.errors.gold_per_round" class="field-error">{{ form.errors.gold_per_round }}</p>
                    <p class="field-hint">Generated in the gold step, before any pouch gold.</p>
                </div>
            </div>

            <div>
                <label class="field-label">Ability name</label>
                <input v-model="form.ability_name" type="text" class="field" placeholder="Deadeye">
            </div>

            <MarkupField
                v-model="form.ability_text"
                label="Ability"
                :rows="3"
                hint="The identity ability, once per round unless the text says otherwise."
                :error="form.errors.ability_text"
            />

            <div>
                <label class="field-label">Domains</label>
                <p class="field-hint mb-2">
                    Where the other half of the deck comes from. How many a character takes is not decided,
                    so pick as many as you like — the character page reports what they add up to against the slots.
                </p>

                <div v-if="domains.length" class="space-y-1.5 rounded border border-stone-300 bg-white p-3">
                    <label v-for="domain in domains" :key="domain.slug" class="flex items-start gap-2 text-sm text-stone-700">
                        <input
                            v-model="form.domains"
                            type="checkbox"
                            :value="domain.slug"
                            class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                        >
                        <span>
                            <Icon :name="domain.is_neutral ? 'neutral' : 'domain'" class="text-stone-500" />
                            {{ domain.name }}
                            <span class="text-stone-500">· {{ domain.pool_size }} cards</span>
                            <span v-if="domain.identity" class="block text-xs text-stone-600">{{ domain.identity }}</span>
                        </span>
                    </label>
                </div>

                <p v-else class="rounded border border-dashed border-stone-300 p-3 text-sm text-stone-600">
                    No domains yet. <Link href="/domains/create" class="underline">Make one</Link> and it appears here.
                </p>
            </div>

            <div>
                <label class="field-label">Story</label>
                <textarea v-model="form.story" rows="3" class="field" placeholder="not written yet" />
            </div>

            <div>
                <label class="field-label">Status</label>
                <input v-model="form.status" type="text" class="field">
                <p class="field-hint">Shown wherever the character appears, so a draft always reads as a draft.</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                Placeholder: the numbers here are invented and expected to change
            </label>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="field-micro mb-2">Preview</p>
            <CardPreview :card="form" kind="character" />
        </aside>
    </form>
</template>
