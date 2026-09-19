<script setup>
import { computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import MarkupField from '../../Components/MarkupField.vue';
import TraitInput from '../../Components/TraitInput.vue';
import CardPreview from '../../Components/CardPreview.vue';

const props = defineProps({
    character: { type: Object, required: true },
    card: { type: Object, default: null },
    role: { type: String, default: 'signature' },
    siblings: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({}) },
});

// Where a card of each role starts, used only to seed a new card.
const defaultZones = { kit: 'play', signature: 'deck', upgrade: 'upgrade' };

const form = useForm({
    name: props.card?.name ?? '',
    slug: props.card?.slug ?? '',
    qty: props.card?.qty ?? 1,
    role: props.card?.role ?? props.role,
    origin: props.card?.origin ?? 'signature',
    domain: props.card?.domain ?? '',
    type: props.card?.type ?? 'action',
    gold_cost: props.card?.gold_cost ?? 0,
    omen_icons: props.card?.omen_icons ?? 0,
    shop_cost: props.card?.shop_cost ?? null,
    start_zone: props.card?.start_zone ?? defaultZones[props.role] ?? 'deck',
    text: props.card?.text ?? '',
    traits: props.card?.traits ?? [],
    keywords: props.card?.keywords ?? [],
    upgrades_to: props.card?.upgrades_to ?? null,
    upgrade_of: props.card?.upgrade_of ?? null,
    is_placeholder: props.card?.is_placeholder ?? true,
});

// An upgrade replaces a card; anything else may point at one.
const isUpgrade = computed(() => form.role === 'upgrade');

const replaceable = computed(() => props.siblings.filter((c) => c.role !== 'upgrade'));
const upgradeCards = computed(() => props.siblings.filter((c) => c.role === 'upgrade'));

// Role and origin both have a 'signature' value meaning different things, so
// each picker gets its own labels rather than sharing one map.
const roleLabels = {
    kit: 'Kit: starts in play, outside the 20',
    signature: 'Signature: one of the 20',
    upgrade: 'Upgrade: set aside, swapped in by the Smithy',
};

const originLabels = {
    signature: "Signature: this character's own",
    domain: 'Domain: one of the shared 20',
    neutral: 'Neutral: colourless, fills a domain slot',
};

const typeLabels = { action: 'Action', item: 'Item', response: 'Response' };

const zoneLabels = { deck: 'Deck', shop: 'Shop pile', play: 'In play', upgrade: 'Set aside as an upgrade' };

const submit = () => {
    if (props.card) {
        form.put(`/player-cards/${props.card.id}`);
    } else {
        form.post(`/characters/${props.character.slug}/cards`);
    }
};
</script>

<template>
    <Head :title="card ? `Edit ${card.name}` : 'New card'" />

    <PageHeader
        :title="card ? `Edit ${card.name}` : `New card for the ${character.name}`"
        subtitle="Gold is what the card costs to play; omen icons are what playing it adds to the pool."
    >
        <template #actions>
            <Link :href="`/characters/${character.slug}`" class="btn-ghost">Cancel</Link>
            <button type="submit" form="player-card-form" class="btn-primary" :disabled="form.processing">Save</button>
        </template>
    </PageHeader>

    <form id="player-card-form" class="grid gap-8 px-6 py-6 lg:grid-cols-[minmax(0,1fr)_240px]" @submit.prevent="submit">
        <div class="max-w-2xl space-y-5">
            <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_90px]">
                <div>
                    <label class="field-label">Name</label>
                    <input v-model="form.name" type="text" class="field" placeholder="Hollow Point">
                    <p v-if="form.errors.name" class="field-error">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="field-label">Copies</label>
                    <input v-model.number="form.qty" type="number" min="1" max="20" class="field">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label">Role</label>
                    <select v-model="form.role" class="field">
                        <option v-for="value in options.roles" :key="value" :value="value">{{ roleLabels[value] ?? value }}</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Type</label>
                    <select v-model="form.type" class="field">
                        <option v-for="value in options.types" :key="value" :value="value">{{ typeLabels[value] ?? value }}</option>
                    </select>
                </div>

                <div>
                    <label class="field-label">Starts in</label>
                    <select v-model="form.start_zone" class="field">
                        <option v-for="value in options.startZones" :key="value" :value="value">{{ zoneLabels[value] ?? value }}</option>
                    </select>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="field-label">Gold cost</label>
                    <input v-model.number="form.gold_cost" type="number" min="0" max="20" class="field">
                </div>

                <div>
                    <label class="field-label">Omen icons</label>
                    <input v-model.number="form.omen_icons" type="number" min="0" max="9" class="field">
                    <p class="field-hint">Added to the pool when the card is played.</p>
                </div>

                <div>
                    <label class="field-label">Shop cost</label>
                    <input v-model.number="form.shop_cost" type="number" min="0" max="20" class="field" placeholder="not buyable">
                    <p class="field-hint">Leave empty if it cannot be bought.</p>
                </div>
            </div>

            <MarkupField
                v-model="form.text"
                label="Effect"
                :rows="4"
                hint="Type {gold}, {omen} or {config:key} and they render the same here as on the printed card."
                :error="form.errors.text"
            />

            <div class="grid gap-4 sm:grid-cols-2">
                <TraitInput v-model="form.traits" label="Traits" :suggestions="['Bullet', 'Gear', 'Weapon', 'Redirect', 'Foresight', 'Pouch']" />
                <TraitInput v-model="form.keywords" label="Keywords" :suggestions="['Fired', 'Tuck', 'Bottom draw']" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label">Origin</label>
                    <select v-model="form.origin" class="field">
                        <option v-for="value in options.origins" :key="value" :value="value">{{ originLabels[value] ?? value }}</option>
                    </select>
                    <p class="field-hint">Signature cards are the character's own. Domains are not designed yet.</p>
                </div>

                <div v-if="form.origin !== 'signature'">
                    <label class="field-label">Domain</label>
                    <input v-model="form.domain" type="text" class="field" placeholder="not named yet">
                </div>
            </div>

            <div>
                <label v-if="isUpgrade" class="field-label">Replaces</label>
                <label v-else class="field-label">Upgrades to</label>

                <select v-if="isUpgrade" v-model="form.upgrade_of" class="field">
                    <option :value="null">nothing yet</option>
                    <option v-for="sibling in replaceable" :key="sibling.slug" :value="sibling.slug">{{ sibling.name }}</option>
                </select>
                <select v-else v-model="form.upgrades_to" class="field">
                    <option :value="null">no upgrade</option>
                    <option v-for="sibling in upgradeCards" :key="sibling.slug" :value="sibling.slug">{{ sibling.name }}</option>
                </select>

                <p class="field-hint">
                    Setting this from either card writes both ends, so you only pick it once. An upgrade
                    replaces one card, and a card has one upgrade: naming a new partner releases the old one.
                </p>
            </div>

            <label class="flex items-center gap-2 text-sm text-stone-700">
                <input v-model="form.is_placeholder" type="checkbox" class="rounded border-stone-400 text-amber-700 focus:ring-amber-600">
                Placeholder: the numbers here are invented and expected to change
            </label>
        </div>

        <aside class="lg:sticky lg:top-6 lg:self-start">
            <p class="field-micro mb-2">Preview</p>
            <CardPreview :card="form" kind="player" />
        </aside>
    </form>
</template>
