<script setup>
/**
 * The playtest table's shell: the whole viewport and nothing past it. The
 * table is a game laid out on one screen, so this has no sidebar and no page
 * scroll — every region inside sizes itself to the space it is given and
 * scrolls sideways on its own if it must. Opted into with
 * `defineOptions({ layout: TableLayout })`, the way a page opts out of
 * AppLayout in app.js.
 */
</script>

<template>
    <div class="table-felt flex h-screen flex-col overflow-hidden text-stone-100">
        <slot />
    </div>
</template>

<style>
/*
 * Shared by every region on the table. Plain CSS rather than @apply so it
 * reads the same wherever it is included; dark felt so the cream card faces
 * stand out, which is the only thing about the cards this page decides.
 */
.table-felt {
    background-color: #17271f;
    background-image:
        radial-gradient(ellipse at 50% 35%, rgba(70, 110, 80, 0.45), transparent 70%),
        radial-gradient(ellipse at 50% 120%, rgba(0, 0, 0, 0.55), transparent 60%);
}

/*
 * A card face inherits its body text colour from the page, which on every
 * other page is the body's stone-900. The table's own text is light, so a card
 * here is handed that colour back — otherwise its effect text is cream on
 * cream. CardPreview itself is untouched.
 */
.table-felt .card-button {
    color: rgb(28 25 23);
}

.table-panel {
    border: 1px solid rgba(255, 255, 255, 0.08);
    background: rgba(0, 0, 0, 0.22);
    border-radius: 0.5rem;
}

.table-label {
    font-size: 10px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgb(168 162 158);
}

.table-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.3rem;
    border-radius: 0.3rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.07);
    padding: 0.2rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1rem;
    color: rgb(231 229 228);
    white-space: nowrap;
    transition: background-color 120ms, border-color 120ms;
}
.table-btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.16);
    border-color: rgba(255, 255, 255, 0.35);
}
.table-btn:disabled {
    opacity: 0.4;
    cursor: default;
}
.table-btn-on {
    background: rgb(180 83 9);
    border-color: rgb(217 119 6);
    color: rgb(255 251 235);
}
.table-btn-on:hover:not(:disabled) {
    background: rgb(194 65 12);
}

.table-btn-primary {
    background: rgb(217 119 6);
    border-color: rgb(245 158 11);
    color: rgb(28 25 23);
    font-weight: 600;
}
.table-btn-primary:hover:not(:disabled) {
    background: rgb(245 158 11);
    border-color: rgb(252 211 77);
}

/* A − or + beside a number. */
.table-step {
    display: inline-flex;
    height: 1.25rem;
    width: 1.25rem;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 0.25rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: rgba(255, 255, 255, 0.06);
    font-size: 0.8rem;
    line-height: 1;
    color: rgb(231 229 228);
}
.table-step:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.18);
}
.table-step:disabled {
    opacity: 0.35;
}

/* The Move… dropdowns on a dark table. */
.table-select {
    display: block;
    width: 100%;
    min-width: 0;
    border-radius: 0.25rem;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background-color: rgba(0, 0, 0, 0.35);
    padding: 0.05rem 1.4rem 0.05rem 0.35rem;
    background-position: right 0.2rem center;
    font-size: 11px;
    line-height: 1.1rem;
    color: rgb(231 229 228);
}
.table-select option {
    color: rgb(28 25 23);
    background: white;
}

/* A row of cards that scrolls sideways and never down. */
.felt-row {
    display: flex;
    align-items: flex-start;
    gap: 0.6rem;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
/* Somewhere a card in the air could land, and the one it is over. */
.drop-row {
    transition: background-color 120ms, box-shadow 120ms;
}
.drop-ready {
    box-shadow: inset 0 0 0 1px rgba(252, 211, 77, 0.25);
}
.drop-over {
    background-color: rgba(251, 191, 36, 0.12);
    box-shadow: inset 0 0 0 2px rgba(251, 191, 36, 0.6);
}

/*
 * Cards sliding to where they went, rather than the row redrawing: a
 * TransitionGroup named card-move on every row of cards.
 */
.card-move-move {
    transition: transform 220ms ease-out;
}
.card-move-enter-active {
    transition: opacity 200ms ease-out, transform 220ms ease-out;
}
.card-move-enter-from {
    opacity: 0;
    transform: translateY(14px) scale(0.96);
}
.card-move-leave-active {
    transition: opacity 140ms ease-in, transform 140ms ease-in;
    position: absolute;
}
.card-move-leave-to {
    opacity: 0;
    transform: scale(0.9);
}

/* A number that just changed: re-keyed on its value, so it pulses each time. */
.pulse {
    display: inline-block;
    animation: table-pulse 380ms ease-out;
}
@keyframes table-pulse {
    0% { transform: scale(1.35); color: rgb(253 230 138); }
    100% { transform: scale(1); }
}

/* A card dealt onto the storyline, one after another. */
.deal-in {
    animation: table-deal 320ms ease-out both;
}
@keyframes table-deal {
    from { opacity: 0; transform: translate(-28px, -10px) rotate(-4deg) scale(0.94); }
    to { opacity: 1; transform: none; }
}

@media (prefers-reduced-motion: reduce) {
    .card-move-move,
    .card-move-enter-active,
    .card-move-leave-active {
        transition: none;
    }
    .pulse,
    .deal-in {
        animation: none;
    }
}

.table-scroll {
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.2) transparent;
}
</style>
