<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { renderMarkup } from '../../markup';

/**
 * The setup screen's Tale block, when the game is a scenario of a playthrough:
 * which scenario of the Tale this is, the mode and its text, the story read
 * before it, the log so far, and the rewards each seat is about to be dealt.
 *
 * It shows; it applies nothing. The rewards are the one thing the table deals
 * — to the first seat playing each character — and the rest of the log is for
 * the players to act on.
 */
const props = defineProps({
    tale: { type: Object, required: true },
    // The decks in the lineup, in seating order.
    seats: { type: Array, default: () => [] },
    characterBySlug: { type: Object, required: true },
    playerCards: { type: Object, default: () => ({}) },
});

const cardName = (id) => props.playerCards[id]?.name ?? `#${id}`;

// An entry's name is card markup ("Damage kept {damage}"), drawn as the
// printed log sheet draws it.
const page = usePage();
const entryName = (text) => renderMarkup(text ?? '', {
    icons: page.props.markup?.icons ?? {},
    paths: page.props.markup?.paths ?? {},
    config: page.props.markup?.config ?? {},
    keywords: page.props.markup?.keywords ?? {},
    cards: page.props.markup?.cards ?? {},
});

// Each seat's rewards: only the first seat playing a character gets them.
const rewardRows = computed(() => props.seats.map((deck, index) => {
    const first = props.seats.findIndex((d) => d.character === deck.character) === index;

    return {
        key: deck.key,
        name: props.characterBySlug[deck.character]?.name ?? deck.character,
        rewards: first ? (deck.rewards ?? []).map(cardName) : [],
        twice: !first,
    };
}));

const seatedTwice = computed(() => rewardRows.value.some((row) => row.twice));

// The log so far, for the characters actually sitting down.
const seatedSlugs = computed(() => new Set(props.seats.map((d) => d.character)));
const characterLog = computed(() => props.tale.grid.characters.filter((c) => seatedSlugs.value.has(c.slug)));
const steps = computed(() => props.tale.grid.steps);
const hasLog = computed(() => props.tale.grid.party.length || characterLog.value.length);
</script>

<template>
    <section class="mb-6 rounded-lg border border-sky-400/30 bg-sky-950/40 p-4 text-stone-200">
        <p class="table-label">{{ tale.tale.name }} · {{ tale.run.name }}</p>
        <h2 class="font-serif text-xl font-semibold text-stone-50">Scenario {{ tale.step.number }} of the Tale</h2>

        <div v-if="tale.mode" class="mt-2 text-sm">
            <span class="font-semibold text-stone-50">Mode: {{ tale.mode.name }}</span>
            <div v-if="tale.mode.text_html" class="rules-prose mt-1 text-stone-300" v-html="tale.mode.text_html" />
        </div>

        <div v-if="tale.step.intro_html" class="rules-prose mt-3 text-sm text-stone-200" v-html="tale.step.intro_html" />
        <p v-if="tale.step.notes_html" class="mt-2 text-sm italic text-stone-300">Setup: <span v-html="tale.step.notes_html" /></p>

        <template v-if="hasLog && steps.length">
            <h3 class="table-label mb-1 mt-4">The log so far</h3>
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr>
                            <th class="border border-white/15 p-1" />
                            <th v-for="step in steps" :key="step.id" class="border border-white/15 p-1 font-medium" :class="step.recorded ? 'text-stone-100' : 'text-stone-500'">
                                {{ step.number }}. {{ step.scenario }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in tale.grid.party" :key="`p-${row.entry_id}`">
                            <th class="border border-white/15 p-1 text-left font-medium" v-html="entryName(row.name)" />
                            <td v-for="(cell, i) in row.cells" :key="i" class="border border-white/15 p-1 text-center">{{ cell }}</td>
                        </tr>
                        <template v-for="character in characterLog" :key="character.slug">
                            <tr><th :colspan="steps.length + 1" class="border border-white/15 bg-black/30 p-1 text-left font-serif">{{ character.name }}</th></tr>
                            <tr v-for="row in character.rows" :key="`${character.slug}-${row.entry_id}`">
                                <th class="border border-white/15 p-1 pl-3 text-left font-medium" v-html="entryName(row.name)" />
                                <td v-for="(cell, i) in row.cells" :key="i" class="border border-white/15 p-1 text-center">{{ cell }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <p class="mt-1 text-[11px] text-stone-400">What the log means for this game is yours to apply.</p>
        </template>

        <h3 class="table-label mb-1 mt-4">Rewards this game</h3>
        <ul v-if="seats.length" class="space-y-0.5 text-sm">
            <li v-for="row in rewardRows" :key="row.key">
                <span class="font-medium text-stone-50">{{ row.name }}</span>:
                <template v-if="row.rewards.length">{{ row.rewards.join(', ') }}</template>
                <span v-else class="text-stone-400">{{ row.twice ? 'none (seated twice)' : 'none earned yet' }}</span>
            </li>
        </ul>
        <p v-else class="text-sm text-stone-400">Seat someone to see what they are dealt.</p>
        <p v-if="seatedTwice" class="mt-1 text-[11px] text-amber-300">Seated twice — rewards go to the first seat only.</p>
        <p v-if="tale.partyRewards.length" class="mt-2 text-sm">
            Party rewards: {{ tale.partyRewards.map(cardName).join(', ') }}.
            <span class="text-stone-400">Party rewards are not dealt; put them where they go.</span>
        </p>
    </section>
</template>
