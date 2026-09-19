<script setup>
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '../Components/PageHeader.vue';
import Icon from '../Components/Icon.vue';

defineProps({
    scenarios: { type: Array, default: () => [] },
    characters: { type: Array, default: () => [] },
    domains: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
});
</script>

<template>
    <Head title="Overview" />

    <PageHeader title="Card Forge" subtitle="Edit the cards, edit the rules, print the game.">
        <template #actions>
            <Link href="/decks" class="btn-ghost"><Icon name="cards" /> Deck builder</Link>
            <Link href="/scenarios/create" class="btn-primary"><Icon name="add" /> New scenario</Link>
        </template>
    </PageHeader>

    <div class="space-y-8 px-6 py-6">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="stat">
                <p class="stat-value">{{ stats.placeholder_values }} / {{ stats.config_values }}</p>
                <p class="stat-label">tunable numbers still placeholders</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.placeholder_cards }} · {{ stats.placeholder_player_cards }}</p>
                <p class="stat-label">entity · player cards flagged as placeholder</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ scenarios.length }} · {{ stats.characters }} · {{ stats.domains }}</p>
                <p class="stat-label">scenarios · characters · domains</p>
            </div>
            <div class="stat">
                <p class="stat-value">{{ stats.rule_documents }}</p>
                <p class="stat-label">rules documents</p>
            </div>
        </section>

        <section>
            <h2 class="mb-3 flex items-center gap-2 font-serif text-lg font-semibold text-stone-900"><Icon name="scenario" /> Scenarios</h2>

            <div v-if="scenarios.length" class="grid gap-4 lg:grid-cols-2">
                <article v-for="scenario in scenarios" :key="scenario.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <Link :href="`/scenarios/${scenario.slug}`" class="font-serif text-lg font-semibold text-stone-900 hover:text-amber-800">
                                {{ scenario.name }}
                            </Link>
                            <p class="text-xs uppercase tracking-wider text-stone-500">{{ scenario.entity_type }}</p>
                        </div>
                        <Link :href="`/print/${scenario.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
                    </div>

                    <p v-if="scenario.overview" class="mt-2 text-sm leading-relaxed text-stone-700">{{ scenario.overview }}</p>
                    <p v-if="scenario.status" class="mt-2 text-xs italic text-amber-800">{{ scenario.status }}</p>

                    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 border-t border-stone-200 pt-3 text-xs text-stone-600">
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.deck_size }}</dt> <dd class="inline">cards in the entity deck</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.entity_cards_count }}</dt> <dd class="inline">unique</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.board_cards_count }}</dt> <dd class="inline">board cards</dd></div>
                        <div><dt class="inline font-semibold text-stone-900">{{ scenario.story_beats_count }}</dt> <dd class="inline">beats</dd></div>
                    </dl>
                </article>
            </div>

            <p v-else class="rounded border border-dashed border-stone-300 p-6 text-sm text-stone-600">
                No scenarios yet. Run <code>php artisan design:import</code> to load the design folder, or create one.
            </p>
        </section>

        <section v-if="characters.length">
            <div class="mb-3 flex items-end justify-between gap-3">
                <h2 class="flex items-center gap-2 font-serif text-lg font-semibold text-stone-900"><Icon name="character" /> Characters</h2>
                <Link href="/characters" class="text-sm text-stone-600 underline hover:text-stone-900">All characters</Link>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <article v-for="character in characters" :key="character.slug" class="rounded-lg border border-stone-300 bg-white p-4 shadow-sm">
                    <div class="flex items-start justify-between gap-3">
                        <Link :href="`/characters/${character.slug}`" class="font-serif text-lg font-semibold text-stone-900 hover:text-amber-800">
                            {{ character.name }}
                        </Link>
                        <Link :href="`/print/character/${character.slug}`" class="btn-ghost"><Icon name="print" /> Print</Link>
                    </div>

                    <p v-if="character.identity" class="mt-2 text-sm leading-relaxed text-stone-700">{{ character.identity }}</p>

                    <p class="mt-3 border-t border-stone-200 pt-3 text-xs text-stone-600">
                        <strong class="text-stone-900">{{ character.signature_count }}</strong> signature cards ·
                        {{ character.health }} health · hand {{ character.hand_size }} ·
                        {{ character.gold_per_round }} gold a round
                    </p>
                </article>
            </div>
        </section>

        <section v-if="domains.length">
            <div class="mb-3 flex items-end justify-between gap-3">
                <h2 class="flex items-center gap-2 font-serif text-lg font-semibold text-stone-900"><Icon name="domain" /> Domains</h2>
                <Link href="/domains" class="text-sm text-stone-600 underline hover:text-stone-900">All domains</Link>
            </div>

            <ul class="divide-y divide-stone-200 overflow-hidden rounded-lg border border-stone-300 bg-white text-sm">
                <li v-for="domain in domains" :key="domain.slug" class="flex flex-wrap items-center gap-x-3 gap-y-1 px-4 py-2">
                    <Icon :name="domain.is_neutral ? 'neutral' : 'domain'" class="text-stone-500" />
                    <Link :href="`/domains/${domain.slug}`" class="font-medium text-stone-900 underline hover:text-amber-800">
                        {{ domain.name }}
                    </Link>
                    <span class="text-stone-600">{{ domain.pool_size }} cards</span>
                    <span v-if="domain.identity" class="min-w-0 flex-1 truncate text-stone-600">{{ domain.identity }}</span>
                </li>
            </ul>
        </section>

        <section class="rounded-lg border border-stone-300 bg-white p-4">
            <h2 class="mb-2 font-serif text-lg font-semibold text-stone-900">Round trip</h2>
            <ol class="list-inside list-decimal space-y-1 text-sm text-stone-700">
                <li><code>php artisan design:import</code> loads <code>design/</code> into the editor.</li>
                <li>Edit cards, beats, board, town and rules here.</li>
                <li><code>php artisan design:export</code> writes it all back to <code>design/</code>.</li>
                <li>Commit <code>design/</code> — git keeps the design history.</li>
            </ol>
        </section>
    </div>
</template>
