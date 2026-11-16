<?php

namespace App\Http\Controllers;

use App\Models\Tale;
use App\Support\PdfRenderer;
use App\Support\RulebookOptions;
use App\Support\TaleBook;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\View;

/**
 * A Tale's book: its story, then its campaign log sheet. A page of text, not
 * the card grid, so it shares the rulebook's paper (RulebookOptions) and not
 * the card sheet. `?part=story` or `?part=log` prints one half alone; the Log
 * tab previews the log half.
 */
class TalePrintController extends Controller
{
    private const PARTS = ['story', 'log', 'both'];

    public function book(Request $request, Tale $tale): HttpResponse
    {
        return response(View::make('print.tale-book', $this->bookData($request, $tale))->render());
    }

    public function bookPdf(Request $request, Tale $tale)
    {
        $pdf = (new PdfRenderer)->render(View::make('print.tale-book', $this->bookData($request, $tale))->render());

        if ($pdf['path'] === null) {
            return back()->with('error', $pdf['error']);
        }

        return response()->download($pdf['path'], $tale->slug.'.pdf')->deleteFileAfterSend(true);
    }

    private function bookData(Request $request, Tale $tale): array
    {
        $part = $request->string('part', 'both')->toString();
        $part = in_array($part, self::PARTS, true) ? $part : 'both';

        $book = TaleBook::for($tale);

        return [
            'tale' => $tale,
            'options' => RulebookOptions::fromRequest($request),
            'story' => $part === 'log' ? null : $book->story(),
            'log' => $part === 'story' ? null : $book->logSheet(),
        ];
    }
}
