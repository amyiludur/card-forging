<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import Icon from '../../Components/Icon.vue';
import HirelingSummary from '../../Components/HirelingSummary.vue';
import { useCardZoom } from '../../useCardZoom';
import ScaledValue from '../../Components/ScaledValue.vue';

const props = defineProps({
    character: { type: Object, required: true },
    cards: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    upgradePairs: { type: Array, default: () => [] },
    warnings: { type: Array, default: () => [] },
});

// The three lists the design file is written in, kept in that order.
const sections = computed(() => [
    {
        role: 'kit',
        title: 'Kit',
        blurb: 'Starts in play and sits outside the 20.',
        cards: props.cards.filter((c) => c.role === 'kit'),
    },
    {
        role: 'signature',
        title: 'Signature cards',
        blurb: `The ${props.stats.rule?.signature ?? 20} this character brings to a deck.`,
        cards: props.cards.filter((c) => c.role === 'signature'),
    },
    {
        role: 'upgrade',
        title: 'Upgrades',
        blurb: 'Set aside outside the 40. The Smithy swaps a card for its upgrade.',
        cards: props.cards.filter((c) => c.role === 'upgrade'),
    },
]);

const startingDeck = computed(() => props.stats.start_zones?.deck ?? 0);
const shopPile = computed(() => props.stats.start_zones?.shop ?? 0);

const zoom = useCardZoom('player');

const openCharacter = () => zoom.open([props.character], 0, 'character');

const deleteCard = (card) => {
    if (window.confirm(`Delete ${card.name}?`)) {
        router.delete(`/player-cards/${card.id}`, { preserveScroll: true });
    }
};

const entries = (object) => Object.entries(object ?? {});
</script>

<template>
    <Head :title="character.name" />

    <PageHeader :title="character.name" :subtitle="character.identity">
        <template #actions>
            <Link :href="`/print/character/${character.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
            <Link :href="`/characters/${character.slug}/cards/create`" class="btn-ghost"><Icon name="add" /> New card</Link>
            <Link :href="`/characters/${character.slug}/edit`" class="btn-primary"><Icon name="edit" /> Edit character</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span><Icon name="health" class="text-red-800" /> <strong><ScaledValue :value="character.health" :equation="character.health_equation" /></strong> health</span>
            <span><Icon name="hand" class="text-stone-500" /> <strong><ScaledValue :value="character.hand_size" :equation="character.hand_size_equation" /></strong> hand size</span>
            <span><Icon name="gold" class="text-amber-700" /> <strong><ScaledValue :value="character.gold_per_round" :equation="character.gold_per_round_equation" /></strong> gold a round</span>
            <span v-if="character.ability_name"><strong>{{ character.ability_name }}</strong></span>
            <span v-if="character.status" class="italic text-amber-800">{{ character.status }}</span>
        </div>
    </PageHeader>

    <div class="space-y-8 px-6 py-6">
        <section
            v-if="warnings.length"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <h2 class="flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="warning" /> Worth a look
            </h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                <li v-for="warning in warnings" :key="warning">{{ warning }}</li>
            </ul>
            <p class="mt-2 text-xs text-amber-800">
                These are reported, not corrected. Whether the cards are wrong or the rule is, is yours to decide.
            </p>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="stat">
                <p class="stat-value">{{ stats.signature_total }} / {{ stats.rule?.signature }}</p>
                <p class="stat-label">signature cards, counted by copy</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.domain_slots }}</p>
                <p class="stat-label">
                    more taken from a domain when a deck is built, out of
                    {{ stats.domain_cards_available }} in the library
                </p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ startingDeck }} · {{ shopPile }}</p>
                <p class="stat-label">start in the deck · start in the shop</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.kit_total }} · {{ stats.upgrade_total }}</p>
                <p class="stat-label">kit cards · upgrades, both outside the 40</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Omen icons</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="bucket in stats.omen_curve" :key="bucket.value" class="flex items-center gap-2">
                        <span class="flex w-14 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="omen" /></span>
                        <span class="h-3 rounded bg-stone-800" :style="{ width: `${bucket.count * 10}px` }" />
                        <span class="text-stone-600">{{ bucket.count }}</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Gold cost</h2>
                <p class="mb-2 text-xs text-stone-600">
                    Against the <ScaledValue :value="character.gold_per_round" :equation="character.gold_per_round_equation" /> a round this character generates.
                </p>
                <ul class="space-y-1 text-sm">
                    <li v-for="bucket in stats.gold_curve" :key="bucket.value" class="flex items-center gap-2">
                        <span class="flex w-14 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="gold" /></span>
                        <span class="h-3 rounded bg-amber-700" :style="{ width: `${bucket.count * 10}px` }" />
                        <span class="text-stone-600">{{ bucket.count }}</span>
                    </li>
                </ul>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Types and keywords</h2>
                <p class="text-sm text-stone-700">
                    <span v-for="([type, count], i) in entries(stats.types)" :key="type">
                        <span v-if="i"> · </span>{{ count }} {{ type }}
                    </span>
                </p>
                <HirelingSummary :hirelings="stats.hirelings" />
                <p class="mt-2 text-sm text-stone-700">
                    <span v-for="([word, count], i) in entries(stats.keywords)" :key="word">
                        <span v-if="i"> · </span>{{ word }} ×{{ count }}
                    </span>
                    <span v-if="!entries(stats.keywords).length" class="text-stone-500">No keywords yet.</span>
                </p>
            </div>
        </section>

        <section class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="domain" class="text-stone-500" /> The other half of the deck
            </h2>
            <p class="text-sm text-stone-600">
                A character does not have a domain. A deck pairs this character with one domain — any of them —
                and takes {{ stats.domain_slots }} of its cards, so that half is chosen when a deck is built, not here.
            </p>
            <Link :href="`/decks?character=${character.slug}`" class="btn-ghost mt-3">
                <Icon name="zone-deck" /> Build a deck with {{ character.name }}
            </Link>
        </section>

        <section>
            <h2 class="mb-1 font-serif text-lg font-semibold">Character card</h2>
            <p class="mb-3 text-sm text-stone-600">Health, hand size and the identity ability. This prints as a card too.</p>
            <button type="button" class="card-button" @click="openCharacter">
                <CardPreview :card="character" kind="character" />
            </button>
        </section>

        <section v-for="section in sections" :key="section.role">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="font-serif text-lg font-semibold">{{ section.title }}</h2>
                    <p class="text-sm text-stone-600">{{ section.blurb }}</p>
                </div>
                <Link :href="`/characters/${character.slug}/cards/create?role=${section.role}`" class="btn-ghost">
                    <Icon name="add" /> Add to {{ section.title.toLowerCase() }}
                </Link>
            </div>

            <div v-if="section.cards.length" class="flex flex-wrap gap-4">
                <div v-for="(card, index) in section.cards" :key="card.id" class="w-[200px]">
                    <button type="button" class="card-button" @click="zoom.open(section.cards, index, 'player')">
                        <CardPreview :card="card" kind="player" />
                    </button>

                    <p v-if="card.role !== 'upgrade' && card.upgrades_to_name" class="mt-1 text-xs text-stone-600">
                        upgrades to <span class="font-medium text-stone-900">{{ card.upgrades_to_name }}</span>
                    </p>
                    <p v-else-if="card.role === 'upgrade' && !card.replaces_name" class="mt-1 text-xs font-medium text-amber-800">
                        replaces nothing yet
                    </p>

                    <div class="mt-1 flex items-center gap-2 text-xs text-stone-600">
                        <span v-if="card.qty > 1">×{{ card.qty }}</span>
                        <Link :href="`/player-cards/${card.id}/edit`" class="underline hover:text-stone-900">
                            <Icon name="edit" /> Edit
                        </Link>
                        <Link
                            :href="`/player-cards/${card.id}/duplicate`"
                            method="post"
                            as="button"
                            class="underline hover:text-stone-900"
                            preserve-scroll
                        >
                            <Icon name="duplicate" /> Duplicate
                        </Link>
                        <button type="button" class="underline hover:text-red-700" @click="deleteCard(card)">
                            <Icon name="delete" /> Delete
                        </button>
                    </div>
                </div>
            </div>

            <p v-else class="rounded border border-dashed border-stone-300 p-6 text-center text-sm text-stone-600">
                Nothing here yet.
            </p>
        </section>

        <section v-if="upgradePairs.length">
            <h2 class="mb-1 flex items-center gap-2 font-serif text-lg font-semibold">
                <Icon name="zone-upgrade" /> What the Smithy swaps
            </h2>
            <p class="mb-3 text-sm text-stone-600">Each upgrade and the card it replaces.</p>
            <ul class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white text-sm">
                <li v-for="pair in upgradePairs" :key="pair.upgrade_slug" class="flex items-center gap-3 px-4 py-2">
                    <span class="text-stone-600">{{ pair.replaces ?? pair.replaces_slug ?? 'nothing' }}</span>
                    <Icon name="zone-upgrade" class="text-stone-400" />
                    <span class="font-medium text-stone-900">{{ pair.upgrade }}</span>
                </li>
            </ul>
        </section>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :position="zoom.position"
        :total="zoom.total"
        :edit-href="zoom.kind === 'player' ? `/player-cards/${zoom.card.id}/edit` : `/characters/${character.slug}/edit`"
        :caption="zoom.card.name"
        @close="zoom.close()"
        @step="zoom.step($event)"
    />
</template>
