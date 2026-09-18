<?php

namespace App\Http\Controllers;

use App\Models\RuleDocument;
use App\Models\RuleDocumentVersion;
use App\Models\RulesConfig;
use App\Support\Markup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RuleDocumentController extends Controller
{
    public function index(): RedirectResponse|Response
    {
        $first = RuleDocument::orderBy('sort')->first();

        return $first
            ? to_route('rules.show', $first)
            : Inertia::render('Rules/Documents', ['documents' => [], 'document' => null, 'versions' => [], 'config' => []]);
    }

    public function show(RuleDocument $document): Response
    {
        $markup = Markup::make();

        return Inertia::render('Rules/Documents', [
            'documents' => RuleDocument::orderBy('sort')->get(['slug', 'title']),
            'document' => [
                'slug' => $document->slug,
                'title' => $document->title,
                'body' => $document->body,
                'updated_at' => $document->updated_at?->toDateTimeString(),
                'references' => $markup->references((string) $document->body),
            ],
            'versions' => $document->versions()->limit(25)->get()
                ->map(fn (RuleDocumentVersion $v) => [
                    'id' => $v->id,
                    'note' => $v->note,
                    'saved_at' => $v->created_at?->toDateTimeString(),
                    'length' => strlen((string) $v->body),
                ]),
            'config' => RulesConfig::orderBy('sort')->get()
                ->map(fn (RulesConfig $c) => ['key' => $c->key, 'label' => $c->label, 'value' => $c->raw_value, 'is_placeholder' => $c->is_placeholder]),
        ]);
    }

    public function update(Request $request, RuleDocument $document): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'body' => ['nullable', 'string'],
        ]);

        if ($document->body !== $data['body']) {
            $document->snapshot('Edited in the rules editor');
        }

        $document->update($data);

        return back()->with('success', 'Rules saved. Previous version kept in history.');
    }

    public function restore(RuleDocument $document, RuleDocumentVersion $version): RedirectResponse
    {
        abort_unless($version->rule_document_id === $document->id, 404);

        $document->snapshot('Replaced by restoring an earlier version');
        $document->update(['body' => $version->body]);

        return to_route('rules.show', $document)->with('success', 'Earlier version restored.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['required', 'string', 'max:160', 'unique:rule_documents,slug'],
        ]);

        $document = RuleDocument::create($data + [
            'body' => "# {$data['title']}\n\n",
            'sort' => (int) RuleDocument::max('sort') + 1,
        ]);

        return to_route('rules.show', $document)->with('success', 'Document created.');
    }
}
