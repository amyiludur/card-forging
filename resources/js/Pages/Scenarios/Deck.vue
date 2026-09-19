<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import PageHeader from '../../Components/PageHeader.vue';
import CardPreview from '../../Components/CardPreview.vue';
import CardZoom from '../../Components/CardZoom.vue';
import { useCardZoom } from '../../useCardZoom';

const props = defineProps({
    scenario: { type: Object, required: true },
    available: { type: Array, default: () => [] },
    others: { type: Array, default: () => [] },
    chosen: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    incompatible: { type: Array, default: () => [] },
    countMatchesRules: { type: Boolean, default: false },
    startingCards: { type: Array, default: () => [] },
    beatCards: { type: Array, default: () => [] },
});

const reload = (modules) =>
    router.get(`/scenarios/${props.scenario.slug}/deck`, { modules }, { preserveState: true, preserveScroll: true, replace: true });

const toggle = (slug) =>
    reload(props.chosen.includes(slug) ? props.chosen.filter((s) => s !== slug) : [...props.chosen, slug]);

const maxOmen = computed(() => Math.max(1, ...props.stats.omen_curve.map((b) => b.count)));
const maxType = computed(() => Math.max(1, ...props.stats.types.map((t) => t.count)));
const arrowTotal = computed(() => Math.max(1, props.stats.arrows.top + props.stats.arrows.bottom));

const zoom = useCardZoom();
</script>

<template>
    <Head :title="`${scenario.name} — deck`" />

    <PageHeader
        :title="`${scenario.name} — deck assembly`"
        subtitle="Pick the modules for this play and see the deck they make."
    >
        <template #actions>
            <Link :href="`/scenarios/${scenario.slug}/storyline?${chosen.map((m) => `modules[]=${m}`).join('&')}`" class="btn-ghost">
                Storyline preview
            </Link>
            <Link :href="`/scenarios/${scenario.slug}`" class="btn-ghost">Back to scenario</Link>
        </template>
    </PageHeader>

    <div class="grid gap-8 px-6 py-6 xl:grid-cols-[22rem,minmax(0,1fr)]">
        <div class="space-y-5">
            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-1 font-serif text-base font-semibold">Modules</h2>
                <p class="mb-3 text-sm" :class="countMatchesRules ? 'text-emerald-700' : 'text-amber-800'">
                    {{ chosen.length }} of {{ scenario.modules_required }} chosen
                    <span v-if="!countMatchesRules">
                        — this scenario asks for {{ scenario.modules_required }}.
                    </span>
                </p>

                <div class="space-y-1.5">
                    <label
                        v-for="module in available"
                        :key="module.slug"
                        class="flex cursor-pointer items-start gap-2 rounded border p-2 text-sm"
                        :class="chosen.includes(module.slug) ? 'border-stone-900 bg-stone-50' : 'border-stone-200 hover:border-stone-400'"
                    >
                        <input
                            type="checkbox"
                            :checked="chosen.includes(module.slug)"
                            class="mt-0.5 rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="toggle(module.slug)"
                        >
                        <span class="min-w-0 flex-1">
                            <span class="font-medium">{{ module.name }}</span>
                            <span v-if="module.recommended" class="ml-1 text-[11px] uppercase tracking-wide text-amber-800">recommended</span>
                            <span class="block text-xs text-stone-500">{{ module.deck_size }} cards</span>
                        </span>
                        <span v-if="module.set_icon" class="shrink-0 rounded border border-stone-700 px-1 py-0.5 font-mono text-[10px] font-bold">
                            {{ module.set_icon }}
                        </span>
                    </label>
                </div>

                <div v-if="others.length" class="mt-3 border-t border-stone-200 pt-3">
                    <p class="field-micro mb-1.5">Not listed for this scenario</p>
                    <label
                        v-for="module in others"
                        :key="module.slug"
                        class="flex cursor-pointer items-center gap-2 rounded border border-stone-200 p-2 text-sm hover:border-stone-400"
                    >
                        <input
                            type="checkbox"
                            :checked="chosen.includes(module.slug)"
                            class="rounded border-stone-400 text-amber-700 focus:ring-amber-600"
                            @change="toggle(module.slug)"
                        >
                        <span class="flex-1">{{ module.name }}</span>
                    </label>
                </div>

                <p v-if="scenario.module_note" class="mt-3 text-xs italic text-stone-500">{{ scenario.module_note }}</p>
            </div>

            <div v-if="incompatible.length" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                <strong>{{ incompatible.join(', ') }}</strong>
                {{ incompatible.length === 1 ? 'is' : 'are' }} not listed as compatible with this scenario. You can still
                play {{ incompatible.length === 1 ? 'it' : 'them' }} — this is a warning, not a rule.
            </div>

            <div class="rounded-lg border border-stone-300 bg-white p-4">
                <h2 class="mb-2 font-serif text-base font-semibold">Where the cards come from</h2>
                <div v-for="source in stats.by_source" :key="source.name" class="flex items-baseline justify-between gap-3 py-0.5 text-sm">
                    <span>
                        {{ source.name }}
                        <span v-if="source.set_icon" class="ml-1 font-mono text-[10px] text-stone-500">{{ source.set_icon }}</span>
                    </span>
                    <span class="font-mono text-stone-700">{{ source.count }}</span>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-3">
                <div class="stat">
                    <p class="stat-value">{{ stats.starting_total }}</p>
                    <p class="stat-label">cards in the starting deck</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ stats.beat_total }}</p>
                    <p class="stat-label">added later by story beats</p>
                </div>
                <div class="stat">
                    <p class="stat-value">{{ stats.total }}</p>
                    <p class="stat-label">cards in total</p>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-lg border border-stone-300 bg-white p-4">
                    <h2 class="mb-3 font-serif text-base font-semibold">Omen curve</h2>
                    <div v-for="bucket in stats.omen_curve" :key="bucket.cost" class="mb-1.5 flex items-center gap-2 text-sm">
                        <span class="w-5 shrink-0 text-right font-mono font-semibold">{{ bucket.cost }}</span>
                        <span class="text-stone-400">◆</span>
                        <span class="h-3.5 rounded-sm bg-stone-800" :style="{ width: `${(bucket.count / maxOmen) * 100}%` }" />
                        <span class="text-xs text-stone-600">{{ bucket.count }}</span>
                    </div>
                </div>

                <div class="rounded-lg border border-stone-300 bg-white p-4">
                    <h2 class="mb-3 font-serif text-base font-semibold">Arrows</h2>
                    <div class="mb-1.5 flex items-center gap-2 text-sm">
                        <span class="w-14 shrink-0 text-right">▲ top</span>
                        <span class="h-3.5 rounded-sm bg-stone-800" :style="{ width: `${(stats.arrows.top / arrowTotal) * 100}%` }" />
                        <span class="text-xs text-stone-600">{{ stats.arrows.top }}</span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <span class="w-14 shrink-0 text-right">▼ bottom</span>
                        <span class="h-3.5 rounded-sm bg-stone-500" :style="{ width: `${(stats.arrows.bottom / arrowTotal) * 100}%` }" />
                        <span class="text-xs text-stone-600">{{ stats.arrows.bottom }}</span>
                    </div>
                    <p class="mt-2 text-xs text-stone-500">Which half of the next card each card points at.</p>
                </div>

                <div class="rounded-lg border border-stone-300 bg-white p-4">
                    <h2 class="mb-3 font-serif text-base font-semibold">Types</h2>
                    <div v-for="type in stats.types" :key="type.type" class="mb-1.5 flex items-center gap-2 text-sm">
                        <span class="w-16 shrink-0 truncate text-right">{{ type.type }}</span>
                        <span class="h-3.5 rounded-sm bg-stone-700" :style="{ width: `${(type.count / maxType) * 100}%` }" />
                        <span class="text-xs text-stone-600">{{ type.count }}</span>
                    </div>
                </div>
            </div>

            <section>
                <h2 class="mb-3 font-serif text-lg font-semibold">Starting deck</h2>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-6">
                    <div v-for="(card, index) in startingCards" :key="card.id">
                        <button
                            type="button"
                            class="card-button"
                            :aria-label="`View ${card.name || 'untitled card'} at full size`"
                            @click="zoom.open(startingCards, index)"
                        >
                            <CardPreview :card="card" kind="entity" :width="140" />
                        </button>
                        <p class="mt-1 text-center text-xs text-stone-600">×{{ card.qty }}</p>
                    </div>
                </div>
            </section>

            <section v-if="beatCards.length">
                <h2 class="mb-1 font-serif text-lg font-semibold">Added later by story beats</h2>
                <p class="mb-3 text-sm text-stone-600">Not in the deck at setup.</p>
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 xl:grid-cols-6">
                    <div v-for="(card, index) in beatCards" :key="card.id">
                        <button
                            type="button"
                            class="card-button"
                            :aria-label="`View ${card.name || 'untitled card'} at full size`"
                            @click="zoom.open(beatCards, index)"
                        >
                            <CardPreview :card="card" kind="entity" :width="140" />
                        </button>
                        <p class="mt-1 text-center text-xs text-stone-600">
                            ×{{ card.qty }}<span v-if="card.added_by_beat"> · beat {{ card.added_by_beat.order }}</span>
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <CardZoom
        v-if="zoom.card"
        :card="zoom.card"
        :kind="zoom.kind"
        :edit-href="`/cards/${zoom.card.id}/edit`"
        :caption="`${zoom.card.name || 'Untitled card'} · ×${zoom.card.qty}`"
        :position="zoom.position"
        :total="zoom.total"
        @close="zoom.close()"
        @step="zoom.step"
    />
</template>
