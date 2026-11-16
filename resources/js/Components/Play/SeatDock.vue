<script setup>
import { computed, ref, watch } from 'vue';
import Icon from '../Icon.vue';
import PileStack from './PileStack.vue';
import PileViewer from './PileViewer.vue';
import TableCard from './TableCard.vue';
import TownTable from './TownTable.vue';
import { openMenu } from './tableOverlays';
import { PORTRAIT, useCardFit, useWidth } from './useCardFit';
import { useDropZone } from './useTableDrag';
import { ZONE_LABELS, locate, shopRow, tuckedUnder } from '../../goldfish';

/**
 * The players' side of the table, along the bottom: a tab for every seat and
 * one seat laid out — its dials, what it has in play, its hand, and its piles.
 * Laying out only one is what lets one to four seats fit the same screen.
 *
 * Which seat is laid out is the page's `active`, a ref of its own and not part
 * of the session, so undoing a move never switches seats. The moves themselves
 * are the page's `actions` (useSeatActions), bound to that seat: a click, a
 * menu item and a drop all call the same function, and every one goes through
 * `table.act()`.
 *
 * Click does what the rules make obvious — play from hand, buy from the shop,
 * exhaust in play, draw from the deck. Right-click has the rest. Drag moves a
 * card freely; onto the in-play row from the hand it plays it, and onto a deck
 * with Shift held it goes underneath.
 */
const props = defineProps({
    party: { type: Array, required: true },
    active: { type: Number, default: 0 },
    firstPlayer: { type: Number, default: 0 },
    characterBySlug: { type: Object, required: true },
    deckByKey: { type: Object, required: true },
    fullHealth: { type: Function, required: true },
    handSize: { type: Function, required: true },
    goldPerRound: { type: Function, required: true },
    partyStyle: { type: Function, required: true },
    town: { type: Array, default: () => [] },
    districts: { type: Object, required: true },
    nameOf: { type: Function, required: true },
    table: { type: Object, required: true },
    actions: { type: Object, required: true },
    // The Actions step: hand and shop cards that can be paid for glow.
    actionsStep: { type: Boolean, default: false },
});

const emit = defineEmits(['update:active', 'open-players']);

const seat = computed(() => props.party[props.active] ?? null);
const character = computed(() => (seat.value ? props.characterBySlug[seat.value.slug] ?? null : null));
const name = computed(() => character.value?.name ?? seat.value?.slug ?? '');
const fullHealth = computed(() => (seat.value ? props.fullHealth(seat.value.slug) : 0));
const handSize = computed(() => (seat.value ? props.handSize(seat.value.slug) : 0));

const a = props.actions;
const cardOf = a.cardOf;

// The shop is a deck with only its top few face up; buying one turns up the
// next. `shopRowSize` says how many, and the rest stay face down.
const rowSize = computed(() => shopRow(props.table.config.shopRowSize));
const shopShowing = computed(() => seat.value?.zones.shop.slice(0, rowSize.value) ?? []);
const shopHidden = computed(() => Math.max(0, (seat.value?.zones.shop.length ?? 0) - shopShowing.value.length));

const adjust = (field, delta, label, max = Infinity) =>
    a.act(`${label} ${delta > 0 ? '+' : ''}${delta}`, () => {
        seat.value[field] = Math.max(0, Math.min((seat.value[field] ?? 0) + delta, max));
    });

const pouchMax = computed(() => {
    const cap = Number(props.table.config.goldPouchCapacity);
    return Number.isFinite(cap) && cap > 0 ? cap : Infinity;
});

const storeGold = () => a.act('put 1 gold in the pouch', () => { seat.value.gold -= 1; seat.value.pouch = (seat.value.pouch ?? 0) + 1; });
const takeGold = () => a.act('took 1 gold from the pouch', () => { seat.value.pouch -= 1; seat.value.gold += 1; });

// Counters a scenario or a card asks a player to keep — plague, rage, a
// Runestone's Break count. Named by the player, because which ones exist is
// the game's to say and not the table's.
const newCounter = ref('');
const addCounter = () => {
    const label = newCounter.value.trim();
    if (!label) return;
    a.act(`keeps ${label} counters`, () => seat.value.counters.push({ name: label, value: 0 }));
    newCounter.value = '';
};
const bumpCounter = (index, delta) => {
    const counter = seat.value.counters[index];
    a.act(`${counter.name} ${delta > 0 ? '+' : ''}${delta}`, () => (counter.value = Math.max(0, counter.value + delta)));
};
const dropCounter = (index) => a.act(`stopped keeping ${seat.value.counters[index].name}`, () => seat.value.counters.splice(index, 1));

const zoomIn = (list, index) => props.table.zoom(list.map(cardOf), index, 'player');

const dead = computed(() => seat.value?.current === 0);
const costLine = (card) => [card.gold_cost ? `${card.gold_cost}g` : null, card.omen_icons ? `+${card.omen_icons} omen` : null].filter(Boolean).join(' · ') || 'free';

// The trays that open over the in-play row, and the pile opened in the viewer.
// Neither is part of the game, so neither is in the session.
const tray = ref(null);
const viewing = ref(null);
const showAbility = ref(false);
const toggleTray = (which) => (tray.value = tray.value === which ? null : which);

// Another seat's piles are another seat's: switching closes what was open.
watch(() => props.active, () => {
    viewing.value = null;
    showAbility.value = false;
});

/** The page's hotkeys open and close these too. */
defineExpose({
    toggleTray,
    closeAll: () => {
        const wasOpen = !!(tray.value || viewing.value || showAbility.value);
        tray.value = null;
        viewing.value = null;
        showAbility.value = false;
        return wasOpen;
    },
});

const viewerNotes = {
    deck: 'top first',
    aside: 'upgrades waiting for the Smithy',
};

// ── What each card does when it is clicked, and what its menu offers ─────────

const menuAt = (event, { title, items }) => openMenu(event, title, items);

const view = (list, index) => ({ label: 'View full size', action: () => zoomIn(list, index) });
const upgradeItem = (instance) => {
    const to = a.upgradeOf(instance);
    return to ? { label: `Upgrade to ${cardOf(to).name}`, hint: 'Smithy', action: () => a.upgrade(instance) } : null;
};
const divider = { divider: true };

const handMenu = (instance, index) => {
    const card = cardOf(instance);
    const affordable = a.canAfford(card.gold_cost);

    return {
        title: card.name,
        items: [
            { label: 'Play', hint: affordable ? `${costLine(card)} · click` : `needs ${card.gold_cost} gold`, disabled: !affordable, action: () => a.play(instance) },
            view(seat.value.zones.hand, index),
            upgradeItem(instance),
            divider,
            ...a.moveItems(instance, 'hand'),
        ],
    };
};

const playMenu = (instance, index) => ({
    title: cardOf(instance).name,
    items: [
        { label: instance.exhausted ? 'Ready' : 'Exhaust', hint: 'click', action: () => a.toggleExhausted(instance) },
        { label: 'Counter +1', hint: `${instance.counter ?? 0} now`, action: () => a.bump(instance, 1) },
        { label: 'Counter −1', disabled: !instance.counter, action: () => a.bump(instance, -1) },
        view(seat.value.zones.play, index),
        upgradeItem(instance),
        divider,
        ...a.moveItems(instance, 'play'),
    ],
});

// A tucked card can be played "as if it were in your hand" — the Revolver's
// words, and the only thing a click does with one. Everything else is a free move.
const tuckedOf = (host) => (seat.value ? tuckedUnder(seat.value, host.uid) : []);

const tuckedMenu = (instance, host, index) => {
    const card = cardOf(instance);
    const affordable = a.canAfford(card.gold_cost);

    return {
        title: `${card.name} · under ${cardOf(host).name}`,
        items: [
            { label: 'Play', hint: affordable ? `${costLine(card)} · click` : `needs ${card.gold_cost} gold`, disabled: !affordable, action: () => a.play(instance) },
            view(tuckedOf(host), index),
            divider,
            ...a.moveItems(instance, 'tucked'),
        ],
    };
};

const shopMenu = (instance, index) => {
    const card = cardOf(instance);
    const affordable = a.canAfford(card.shop_cost);

    return {
        title: card.name,
        items: [
            {
                label: 'Buy',
                hint: affordable ? `${card.shop_cost ?? 0}g, ${a.destinationLabel()}` : `needs ${card.shop_cost} gold`,
                disabled: !affordable,
                action: () => a.buy(instance),
            },
            view(shopShowing.value, index),
            divider,
            ...a.moveItems(instance, 'shop'),
        ],
    };
};

const pileMenu = (zone) => (instance, index) => ({
    title: cardOf(instance).name,
    items: [view(seat.value.zones[zone], index), upgradeItem(instance), divider, ...a.moveItems(instance, zone)],
});

const deckMenu = () => ({
    title: `${name.value} · deck`,
    items: [
        { label: 'Draw 1', hint: 'click · D', action: () => a.draw(1) },
        { label: 'Draw from the bottom', disabled: !seat.value.zones.deck.length, action: () => a.draw(1, 'bottom') },
        { label: `Draw up to ${handSize.value}`, disabled: seat.value.zones.hand.length >= handSize.value, action: a.drawUp },
        { label: 'Shuffle', action: a.shuffleDeck },
        { label: 'Look through', hint: 'top first', disabled: !seat.value.zones.deck.length, action: () => (viewing.value = 'deck') },
        divider,
        { label: 'Ready everything in play', action: a.readyAll },
    ],
});

const shopPileMenu = () => ({
    title: `${name.value} · shop deck`,
    items: [
        { label: tray.value === 'shop' ? 'Close the shop' : 'Open the shop', hint: 'click · S', action: () => toggleTray('shop') },
        { label: 'Shuffle the shop deck', disabled: seat.value.zones.shop.length < 2, action: a.shuffleShop },
        { label: 'Look through', disabled: !seat.value.zones.shop.length, action: () => (viewing.value = 'shop') },
    ],
});

const dragOf = (zone, instance) => ({ source: 'seat', zone, uid: instance.uid });

// ── Where a card can be dropped ─────────────────────────────────────────────

const instanceOf = (payload) => (seat.value ? locate(seat.value, payload.uid)?.card ?? null : null);
const fromSeat = (payload) => payload.source === 'seat' && !!instanceOf(payload);

const playZone = useDropZone(
    (p) => fromSeat(p) && p.zone !== 'play',
    (p) => {
        const instance = instanceOf(p);
        // From the hand, onto the table, is playing it: paid for, omen added.
        if (p.zone === 'hand') a.play(instance);
        else a.move(instance, 'play');
    },
);
const handZone = useDropZone((p) => fromSeat(p) && p.zone !== 'hand', (p) => a.move(instanceOf(p), 'hand'));
const deckZone = useDropZone(fromSeat, (p, { under }) => a.move(instanceOf(p), 'deck', under ? 'bottom' : 'top'));
const discardZone = useDropZone((p) => fromSeat(p) && p.zone !== 'discard', (p) => a.move(instanceOf(p), 'discard'));
const shopZone = useDropZone(fromSeat, (p, { under }) => a.move(instanceOf(p), 'shop', under ? 'bottom' : 'top'));
const removedZone = useDropZone((p) => fromSeat(p) && p.zone !== 'removed', (p) => a.move(instanceOf(p), 'removed'));

// Every card in play is a drop zone of its own: a card dropped on one is tucked
// under it. Made once per uid and reading the seat when the drop lands, so
// undo replacing the objects, or another seat reusing the number, changes
// nothing. It sits inside the in-play row, and takes the drop before the row
// can play it (useDropZone skips a drop an inner zone already took).
const hostZones = new Map();
const hostZone = (host) => {
    const uid = host.uid;
    if (!hostZones.has(uid)) {
        hostZones.set(uid, useDropZone(
            (p) => fromSeat(p) && p.uid !== uid && instanceOf(p)?.under !== uid,
            (p) => {
                const found = locate(seat.value, uid);
                if (found?.zone === 'play') a.tuck(instanceOf(p), found.card);
            },
        ));
    }
    return hostZones.get(uid);
};

// ── Sizes ───────────────────────────────────────────────────────────────────

// Sizes from the rows' own heights; nothing sits under a card any more but a
// little room for the hand's lift and the sideways scrollbar.
const playRow = ref(null);
const handRow = ref(null);
const trayBody = ref(null);
const playWidth = useCardFit(playRow, { ratio: PORTRAIT, chrome: 14, min: 70, max: 200 });
// How much of a tucked card shows past the right edge of the card it is under.
// Sideways, never below: the row's height must not depend on what is in it.
const tuckPeek = computed(() => Math.round(playWidth.value * 0.22));
const handWidth = useCardFit(handRow, { ratio: PORTRAIT, chrome: 20, min: 90, max: 240 });
const trayWidth = useCardFit(trayBody, { ratio: PORTRAIT, chrome: 34, min: 70, max: 200 });

// The hand fans out: cards sit side by side while they fit, and overlap once
// they do not, the way a hand held in front of you does.
const handRowWidth = useWidth(handRow);
const HAND_GAP = 8;
const handOverlap = computed(() => {
    const n = seat.value?.zones.hand.length ?? 0;
    if (n < 2 || !handRowWidth.value) return 0;
    const needed = n * handWidth.value + (n - 1) * HAND_GAP;
    const spare = needed - (handRowWidth.value - 8);
    return spare > 0 ? Math.min(handWidth.value * 0.72, spare / (n - 1) + HAND_GAP) : 0;
});

const handOf = (member) => member.zones?.hand?.length ?? 0;
const deckLine = (member) =>
    `${props.deckByKey[member.deckKey]?.name ?? 'no deck'} · hand ${props.handSize(member.slug)} · ${props.goldPerRound(member.slug)} gold a round`;
</script>

<template>
    <section class="table-panel flex min-h-0 flex-col">
        <!-- Seat tabs, and the active seat's dials beside them -->
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 border-b border-white/10 px-2 py-1">
            <div class="flex flex-wrap items-center gap-1" role="tablist" aria-label="Seats">
                <button
                    v-for="(member, index) in party"
                    :key="member.deckKey"
                    type="button"
                    role="tab"
                    :aria-selected="index === active"
                    class="flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs transition"
                    :class="[
                        index === active ? 'border-amber-400/70 bg-white/15 text-stone-50' : 'border-white/10 bg-black/20 text-stone-300 hover:border-white/30',
                        member.current === 0 ? 'border-red-500/70' : '',
                    ]"
                    :title="`${deckLine(member)} · press ${index + 1}`"
                    @click="emit('update:active', index)"
                >
                    <span class="h-3 w-3 shrink-0 rounded-full border border-white/40" :style="partyStyle(characterBySlug[member.slug])" />
                    <span class="max-w-[9rem] truncate font-semibold">{{ nameOf(member) }}</span>
                    <span v-if="index === firstPlayer" class="rounded bg-amber-600 px-1 text-[9px] font-bold uppercase tracking-wide text-amber-50" title="First player">1st</span>
                    <span v-if="member.current === 0" class="rounded bg-red-700 px-1 text-[9px] font-bold uppercase text-red-50">out</span>
                    <span class="tabular-nums text-red-200" title="Health"><Icon name="health" /> {{ member.current }}</span>
                    <span class="tabular-nums text-amber-200" title="Gold"><Icon name="gold" /> {{ member.gold ?? 0 }}</span>
                    <span class="tabular-nums text-stone-300" title="Cards in hand">✋ {{ handOf(member) }}</span>
                </button>
                <button type="button" class="table-btn py-0" title="Pick who is playing" @click="emit('open-players')">+ Seat</button>
            </div>

            <template v-if="seat">
                <div class="relative">
                    <button
                        v-if="character?.ability_name"
                        type="button"
                        class="text-xs text-stone-300 underline decoration-dotted hover:text-stone-50"
                        :title="deckLine(seat)"
                        @click="showAbility = !showAbility"
                    >
                        {{ character.ability_name }}
                    </button>
                    <div
                        v-if="showAbility"
                        class="absolute bottom-full left-0 z-30 mb-1 w-80 rounded border border-stone-300 bg-stone-50 p-2 text-xs leading-relaxed text-stone-800 shadow-xl"
                    >
                        <p class="mb-1 text-[11px] text-stone-500">{{ deckLine(seat) }}</p>
                        <div v-html="character.ability_html" />
                    </div>
                </div>

                <div class="ml-auto flex flex-wrap items-center gap-3 text-xs">
                    <div class="dial">
                        <span class="table-label"><Icon name="health" /> Health</span>
                        <button type="button" class="table-step" aria-label="Damage" @click="adjust('current', -1, 'health', fullHealth)">−</button>
                        <span class="w-12 text-center font-semibold tabular-nums" :class="dead ? 'text-red-300' : 'text-stone-50'">{{ seat.current }} / {{ fullHealth }}</span>
                        <button type="button" class="table-step" aria-label="Heal" @click="adjust('current', 1, 'health', fullHealth)">+</button>
                    </div>
                    <div class="dial">
                        <span class="table-label"><Icon name="gold" /> Gold</span>
                        <button type="button" class="table-step" aria-label="Spend a gold" @click="adjust('gold', -1, 'gold')">−</button>
                        <span :key="`gold-${seat.gold}`" class="pulse w-6 text-center font-serif text-base font-bold tabular-nums text-amber-200">{{ seat.gold }}</span>
                        <button type="button" class="table-step" aria-label="Gain a gold" @click="adjust('gold', 1, 'gold')">+</button>
                    </div>
                    <div class="dial" title="Only if this deck has a gold pouch. It keeps its gold past the end of the round.">
                        <span class="table-label">Pouch</span>
                        <button type="button" class="table-step" :disabled="!seat.pouch" aria-label="Take from the pouch" @click="takeGold">−</button>
                        <span class="w-8 text-center font-semibold tabular-nums text-stone-50">{{ seat.pouch ?? 0 }}<span v-if="pouchMax !== Infinity" class="text-[10px] text-stone-400">/{{ pouchMax }}</span></span>
                        <button type="button" class="table-step" :disabled="seat.gold < 1 || (seat.pouch ?? 0) >= pouchMax" aria-label="Put into the pouch" @click="storeGold">+</button>
                    </div>
                    <div v-for="(counter, index) in seat.counters" :key="counter.name + index" class="dial">
                        <span class="table-label">
                            {{ counter.name }}
                            <button type="button" class="ml-0.5 text-stone-500 hover:text-red-300" :aria-label="`Stop keeping ${counter.name}`" @click="dropCounter(index)">×</button>
                        </span>
                        <button type="button" class="table-step" @click="bumpCounter(index, -1)">−</button>
                        <span
                            class="w-6 text-center font-semibold tabular-nums"
                            :class="counter.value >= fullHealth && fullHealth > 0 ? 'text-red-300' : 'text-stone-50'"
                        >{{ counter.value }}</span>
                        <button type="button" class="table-step" @click="bumpCounter(index, 1)">+</button>
                    </div>
                    <form class="flex items-center gap-1" @submit.prevent="addCounter">
                        <input
                            v-model="newCounter"
                            class="w-24 rounded border border-white/20 bg-black/30 px-1.5 py-0 text-xs leading-5 text-stone-100 placeholder-stone-500 focus:border-amber-500 focus:ring-0"
                            placeholder="New counter…"
                            aria-label="Name a counter to keep"
                        >
                        <button type="submit" class="table-btn py-0" :disabled="!newCounter.trim()">Add</button>
                    </form>
                    <span class="h-5 w-px bg-white/15" />
                    <button type="button" class="table-btn" :class="tray === 'shop' ? 'table-btn-on' : ''" title="Shop (S)" @click="toggleTray('shop')">Shop</button>
                    <button type="button" class="table-btn" :class="tray === 'town' ? 'table-btn-on' : ''" title="Town (T)" @click="toggleTray('town')">Town</button>
                </div>
            </template>
        </div>

        <template v-if="seat">
            <!-- In play, and the trays that open over it -->
            <div class="relative flex min-h-0 gap-2 px-2 pt-1" style="flex: 40 1 0%">
                <div class="flex shrink-0 flex-col items-center justify-center gap-1">
                    <span class="row-label">In play · {{ seat.zones.play.length }}</span>
                    <button type="button" class="table-btn px-1 py-0 text-[10px]" title="Ready everything in play" :disabled="!seat.zones.play.some((c) => c.exhausted)" @click="a.readyAll">↺</button>
                </div>
                <div
                    ref="playRow"
                    class="felt-row drop-row min-h-0 flex-1 rounded-md"
                    :class="playZone.over.value ? 'drop-over' : playZone.ready() ? 'drop-ready' : ''"
                    v-on="playZone.listeners"
                >
                    <TransitionGroup name="card-move">
                        <!-- A card in play, with whatever is tucked under it fanned out to its right, behind it. Drop a card on it to tuck it there. -->
                        <div
                            v-for="(instance, index) in seat.zones.play"
                            :key="instance.uid"
                            class="tuck-host relative shrink-0 rounded-lg"
                            :class="hostZone(instance).over.value ? 'tuck-over' : ''"
                            :style="{ width: `${playWidth + tuckPeek * tuckedOf(instance).length}px` }"
                            v-on="hostZone(instance).listeners"
                        >
                            <TableCard
                                v-for="(under, u) in tuckedOf(instance)"
                                :key="under.uid"
                                :style="{ position: 'absolute', top: '0px', left: `${tuckPeek * (u + 1)}px`, zIndex: tuckedOf(instance).length - u }"
                                :card="cardOf(under)"
                                :width="playWidth"
                                :glow="actionsStep && a.canAfford(cardOf(under).gold_cost)"
                                :activate="() => a.play(under)"
                                :menu="() => tuckedMenu(under, instance, u)"
                                :drag="dragOf('tucked', under)"
                                :label="`${cardOf(under).name}, tucked under ${cardOf(instance).name}: click to play it · ${costLine(cardOf(under))}`"
                            >
                                <template #overlay>
                                    <span class="tuck-tag" :style="{ width: `${tuckPeek}px` }">tucked</span>
                                </template>
                            </TableCard>

                            <TableCard
                                class="group"
                                :style="{ zIndex: tuckedOf(instance).length + 1 }"
                                :card="cardOf(instance)"
                                :width="playWidth"
                                :exhausted="instance.exhausted"
                                :activate="() => a.toggleExhausted(instance)"
                                :menu="() => playMenu(instance, index)"
                                :drag="dragOf('play', instance)"
                                :label="`${cardOf(instance).name}: click to ${instance.exhausted ? 'ready' : 'exhaust'}`"
                            >
                                <template #overlay>
                                    <span
                                        v-if="tuckedOf(instance).length"
                                        class="badge absolute left-1 top-1/2 -translate-y-1/2 bg-stone-950/85 text-stone-100"
                                        :title="`${tuckedOf(instance).map((c) => cardOf(c).name).join(', ')} tucked under it`"
                                    >{{ tuckedOf(instance).length }} under</span>
                                    <button
                                        v-if="a.upgradeOf(instance)"
                                        type="button"
                                        class="badge pointer-events-auto absolute right-1 top-1/2 -translate-y-1/2 bg-amber-600 text-amber-50 hover:bg-amber-500"
                                        :title="`Upgrade to ${cardOf(a.upgradeOf(instance)).name} (the Smithy)`"
                                        @click="a.upgrade(instance)"
                                    >⬆</button>
                                    <div
                                        class="pointer-events-auto absolute bottom-1 left-1/2 -translate-x-1/2 items-center gap-0.5 rounded-full bg-stone-950/85 px-1 py-0.5 text-[11px] text-stone-50 shadow"
                                        :class="instance.counter ? 'flex' : 'hidden group-hover:flex'"
                                        title="Counters on this card — uses, anything"
                                    >
                                        <button type="button" class="counter-btn" aria-label="Remove a counter" @click="a.bump(instance, -1)">−</button>
                                        <span class="w-4 text-center font-semibold tabular-nums">{{ instance.counter ?? 0 }}</span>
                                        <button type="button" class="counter-btn" aria-label="Add a counter" @click="a.bump(instance, 1)">+</button>
                                    </div>
                                </template>
                            </TableCard>
                        </div>
                    </TransitionGroup>
                    <p v-if="!seat.zones.play.length" class="m-auto text-xs text-stone-500">Nothing in play. Click a card in hand to play it, or drag it here.</p>
                </div>

                <!-- A tray over the in-play row: the shop, or the town -->
                <div v-if="tray" class="absolute inset-x-1 inset-y-0 z-20 flex gap-2 rounded-md border border-amber-500/40 bg-stone-950 px-2 pt-1 shadow-2xl">
                    <div class="table-scroll flex w-44 shrink-0 flex-col gap-1 text-[11px] leading-snug text-stone-400">
                        <span class="table-label text-amber-300">{{ tray === 'shop' ? 'Shop' : 'Town' }} · {{ name }} · {{ seat.gold }}g</span>
                        <template v-if="tray === 'shop'">
                            <span>Click a card to buy it. {{ shopHidden }} face down in the shop deck.</span>
                            <span>Bought cards go {{ a.destinationLabel() }} <span class="text-stone-500">(shopPurchaseDestination, which the designer is not sure of)</span>.</span>
                            <button type="button" class="table-btn py-0" :disabled="seat.zones.shop.length < 2" @click="a.shuffleShop"><Icon name="shuffle" /> Shuffle shop</button>
                        </template>
                        <span v-else>Click a district to use it for {{ name }}: {{ seat.townUsed?.length ?? 0 }} used so far this round.</span>
                        <button type="button" class="table-btn mb-1 mt-auto py-0" @click="tray = null">Close</button>
                    </div>

                    <div ref="trayBody" class="min-h-0 min-w-0 flex-1">
                        <div v-if="tray === 'shop'" class="felt-row h-full pt-1">
                            <TransitionGroup name="card-move">
                                <TableCard
                                    v-for="(instance, index) in shopShowing"
                                    :key="instance.uid"
                                    :card="cardOf(instance)"
                                    :width="trayWidth"
                                    :glow="a.canAfford(cardOf(instance).shop_cost)"
                                    :activate="() => a.buy(instance)"
                                    :menu="() => shopMenu(instance, index)"
                                    :drag="dragOf('shop', instance)"
                                    :label="`Buy ${cardOf(instance).name} for ${cardOf(instance).shop_cost ?? 0} gold`"
                                >
                                    <template #overlay>
                                        <span
                                            class="badge absolute bottom-1 left-1/2 -translate-x-1/2"
                                            :class="a.canAfford(cardOf(instance).shop_cost) ? 'bg-amber-500 text-stone-950' : 'bg-stone-950/85 text-stone-300'"
                                        >{{ cardOf(instance).shop_cost ?? 0 }}g</span>
                                    </template>
                                </TableCard>
                            </TransitionGroup>
                            <p v-if="!shopShowing.length" class="my-auto text-xs text-stone-500">The shop is empty.</p>
                        </div>
                        <TownTable
                            v-else
                            :town="town"
                            :districts="districts"
                            :seat="seat"
                            :name-of="nameOf"
                            :table="table"
                            :width="trayWidth"
                        />
                    </div>
                </div>
            </div>

            <!-- The hand, with the deck on one side and the other piles on the other -->
            <div class="flex min-h-0 gap-3 px-2 pb-1 pt-1" style="flex: 60 1 0%">
                <div class="flex w-[4.5rem] shrink-0 flex-col items-center gap-1">
                    <PileStack
                        label="Deck"
                        :count="seat.zones.deck.length"
                        :ready="deckZone.ready()"
                        :active="deckZone.over.value"
                        title="Click to draw · right-click for more · drop a card here (Shift: underneath)"
                        v-on="deckZone.listeners"
                        @open="a.draw(1)"
                        @contextmenu.prevent="(event) => menuAt(event, deckMenu())"
                    />
                    <span class="text-center text-[10px] leading-tight text-stone-500">click: draw<br>right-click: more</span>
                </div>

                <div class="flex shrink-0 flex-col justify-center">
                    <span class="row-label">Hand · {{ seat.zones.hand.length }}</span>
                </div>
                <div
                    ref="handRow"
                    class="hand-row drop-row min-h-0 flex-1 rounded-md pt-2"
                    :class="handZone.over.value ? 'drop-over' : handZone.ready() ? 'drop-ready' : ''"
                    v-on="handZone.listeners"
                >
                    <TransitionGroup name="card-move">
                        <TableCard
                            v-for="(instance, index) in seat.zones.hand"
                            :key="instance.uid"
                            class="hand-card"
                            :style="{ marginLeft: index ? `${HAND_GAP - handOverlap}px` : '0px', zIndex: index }"
                            :card="cardOf(instance)"
                            :width="handWidth"
                            :glow="actionsStep && a.canAfford(cardOf(instance).gold_cost)"
                            :activate="() => a.play(instance)"
                            :menu="() => handMenu(instance, index)"
                            :drag="dragOf('hand', instance)"
                            :label="`Play ${cardOf(instance).name} · ${costLine(cardOf(instance))}`"
                        >
                            <template #overlay>
                                <button
                                    v-if="a.upgradeOf(instance)"
                                    type="button"
                                    class="badge pointer-events-auto absolute right-1 top-1/2 -translate-y-1/2 bg-amber-600 text-amber-50 hover:bg-amber-500"
                                    :title="`Upgrade to ${cardOf(a.upgradeOf(instance)).name} (the Smithy)`"
                                    @click="a.upgrade(instance)"
                                >⬆</button>
                            </template>
                        </TableCard>
                    </TransitionGroup>
                    <p v-if="!seat.zones.hand.length" class="m-auto text-xs text-stone-500">Empty hand. Click the deck to draw.</p>
                </div>

                <div class="flex shrink-0 items-start gap-2">
                    <PileStack
                        label="Discard"
                        :count="seat.zones.discard.length"
                        :sub="seat.zones.discard.length ? cardOf(seat.zones.discard[seat.zones.discard.length - 1]).name : ''"
                        :ready="discardZone.ready()"
                        :active="discardZone.over.value"
                        v-on="discardZone.listeners"
                        @open="viewing = 'discard'"
                    />
                    <PileStack
                        label="Shop"
                        tone="shop"
                        :count="seat.zones.shop.length"
                        :active="tray === 'shop' || shopZone.over.value"
                        :ready="shopZone.ready()"
                        title="Click to open the shop · drop a card on top (Shift: underneath)"
                        v-on="shopZone.listeners"
                        @open="toggleTray('shop')"
                        @contextmenu.prevent="(event) => menuAt(event, shopPileMenu())"
                    />
                    <PileStack :label="ZONE_LABELS.aside" tone="aside" :count="seat.zones.aside.length" @open="viewing = 'aside'" />
                    <PileStack
                        :label="ZONE_LABELS.removed"
                        tone="removed"
                        :count="seat.zones.removed.length"
                        :ready="removedZone.ready()"
                        :active="removedZone.over.value"
                        v-on="removedZone.listeners"
                        @open="viewing = 'removed'"
                    />
                </div>
            </div>
        </template>

        <div v-else class="flex flex-1 flex-col items-center justify-center gap-2 text-sm text-stone-400">
            <p>Nobody at the table.</p>
            <button type="button" class="table-btn table-btn-primary" @click="emit('open-players')">Pick the decks that are playing</button>
        </div>

        <PileViewer
            v-if="seat && viewing"
            :title="`${name} · ${ZONE_LABELS[viewing]}`"
            :note="viewerNotes[viewing] ?? ''"
            :items="seat.zones[viewing]"
            :card-of="cardOf"
            :menu-for="pileMenu(viewing)"
            :drag-for="(item) => dragOf(viewing, item)"
            @close="viewing = null"
            @zoom="(index) => zoomIn(seat.zones[viewing], index)"
        />
    </section>
</template>

<style scoped>
.dial {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
/* A row's name, written up the side so it costs no height. */
.row-label {
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    align-self: center;
    flex: none;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgb(120 113 108);
}
.hand-row {
    display: flex;
    align-items: flex-start;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
.hand-card {
    position: relative;
    transition: transform 140ms ease-out, margin 200ms ease-out;
}
.hand-card:hover,
.hand-card:focus-within {
    transform: translateY(-6px);
    z-index: 100 !important;
}
.badge {
    border-radius: 9999px;
    padding: 0 0.4rem;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
}
/* A card in play with a card in the air over it that would be tucked under it. */
.tuck-over {
    box-shadow: 0 0 0 2px rgba(251, 191, 36, 0.9), 0 0 16px rgba(251, 191, 36, 0.4);
}
/* The word on the strip of a tucked card that shows past the card it is under. */
.tuck-tag {
    position: absolute;
    right: 0;
    bottom: 0.25rem;
    padding: 0.1rem 0;
    border-radius: 0.25rem;
    background: rgba(12, 10, 9, 0.85);
    color: rgb(214 211 209);
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-align: center;
    text-transform: uppercase;
}
.counter-btn {
    width: 1rem;
    border-radius: 9999px;
    line-height: 1rem;
}
.counter-btn:hover {
    background: rgba(255, 255, 255, 0.2);
}
</style>
