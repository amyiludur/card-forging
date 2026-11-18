<?php

namespace App\Http\Controllers;

use App\Support\CardArt;
use App\Models\Tale;
use App\Models\TaleCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The Tale's own cards: story cards and the box's own mechanic. A reward is a
 * player card and goes through the player card editor; a Tale's entity-side
 * cards are its modules. This is the third kind, and only this.
 */
class TaleCardController extends Controller
{
    public function store(Request $request, Tale $tale): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug($tale, Str::slug($data['name']));
        $data['sort'] = (int) $tale->cards()->max('sort') + 1;

        $tale->cards()->create($data);

        return $this->back($tale, 'Card added.');
    }

    public function update(Request $request, TaleCard $taleCard): RedirectResponse
    {
        $data = $this->validated($request);

        // The slug follows a rename, the same way it is made in the first place.
        if ($data['name'] !== $taleCard->name) {
            $data['slug'] = $this->uniqueSlug($taleCard->tale, Str::slug($data['name']), $taleCard);
        }

        $taleCard->update($data);

        return $this->back($taleCard->tale, 'Card saved.');
    }

    public function destroy(TaleCard $taleCard): RedirectResponse
    {
        $tale = $taleCard->tale;
        $taleCard->delete();

        return $this->back($tale, 'Card deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:20'],
            'type_line' => ['nullable', 'string', 'max:40'],
            'text' => ['nullable', 'string'],
            'flavour' => ['nullable', 'string'],
            // A picture and its placement: App\Support\CardArt.
            ...CardArt::rules(),
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'is_placeholder' => ['boolean'],
        ]);

        $data['qty'] ??= 1;

        return $data;
    }

    private function uniqueSlug(Tale $tale, string $base, ?TaleCard $except = null): string
    {
        $slug = $base ?: 'card';
        $n = 1;

        while ($tale->cards()->where('slug', $slug)->when($except, fn ($q) => $q->whereKeyNot($except->id))->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    private function back(Tale $tale, string $message): RedirectResponse
    {
        return redirect("/tales/{$tale->slug}?tab=cards")->with('success', $message);
    }
}
