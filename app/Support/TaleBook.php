<?php

namespace App\Support;

use App\Models\Tale;
use App\Models\TaleLogEntry;
use App\Models\TaleMode;
use App\Models\TaleStep;
use Illuminate\Support\Collection;

/**
 * What a Tale's printed book says: its story in play order, its modes, and the
 * campaign log laid out from its own entries. The log sheet is a grid — rows
 * are the entries, columns are the scenarios — and nothing on it is the tool's:
 * an entry prints its name and the space its kind needs to be written in.
 */
class TaleBook
{
    /** One block per seat on the log sheet. Recorded in the spec as the builder's call. */
    public const CHARACTER_BLOCKS = 4;

    private RulesMarkdown $prose;

    public function __construct(private Tale $tale, private Markup $markup)
    {
        $this->prose = new RulesMarkdown($markup);
        $tale->loadMissing(['steps.scenario', 'modes', 'logEntries']);
    }

    public static function for(Tale $tale): self
    {
        return new self($tale, Markup::make());
    }

    /**
     * The overview, then each scenario with the story before and after it,
     * then each mode and what it changes.
     *
     * @return array{overview_html: string, steps: array<int, array>, modes: array<int, array>}
     */
    public function story(): array
    {
        return [
            'overview_html' => $this->html($this->tale->overview, 'overview'),
            'steps' => $this->steps()->map(fn (TaleStep $step, int $i) => [
                'number' => $i + 1,
                'scenario' => $step->scenario?->name ?? 'Missing scenario',
                'intro_html' => $this->html($step->intro, 'step-'.($i + 1).'-intro'),
                'outro_html' => $this->html($step->outro, 'step-'.($i + 1).'-outro'),
                'notes_html' => $step->notes ? $this->markup->toHtml($step->notes) : '',
            ])->all(),
            'modes' => $this->tale->modes->map(fn (TaleMode $mode) => [
                'name' => $mode->name,
                'text_html' => $this->html($mode->text, 'mode-'.$mode->id),
                'is_placeholder' => $mode->is_placeholder,
            ])->all(),
        ];
    }

    /**
     * @return array{modes: array<int, string>, steps: array<int, array>, party: array<int, array>, character: array<int, array>, character_blocks: int}
     */
    public function logSheet(): array
    {
        $entry = fn (TaleLogEntry $e) => ['name' => $e->name, 'kind' => $e->kind];

        return [
            'modes' => $this->tale->modes->pluck('name')->all(),
            'steps' => $this->steps()->map(fn (TaleStep $step, int $i) => [
                'number' => $i + 1,
                'scenario' => $step->scenario?->name ?? 'Missing scenario',
            ])->all(),
            'party' => $this->tale->logEntries->where('scope', 'party')->map($entry)->values()->all(),
            'character' => $this->tale->logEntries->where('scope', 'character')->map($entry)->values()->all(),
            'character_blocks' => self::CHARACTER_BLOCKS,
        ];
    }

    private function steps(): Collection
    {
        return $this->tale->steps->values();
    }

    private function html(?string $text, string $anchor): string
    {
        return ($text ?? '') === '' ? '' : $this->prose->toHtml($text, $this->tale->slug.'-'.$anchor);
    }
}
