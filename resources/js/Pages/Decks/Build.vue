<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import Icon from '../../Components/Icon.vue';
import HirelingSummary from '../../Components/HirelingSummary.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    characters: { type: Array, default: () => [] },
    domains: { type: Array, default: () => [] },
    character: { type: Object, default: null },
    domain: { type: Object, default: null },
    pool: { type: Array, default: () => [] },
    take: { type: Object, default: () => ({}) },
    has_neutral_pool: { type: Boolean, default: false },
    signature: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    warnings: { type: Array, default: () => [] },
});

const query = (overrides = {}) => ({
    character: props.character?.slug ?? undefined,
    domain: props.domain?.slug ?? undefined,
    take: props.take,
    ...overrides,
});

const reload = (overrides) =>
    router.get('/decks', query(overrides), { preserveState: true, preserveScroll: true, replace: true });

// Changing either side starts the picking over: the cards of one domain mean
// nothing in another.
const pickCharacter = (slug) => reload({ character: slug === props.character?.slug ? undefined : slug });
const pickDomain = (slug) => reload({ domain: slug === props.domain?.slug ? undefined : slug, take: {} });

const setTake = (card, count) => {
    const take = { ...props.take };
    // card.limit is the pool's print run, or the copy cap when that is tighter.
    const next = Math.max(0, Math.min(count, card.limit));

    if (next === 0) {
        delete take[card.slug];
    } else {
        take[card.slug] = next;
    }

    reload({ take });
};

const fillFirst = () => {
    // A starting point to edit, not a recommendation: the cards in pool order
    // until the slots are full.
    const take = {};
    let left = props.stats.rule?.domain ?? 0;

    for (const card of props.pool) {
        if (left <= 0) break;
        const n = Math.min(card.limit, left);
        take[card.slug] = n;
        left -= n;
    }

    reload({ take });
};

const slotsLeft = computed(() => (props.stats.rule?.domain ?? 0) - (props.stats.domain_total ?? 0));
const maxOmen = computed(() => Math.max(1, ...(props.stats.omen_curve ?? []).map((b) => b.count)));
const maxGold = computed(() => Math.max(1, ...(props.stats.gold_curve ?? []).map((b) => b.count)));

const printHref = computed(() => {
    const parts = [`character=${props.character?.slug ?? ''}`, `domain=${props.domain?.slug ?? ''}`];
    for (const [slug, n] of Object.entries(props.take)) parts.push(`take[${slug}]=${n}`);
    return `/print/deck?${parts.join('&')}`;
});

const entries = (object) => Object.entries(object ?? {});

const zoom = useCardZoom('player');
</script>

<template>
    <Head title="Deck builder" />

    <PageHeader
        title="Deck builder"
        subtitle="A deck is a character plus a domain. Pick both, then take the domain cards this deck carries."
    >
        <template #actions>
            <a v-if="character || domain" :href="printHref" class="btn-ghost"><Icon name="print" /> Print this deck</a>
            <Link href="/decks" class="btn-ghost">Start again</Link>
        </template>
    </PageHeader>

    <div class="grid gap-8 px-6 py-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <div class="space-y-5">
            <!-- 1. The character -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="character" class="text-stone-500" /> Character
                </h2>
                <p class="mb-3 text-sm text-stone-600">Brings {{ stats.rule?.signature ?? 20 }} signature cards.</p>

                <div v-if="characters.length" class="space-y-1">
                    <button
                        v-for="option in characters"
                        :key="option.slug"
                        type="button"
                        class="pick"
                        :class="{ 'pick-on': character?.slug === option.slug }"
                        @click="pickCharacter(option.slug)"
                    >
                        <span class="font-medium">{{ option.name }}</span>
                        <span class="text-xs text-stone-500">{{ option.signature_count }} cards</span>
                    </button>
                </div>
                <p v-else class="text-sm text-stone-600">
                    No characters yet. <Link href="/characters/create" class="underline">Make one.</Link>
                </p>
            </div>

            <!-- 2. The domain -->
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 flex items-center gap-2 font-serif text-base font-semibold">
                    <Icon name="domain" class="text-stone-500" /> Domain
                </h2>
                <p class="mb-3 text-sm text-stone-600">
                    Any domain goes with any character. {{ stats.rule?.domain ?? 20 }} of its cards join the deck.
                </p>

                <div v-if="domains.length" class="space-y-1">
                    <button
                        v-for="option in domains"
                        :key="option.slug"
                        type="button"
                        class="pick"
                        :class="{ 'pick-on': domain?.slug === option.slug }"
                        @click="pickDomain(option.slug)"
                    >
                        <span class="flex items-center gap-1.5 font-medium">
                            <Icon :name="option.is_neutral ? 'neutral' : 'domain'" class="text-stone-500" />
                            {{ option.name }}
                        </span>
                        <span class="text-xs text-stone-500">{{ option.pool_size }} cards</span>
                    </button>
                </div>
                <p v-else class="text-sm text-stone-600">
                    No domains yet. <Link href="/domains/create" class="underline">Make one.</Link>
                </p>
            </div>

            <!-- 3. What it adds up to -->
            <div v-if="character || domain" class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">This deck</h2>
                <dl class="space-y-1 text-sm text-stone-700">
                    <div class="flex justify-between">
                        <dt>Signature</dt>
                        <dd :class="stats.signature_total === stats.rule?.signature ? '' : 'font-semibold text-amber-800'">
                            {{ stats.signature_total }} / {{ stats.rule?.signature }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt>Domain</dt>
                        <dd :class="stats.domain_total === stats.rule?.domain ? '' : 'font-semibold text-amber-800'">
                            {{ stats.domain_total }} / {{ stats.rule?.domain }}
                        </dd>
                    </div>
                    <div class="flex justify-between border-t border-stone-200 pt-1 font-semibold">
                        <dt>Deck</dt>
                        <dd>{{ stats.deck_total }} / {{ stats.deck_rule }}</dd>
                    </div>
                    <div class="flex justify-between text-stone-600">
                        <dt>Outside the deck</dt>
                        <dd>{{ stats.kit_total }} kit · {{ stats.upgrade_total }} upgrades</dd>
                    </div>
                </dl>
            </div>
        </div>

        <div class="min-w-0 space-y-8">
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

            <p v-if="!character || !domain" class="rounded border border-dashed border-stone-300 p-8 text-center text-sm text-stone-600">
                <span v-if="!character && !domain">Pick a character and a domain to start a deck.</span>
                <span v-else-if="!character">Now pick a character.</span>
                <span v-else>Now pick a domain — any of them will do.</span>
            </p>

            <template v-else>
                <!-- The curves across the whole 40, which is what a player draws from -->
                <section class="grid gap-4 lg:grid-cols-3">
                    <div class="rounded-lg border border-stone-300 bg-white p-4">
                        <h2 class="mb-2 font-serif text-base font-semibold">Omen icons</h2>
                        <p class="mb-2 text-xs text-stone-600">Across the whole deck, both halves together.</p>
                        <ul class="space-y-1 text-sm">
                            <li v-for="bucket in stats.omen_curve" :key="bucket.value" class="flex items-center gap-2">
                                <span class="flex w-12 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="omen" /></span>
                                <span class="h-3 rounded bg-stone-800" :style="{ width: `${(bucket.count / maxOmen) * 100}px` }" />
                                <span class="text-stone-600">{{ bucket.count }}</span>
                            </li>
                            <li v-if="!stats.omen_curve?.length" class="text-stone-500">Nothing in the deck yet.</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-stone-300 bg-white p-4">
                        <h2 class="mb-2 font-serif text-base font-semibold">Gold cost</h2>
                        <p class="mb-2 text-xs text-stone-600">Against the {{ character.gold_per_round }} a round this character generates.</p>
                        <ul class="space-y-1 text-sm">
                            <li v-for="bucket in stats.gold_curve" :key="bucket.value" class="flex items-center gap-2">
                                <span class="flex w-12 shrink-0 items-center gap-1 text-stone-600">{{ bucket.value }} <Icon name="gold" /></span>
                                <span class="h-3 rounded bg-amber-700" :style="{ width: `${(bucket.count / maxGold) * 100}px` }" />
                                <span class="text-stone-600">{{ bucket.count }}</span>
                            </li>
                            <li v-if="!stats.gold_curve?.length" class="text-stone-500">Nothing in the deck yet.</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-stone-300 bg-white p-4">
                        <h2 class="mb-2 font-serif text-base font-semibold">Types and keywords</h2>
                        <p class="text-sm text-stone-700">
                            <span v-for="([type, count], i) in entries(stats.types)" :key="type">
                                <span v-if="i"> · </span>{{ count }} {{ type }}
                            </span>
                            <span v-if="!entries(stats.types).length" class="text-stone-500">Nothing in the deck yet.</span>
                        </p>
                        <HirelingSummary :hirelings="stats.hirelings" />
                        <p class="mt-2 text-sm text-stone-700">
                            <span v-for="([word, count], i) in entries(stats.keywords)" :key="word">
                                <span v-if="i"> · </span>{{ word }} ×{{ count }}
                            </span>
                            <span v-if="!entries(stats.keywords).length" class="text-stone-500">No keywords.</span>
                        </p>
                        <p class="mt-2 text-sm text-stone-600">
                            {{ stats.start_zones?.deck ?? 0 }} start in the deck · {{ stats.start_zones?.shop ?? 0 }} in the shop
                        </p>
                    </div>
                </section>

                <!-- Picking the domain half -->
                <section>
                    <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                        <div>
                            <h2 class="font-serif text-lg font-semibold">
                                Take from {{ domain.name }}<span v-if="has_neutral_pool"> and the colourless pool</span>
                            </h2>
                            <p class="text-sm" :class="slotsLeft === 0 ? 'text-stone-600' : 'text-amber-800'">
                                <span v-if="slotsLeft > 0">{{ slotsLeft }} still to pick</span>
                                <span v-else-if="slotsLeft < 0">{{ -slotsLeft }} too many</span>
                                <span v-else>All {{ stats.rule?.domain }} picked.</span>
                                <span class="text-stone-600">
                                    · {{ stats.pool_left }} of {{ stats.pool_total }} left in the pool
                                    <span v-if="stats.max_copies"> · at most {{ stats.max_copies }} of any one card</span>
                                </span>
                            </p>
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost" @click="fillFirst">Fill from the top</button>
                            <button type="button" class="btn-ghost" @click="reload({ take: {} })">Clear</button>
                        </div>
                    </div>

                    <div v-if="pool.length" class="flex flex-wrap gap-4">
                        <div v-for="(card, index) in pool" :key="card.id" class="w-[200px]">
                            <button type="button" class="card-button" :class="{ 'opacity-45': !card.taken }" @click="zoom.open(pool, index, 'player')">
                                <CardPreview :card="card" kind="player" />
                            </button>

                            <div class="mt-1 flex items-center gap-2 text-xs text-stone-700">
                                <button type="button" class="step" :disabled="!card.taken" @click="setTake(card, card.taken - 1)">−</button>
                                <span class="font-medium tabular-nums" :title="card.limit < card.qty ? `The pool prints ${card.qty}; a deck takes at most ${card.limit}.` : null">
                                    {{ card.taken }} of {{ card.limit }}
                                </span>
                                <button type="button" class="step" :disabled="card.taken >= card.limit" @click="setTake(card, card.taken + 1)">+</button>
                                <Link :href="`/player-cards/${card.id}/edit`" class="ml-auto underline hover:text-stone-900">
                                    <Icon name="edit" /> Edit
                                </Link>
                            </div>
                        </div>
                    </div>

                    <p v-else class="rounded border border-dashed border-stone-300 p-6 text-center text-sm text-stone-600">
                        {{ domain.name }} has no cards in its pool yet.
                        <Link :href="`/domains/${domain.slug}`" class="underline">Add some.</Link>
                    </p>
                </section>

                <!-- The character's half, listed: it is already drawn on its own page -->
                <section>
                    <h2 class="mb-1 font-serif text-lg font-semibold">{{ character.name }} brings</h2>
                    <p class="mb-3 text-sm text-stone-600">
                        The signature half, plus a kit and upgrades that sit outside the deck.
                        <Link :href="`/characters/${character.slug}`" class="underline">See the cards.</Link>
                    </p>

                    <ul class="grid gap-x-6 rounded-lg border border-stone-300 bg-white p-4 text-sm text-stone-700 sm:grid-cols-2 lg:grid-cols-3">
                        <li v-for="card in signature" :key="card.id" class="flex items-center gap-2 py-0.5">
                            <span v-if="card.qty > 1" class="text-stone-500">×{{ card.qty }}</span>
                            <Icon :name="card.type" class="text-stone-400" />
                            <span class="min-w-0 truncate">{{ card.name }}</span>
                            <span class="ml-auto shrink-0 text-xs text-stone-500">
                                {{ card.gold_cost }}<Icon name="gold" />
                                <span v-if="card.omen_icons"> · {{ card.omen_icons }}<Icon name="omen" /></span>
                            </span>
                        </li>
                        <li v-if="!signature.length" class="text-stone-500">No signature cards yet.</li>
                    </ul>
                </section>
            </template>
        </div>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        kind="player"
        :position="zoom.position"
        :total="zoom.total"
        :edit-href="`/player-cards/${zoom.card.id}/edit`"
        :caption="zoom.card.name"
        @close="zoom.close()"
        @step="zoom.step($event)"
    />
</template>

<style scoped>
.pick {
    display: flex;
    width: 100%;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem;
    border-radius: 0.375rem;
    border: 1px solid rgb(214 211 209);
    padding: 0.375rem 0.625rem;
    text-align: left;
    font-size: 0.875rem;
}
.pick:hover {
    border-color: rgb(168 162 158);
    background: rgb(250 250 249);
}
.pick-on {
    border-color: rgb(180 83 9);
    background: rgb(254 243 199);
    color: rgb(120 53 15);
}
.step {
    width: 1.5rem;
    border-radius: 0.25rem;
    border: 1px solid rgb(214 211 209);
    line-height: 1.25rem;
}
.step:hover:not(:disabled) {
    border-color: rgb(120 113 108);
    background: rgb(245 245 244);
}
.step:disabled {
    opacity: 0.4;
}
</style>
