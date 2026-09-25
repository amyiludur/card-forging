<?php

namespace App\Http\Controllers;

use App\Support\Pocket;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\View;

/**
 * The pocket page, served live from the editor.
 *
 * The page the designer actually reads is a file: `pocket:build` writes it and
 * the workflow publishes it, so it can be opened on a phone with this app
 * stopped and the machine it runs on switched off. This route is the same page
 * off the working copy, for checking what will be published before pushing —
 * exactly the part a print options page plays for a sheet.
 */
class PocketController extends Controller
{
    public function show(): HttpResponse
    {
        return response(View::make('pocket', Pocket::make()->data())->render());
    }
}
