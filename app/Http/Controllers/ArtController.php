<?php

namespace App\Http\Controllers;

use App\Support\CardArt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Card pictures in and out of the design folder. An upload is stored at once
 * and its path handed back, so the editor can show a picture on a card that
 * has not been saved yet; the card itself records the path when it is.
 */
class ArtController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // What PHP will really accept, said in the error: a picture over
        // upload_max_filesize never reaches the app, and "the file failed to
        // upload" does not tell the designer why or what to change.
        $limit = CardArt::maxKb();
        $tooBig = sprintf(
            'Pictures can be at most %s here. Larger ones need upload_max_filesize and post_max_size raised in php.ini (up to %d MB).',
            $limit >= 1024 ? round($limit / 1024, 1).' MB' : $limit.' KB',
            CardArt::MAX_KB / 1024,
        );

        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:'.implode(',', array_keys(CardArt::MIMES)), 'max:'.$limit],
            'kind' => ['required', Rule::in(CardArt::KINDS)],
            'name' => ['required', 'string', 'max:120'],
        ], [
            'file.max' => $tooBig,
            'file.uploaded' => $tooBig,
        ]);

        $path = CardArt::store($data['file'], $data['kind'], $data['name']);

        return response()->json(['path' => $path, 'url' => '/'.$path]);
    }

    /** One picture, from design/art and from nowhere else. */
    public function show(string $path): BinaryFileResponse
    {
        $file = CardArt::absolute('art/'.$path);

        abort_if($file === null, 404);

        $mime = CardArt::MIMES[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? null;

        abort_if($mime === null, 404);

        return response()->file($file, ['Content-Type' => $mime, 'Cache-Control' => 'no-cache']);
    }
}
