<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import MarkupField from '../../Components/MarkupField.vue';
import CardPreview from '../../Components/CardPreview.vue';
import ScaledNumberField from '../../Components/ScaledNumberField.vue';
import { band } from '../../colour';
import Icon from '../../Components/Icon.vue';

const props = defineProps({
    character: { type: Object, default: null },
});

const form = useForm({
    name: props.character?.name ?? '',
    slug: props.character?.slug ?? '',
    title: props.character?.title ?? '',
    story: props.character?.story ?? '',
    status: props.character?.status ?? 'draft; every number is a placeholder for playtesting',
    identity: props.character?.identity ?? '',
    // The character card's head band. Both may be left empty; one alone is a
    // flat band and neither is the dark head it printed before.
    colour: props.character?.colour ?? '',
    colour_secondary: props.character?.colour_secondary ?? '',
    health: props.character?.health ?? 10,
    // Empty is a plain number; an equation counting the players is the value
    // while it is there. The number beside it is what comes back if it is
    // cleared, which is why it is kept rather than overwritten.
    health_equation: props.character?.health_equation ?? null,
    hand_size: props.character?.hand_size ?? 5,
    hand_size_equation: props.character?.hand_size_equation ?? null,
    gold_per_round: props.character?.gold_per_round ?? 2,
    gold_per_round_equation: props.character?.gold_per_round_equation ?? null,
    ability_name: props.character?.ability_name ?? '',
    ability_text: props.character?.ability_text ?? '',
    is_placeholder: props.character?.is_placeholder ?? true,
});

// The same band the card prints, so the swatch and the preview cannot disagree.
const headBand = computed(() => band(form.colour, form.colour_secondary, '135deg') ?? '#3f2b56');

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

            <div>
                <label class="field-label">Card colours</label>
                <div class="flex flex-wrap items-center gap-3">
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input
                            :value="form.colour || '#3f2b56'"
                            type="color"
                            class="h-9 w-14 cursor-pointer rounded border border-stone-300 bg-white p-1"
                            @input="form.colour = $event.target.value"
                        >
                        from
                    </label>
                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input
                            :value="form.colour_secondary || form.colour || '#3f2b56'"
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
                    The character card's head band, as a slight gradient from the first colour to the second.
                    Leave them clear for the dark head every other card prints.
                </p>
                <p v-if="form.errors.colour" class="field-error">{{ form.errors.colour }}</p>
                <p v-if="form.errors.colour_secondary" class="field-error">{{ form.errors.colour_secondary }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <ScaledNumberField
                    v-model:value="form.health"
                    v-model:equation="form.health_equation"
                    label="Health"
                    :min="1"
                    :max="99"
                    :error="form.errors.health_equation || form.errors.health"
                />

                <ScaledNumberField
                    v-model:value="form.hand_size"
                    v-model:equation="form.hand_size_equation"
                    label="Hand size"
                    :min="1"
                    :max="20"
                    hint="Drawn up to in step 3 of the player phase."
                    :error="form.errors.hand_size_equation || form.errors.hand_size"
                />

                <ScaledNumberField
                    v-model:value="form.gold_per_round"
                    v-model:equation="form.gold_per_round_equation"
                    label="Gold per round"
                    :min="0"
                    :max="20"
                    hint="Generated in the gold step, before any pouch gold."
                    :error="form.errors.gold_per_round_equation || form.errors.gold_per_round"
                />
            </div>

            <div>
                <label class="field-label">Ability name</label>
                <input v-model="form.ability_name" type="text" class="field" placeholder="Deadeye">
            </div>

            <MarkupField
                v-model="form.ability_text"
                label="Ability"
                :rows="3"
                :card-name="form.name ?? ''"
                hint="The identity ability, once per round unless the text says otherwise."
                :error="form.errors.ability_text"
            />

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
