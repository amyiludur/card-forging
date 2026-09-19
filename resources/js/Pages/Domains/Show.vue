<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import Icon from '../../Components/Icon.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    domain: { type: Object, required: true },
    cards: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    upgradePairs: { type: Array, default: () => [] },
    warnings: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
});

// The two lists a domain file is written in.
const sections = computed(() => [
    {
        role: 'domain',
        title: 'Pool',
        blurb: 'The cards a character can take into a domain slot.',
        cards: props.cards.filter((c) => c.role === 'domain'),
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

const deleteCard = (card) => {
    if (window.confirm(`Delete ${card.name}?`)) {
        router.delete(`/player-cards/${card.id}`, { preserveScroll: true });
    }
};

const deleteDomain = () => {
    if (window.confirm(`Delete ${props.domain.name} and its ${props.cards.length} cards?`)) {
        router.delete(`/domains/${props.domain.slug}`);
    }
};

const entries = (object) => Object.entries(object ?? {});
</script>

<template>
    <Head :title="domain.name" />

    <PageHeader :title="domain.name" :subtitle="domain.identity">
        <template #actions>
            <Link :href="`/decks?domain=${domain.slug}`" class="btn-ghost"><Icon name="zone-deck" /> Build a deck</Link>
            <Link :href="`/print/domain/${domain.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
            <Link :href="`/domains/${domain.slug}/cards/create`" class="btn-ghost"><Icon name="add" /> New card</Link>
            <Link :href="`/domains/${domain.slug}/edit`" class="btn-primary"><Icon name="edit" /> Edit domain</Link>
        </template>

        <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-stone-700">
            <span class="flex items-center gap-2">
                <Icon :name="domain.is_neutral ? 'neutral' : 'domain'" class="text-stone-500" />
                {{ domain.is_neutral ? 'Colourless pool' : 'Domain' }}
            </span>
            <span v-if="domain.set_icon" class="rounded border border-stone-800 px-1.5 py-0.5 font-mono text-xs font-bold">
                {{ domain.set_icon }}
            </span>
            <span><strong>{{ stats.pool_total }}</strong> cards in the pool</span>
            <span class="text-stone-500">Any character can take this</span>
            <span v-if="domain.status" class="italic text-amber-800">{{ domain.status }}</span>
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
                <p class="stat-value">{{ stats.pool_total }}</p>
                <p class="stat-label">
                    cards in the pool, counted by copy<span v-if="stats.domain_slots">. A character takes {{ stats.domain_slots }}</span>
                </p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.choice }}</p>
                <p class="stat-label">
                    left over after a deck takes its {{ stats.domain_slots }} — the choice at deck building
                </p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ startingDeck }} · {{ shopPile }}</p>
                <p class="stat-label">start in the deck · start in the shop</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.upgrade_total }}</p>
                <p class="stat-label">upgrades, set aside outside the 40</p>
            </div>
        </section>

        <section v-if="characters.length" class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                <Icon name="zone-deck" class="text-stone-500" /> Build a deck with it
            </h2>
            <p class="mb-3 text-sm text-stone-600">
                A domain belongs to no character. Pair it with one and take {{ stats.domain_slots }} of these cards.
            </p>
            <div class="flex flex-wrap gap-2">
                <Link
                    v-for="character in characters"
                    :key="character.slug"
                    :href="`/decks?character=${character.slug}&domain=${domain.slug}`"
                    class="btn-ghost"
                >
                    <Icon name="character" /> {{ character.name }}
                </Link>
            </div>
        </section>

        <p v-if="stats.fills_slots === false" class="rounded-lg border border-stone-300 bg-white p-4 text-sm text-stone-700">
            <Icon name="neutral" class="text-stone-500" />
            Neutral cards do not fill domain slots under the current rules, so nothing here can be taken.
            <Link href="/rules/config" class="underline">That is a tunable number.</Link>
        </p>

        <section class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Omen icons</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="bucket in stats.omen_curve" :key="bucket.value" class="flex items-center gap-2">
                        <span class="flex w-14 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="omen" /></span>
                        <span class="h-3 rounded bg-stone-800" :style="{ width: `${bucket.count * 10}px` }" />
                        <span class="text-stone-600">{{ bucket.count }}</span>
                    </li>
                    <li v-if="!stats.omen_curve?.length" class="text-stone-500">No cards yet.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Gold cost</h2>
                <ul class="space-y-1 text-sm">
                    <li v-for="bucket in stats.gold_curve" :key="bucket.value" class="flex items-center gap-2">
                        <span class="flex w-14 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="gold" /></span>
                        <span class="h-3 rounded bg-amber-700" :style="{ width: `${bucket.count * 10}px` }" />
                        <span class="text-stone-600">{{ bucket.count }}</span>
                    </li>
                    <li v-if="!stats.gold_curve?.length" class="text-stone-500">No cards yet.</li>
                </ul>
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Types and keywords</h2>
                <p class="text-sm text-stone-700">
                    <span v-for="([type, count], i) in entries(stats.types)" :key="type">
                        <span v-if="i"> · </span>{{ count }} {{ type }}
                    </span>
                    <span v-if="!entries(stats.types).length" class="text-stone-500">No cards yet.</span>
                </p>
                <p class="mt-2 text-sm text-stone-700">
                    <span v-for="([word, count], i) in entries(stats.keywords)" :key="word">
                        <span v-if="i"> · </span>{{ word }} ×{{ count }}
                    </span>
                    <span v-if="!entries(stats.keywords).length" class="text-stone-500">No keywords yet.</span>
                </p>
            </div>
        </section>

        <section v-for="section in sections" :key="section.role">
            <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 class="font-serif text-lg font-semibold">{{ section.title }}</h2>
                    <p class="text-sm text-stone-600">{{ section.blurb }}</p>
                </div>
                <Link :href="`/domains/${domain.slug}/cards/create?role=${section.role}`" class="btn-ghost">
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

        <section class="border-t border-stone-200 pt-6">
            <button type="button" class="text-sm text-red-700 underline hover:text-red-900" @click="deleteDomain">
                <Icon name="delete" /> Delete this domain and its cards
            </button>
            <p class="mt-1 text-xs text-stone-600">
                Its cards belong to the pool, so they go with it. Characters drawing from it simply stop doing so.
            </p>
        </section>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :position="zoom.position"
        :total="zoom.total"
        :edit-href="`/player-cards/${zoom.card.id}/edit`"
        :caption="zoom.card.name"
        @close="zoom.close()"
        @step="zoom.step($event)"
    />
</template>
