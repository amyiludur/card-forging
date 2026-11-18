<script setup>
/*
 * A card's picture, in every card editor: upload or replace it, take it off
 * the card, size its window, choose crop-or-fit, and click the point to keep
 * in view. It writes straight into the editor's form (art, art_size, art_fit,
 * art_focus), so the preview beside the form follows along and the card saves
 * the lot with everything else.
 *
 * An upload is stored at once, in design/art/<kind>/, so a card not saved yet
 * can still show its picture. Taking the picture off a card never deletes the
 * file: another card may be using it, and /design lists the ones nobody is.
 */
import { computed, ref } from 'vue';
import { DEFAULT_SIZE, artUrl, windowSize } from '../cardArt';

const props = defineProps({
    form: { type: Object, required: true },
    kind: { type: String, required: true },
    name: { type: String, default: '' },
    landscape: { type: Boolean, default: false },
    // The server's word on the picture as saved: the path names no file.
    missing: { type: Boolean, default: false },
});

const uploading = ref(false);
const error = ref(null);
const input = ref(null);

const focus = computed(() =>
    Array.isArray(props.form.art_focus) && props.form.art_focus.length === 2 ? props.form.art_focus : [50, 50]
);

const fit = computed(() => props.form.art_fit ?? 'cover');

// What a blank size comes to, said in the placeholder so "default" is a number.
const defaultSize = computed(() => windowSize({ art: props.form.art, art_size: null }, props.landscape));

const upload = async (event) => {
    const file = event.target.files?.[0];

    if (!file) return;

    const data = new FormData();
    data.append('file', file);
    data.append('kind', props.kind);
    data.append('name', props.name || 'card');

    uploading.value = true;
    error.value = null;

    try {
        const response = await window.axios.post('/art', data);
        props.form.art = response.data.path;
    } catch (e) {
        // The field's own error when there is one; PHP refusing a request over
        // post_max_size answers 413 with no field errors, so that gets said too.
        const errors = e.response?.data?.errors ?? {};
        error.value = Object.values(errors).flat()[0]
            ?? (e.response?.status === 413 ? 'That picture is bigger than PHP accepts here: raise post_max_size and upload_max_filesize in php.ini.' : null)
            ?? e.response?.data?.message
            ?? 'That picture could not be stored.';
    } finally {
        uploading.value = false;
        if (input.value) input.value.value = '';
    }
};

const remove = () => {
    props.form.art = null;
};

const setSize = (event) => {
    const value = event.target.value;
    props.form.art_size = value === '' ? null : Math.max(0, Math.min(90, Math.round(Number(value))));
};

const setFocus = (event) => {
    const box = event.currentTarget.getBoundingClientRect();
    const x = Math.round(((event.clientX - box.left) / box.width) * 100);
    const y = Math.round(((event.clientY - box.top) / box.height) * 100);
    props.form.art_focus = [Math.max(0, Math.min(100, x)), Math.max(0, Math.min(100, y))];
};
</script>

<template>
    <div class="space-y-2 rounded-md border border-stone-200 bg-stone-50 p-2.5">
        <div class="flex items-center justify-between gap-2">
            <span class="field-micro">Art</span>
            <span v-if="form.art" class="truncate font-mono text-[11px] text-stone-500" :title="form.art">{{ form.art }}</span>
        </div>

        <p v-if="form.art && missing" class="text-xs font-semibold text-red-700">
            File missing from the design folder — the card keeps the path and prints an empty window.
        </p>

        <div class="flex flex-wrap items-start gap-3">
            <!-- The picture itself: click to choose what stays in view when it is
                 cropped. The button takes the picture's own shape, so a click's
                 place in the button is its place in the picture — the same
                 percentages background-position reads. -->
            <button
                v-if="form.art"
                type="button"
                class="relative block shrink-0 cursor-crosshair overflow-hidden rounded border border-stone-300 bg-stone-200"
                title="Click the part of the picture to keep in view"
                @click="setFocus"
            >
                <img :src="artUrl(form.art)" alt="" class="block max-h-24 max-w-24">
                <span
                    class="pointer-events-none absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-amber-600 shadow"
                    :style="{ left: `${focus[0]}%`, top: `${focus[1]}%` }"
                />
            </button>

            <div class="min-w-0 flex-1 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <label class="btn-ghost cursor-pointer text-xs">
                        {{ uploading ? 'Uploading…' : form.art ? 'Replace picture' : 'Upload picture' }}
                        <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="hidden" :disabled="uploading" @change="upload">
                    </label>
                    <button v-if="form.art" type="button" class="btn-ghost text-xs" @click="remove">Take off card</button>
                </div>

                <label class="flex items-center gap-2 text-xs text-stone-600">
                    Window
                    <input
                        type="number"
                        min="0"
                        max="90"
                        class="field w-20 py-1 text-xs"
                        :value="form.art_size ?? ''"
                        :placeholder="String(defaultSize)"
                        @input="setSize"
                    >
                    % of the card's {{ landscape ? 'width' : 'height' }}
                    <span class="text-stone-400">(blank = {{ DEFAULT_SIZE }}, 0 = no window)</span>
                </label>

                <div v-if="form.art" class="flex items-center gap-1 text-xs">
                    <button
                        type="button"
                        class="rounded border px-2 py-0.5"
                        :class="fit === 'cover' ? 'border-amber-600 bg-amber-50 text-amber-900' : 'border-stone-300 text-stone-600'"
                        @click="form.art_fit = null"
                    >Crop to fill</button>
                    <button
                        type="button"
                        class="rounded border px-2 py-0.5"
                        :class="fit === 'contain' ? 'border-amber-600 bg-amber-50 text-amber-900' : 'border-stone-300 text-stone-600'"
                        @click="form.art_fit = 'contain'"
                    >Show whole</button>
                </div>
            </div>
        </div>

        <p v-if="error" class="text-xs text-red-700">{{ error }}</p>
        <p v-if="form.errors?.art" class="text-xs text-red-700">{{ form.errors.art }}</p>
    </div>
</template>
