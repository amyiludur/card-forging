<?php

namespace App\Support;

use App\Models\Keyword;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * The two tables that sit behind the rules wherever the rules are read: the
 * tunable numbers and the keyword library.
 *
 * Both are the designer's own data, listed rather than written — and every
 * placeholder is flagged, because a placeholder is not a decision. This is the
 * one place they are built, so the printed rulebook
 * ({@see \App\Http\Controllers\RulebookPrintController}) and the pocket page
 * ({@see Pocket}) cannot disagree about what a number is worth or what a
 * keyword looks like.
 */
class RulesAppendices
{
    public function __construct(private Markup $markup)
    {
    }

    public static function make(): self
    {
        return new self(Markup::make());
    }

    /**
     * The tunable numbers, listed as the rules text would have quoted them:
     * the value goes through the markup as `{config:key}`, so the appendix and
     * a paragraph that names the same number can never print it differently.
     */
    public function numbers(): array
    {
        return RulesConfig::orderBy('sort')->get()
            ->map(fn (RulesConfig $config) => [
                'key' => $config->key,
                'label' => $config->label,
                'value' => $this->markup->toHtml('{config:'.$config->key.'}'),
                'description' => $config->description ? $this->markup->toHtml($config->description) : null,
                'is_placeholder' => $config->is_placeholder,
            ])
            ->all();
    }

    /**
     * The keyword library, each one drawn exactly as a card draws it — the
     * token goes through the markup rather than being rebuilt here, so the
     * glossary and the cards cannot disagree about what a keyword looks like.
     */
    public function keywords(): array
    {
        return Keyword::orderBy('sort')->orderBy('name')->get()
            ->map(fn (Keyword $keyword) => [
                'token' => $keyword->token,
                'rendered' => $this->markup->toHtml('{'.$keyword->token.'}'),
                'description' => $keyword->description ? $this->markup->toHtml($keyword->description) : '',
                'is_placeholder' => $keyword->is_placeholder,
            ])
            ->all();
    }

    /**
     * The tunable numbers the given documents quote that are still
     * placeholders. Reported wherever those documents are read, because a
     * rulebook taken to a playtest should say which of its numbers are not
     * decided yet. Report, don't correct: nothing is left out or rewritten.
     *
     * @param  Collection<int, RuleDocument>  $documents
     * @return list<string>
     */
    public function placeholderNumbers(Collection $documents): array
    {
        $quoted = $documents
            ->flatMap(fn (RuleDocument $d) => $this->markup->references((string) $d->body))
            ->unique();

        return RulesConfig::where('is_placeholder', true)
            ->whereIn('key', $quoted)
            ->orderBy('sort')
            ->pluck('key')
            ->all();
    }
}
