<?php

namespace App\Http\Controllers;

use App\Models\RuleDocument;
use App\Support\Markup;
use App\Support\PdfRenderer;
use App\Support\PrintOptions;
use App\Support\PrintSelection;
use App\Support\RulebookOptions;
use App\Support\RulesAppendices;
use App\Support\RulesMarkdown;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Printing the rulebook: the third of the three things the design brief asks
 * for, beside editing the cards and editing the rules.
 *
 * It works like the card print pages — an options page that writes the whole
 * setup into the query string, a preview served as HTML, and a PDF rendered
 * from that same HTML — and it reuses their document picker wholesale, because
 * "name what it prints or what it holds back, and naming wins" is the same rule
 * whether the run is a deck or a book.
 *
 * What it does not share is the sheet. A card sheet is a grid of fixed shapes
 * and a rulebook is a column of text that breaks over as many pages as it
 * takes, so the two views have nothing in common past the paper.
 *
 * Nothing here writes rules. The documents are the designer's, the tunable
 * numbers and the keywords are the designer's, and the two appendices only
 * list them — flagging every placeholder rather than quietly printing a
 * placeholder as a decided number.
 */
class RulebookPrintController extends Controller
{
    /** The group every document's picker key is in: `rules:12`. */
    private const GROUP = 'rules';

    public function options(Request $request): Response
    {
        $options = RulebookOptions::fromRequest($request);
        $selection = PrintSelection::fromRequest($request);
        $documents = $this->documents();

        return Inertia::render('Print/Rulebook', [
            'options' => $options->toArray(),
            'sheetSizes' => PrintOptions::SHEET_SIZES,
            'maxColumns' => RulebookOptions::MAX_COLUMNS,
            'layout' => $options->layout(),
            // Every document this page could print. The sheet is built from the
            // same list, so ticking one here and printing agree.
            'items' => $documents
                ->map(fn (RuleDocument $document) => [
                    'group' => self::GROUP,
                    'key' => $this->key($document),
                    'name' => $document->title,
                    'words' => str_word_count((string) $document->body),
                ])
                ->values()
                ->all(),
            'selection' => $selection->toArray(),
            'counts' => [
                'documents' => $documents->count(),
                'printing' => $documents->filter(fn (RuleDocument $d) => $selection->includes($this->key($d)))->count(),
            ],
            'placeholderNumbers' => RulesAppendices::make()->placeholderNumbers($this->chosen($selection, $documents)),
        ]);
    }

    public function sheet(Request $request): HttpResponse
    {
        return response(View::make('print.rulebook', $this->bookData($request))->render());
    }

    public function pdf(Request $request)
    {
        $pdf = (new PdfRenderer)->render(View::make('print.rulebook', $this->bookData($request))->render());

        if ($pdf['path'] === null) {
            return back()->with('error', $pdf['error']);
        }

        return response()->download($pdf['path'], 'rulebook.pdf')->deleteFileAfterSend(true);
    }

    /**
     * The whole book: the documents in the run, rendered, plus the contents
     * list and whichever appendices were asked for.
     */
    private function bookData(Request $request): array
    {
        $options = RulebookOptions::fromRequest($request);
        $selection = PrintSelection::fromRequest($request);

        $all = $this->documents();
        $chosen = $this->chosen($selection, $all);

        $markup = Markup::make();
        $renderer = new RulesMarkdown($markup);
        $appendices = new RulesAppendices($markup);

        // The heading ids are prefixed per document, because a printed rulebook
        // is several documents in one page and two of them may well both have a
        // "Rules" heading. The contents reads the same ids back, so a link in
        // the PDF lands on the heading it names.
        $documents = $chosen
            ->map(fn (RuleDocument $document) => [
                'title' => $document->title,
                'html' => $renderer->toHtml((string) $document->body, $document->slug),
                'headings' => $renderer->headings((string) $document->body, $document->slug),
            ])
            ->values()
            ->all();

        return [
            'title' => 'Rulebook',
            'options' => $options,
            'documents' => $documents,
            'contents' => collect($documents)->flatMap(fn (array $d) => $d['headings'])->values()->all(),
            'configRows' => $options->tunableNumbers ? $appendices->numbers() : [],
            'keywordRows' => $options->keywordGlossary ? $appendices->keywords() : [],
            'omitted' => $all->count() - $chosen->count(),
            'placeholderNumbers' => $appendices->placeholderNumbers($chosen),
        ];
    }

    private function documents(): Collection
    {
        return RuleDocument::orderBy('sort')->orderBy('id')->get();
    }

    private function chosen(PrintSelection $selection, Collection $documents): Collection
    {
        return $documents->filter(fn (RuleDocument $d) => $selection->includes($this->key($d)))->values();
    }

    /** The picker's key. The group is part of it, the same as every other run. */
    private function key(RuleDocument $document): string
    {
        return self::GROUP.':'.$document->id;
    }
}
