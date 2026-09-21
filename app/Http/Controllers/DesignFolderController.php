<?php

namespace App\Http\Controllers;

use App\Support\DesignFolder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The design folder, from a button rather than a terminal.
 *
 * Three things, and they are the three the designer already did by hand:
 * read the folder into the editor, write the editor back to the folder, and
 * commit what that wrote. Nothing here is a new idea about the design folder —
 * `design:import` and `design:export` are still the whole of what moves data,
 * and this only presses them.
 *
 * Publishing is export, commit and push in that order, and it stops at the
 * first one that fails rather than carrying on: a push of a commit that was not
 * made is worse than no push at all. What happened at each step is reported,
 * because a button that says only "done" is a button that cannot be trusted.
 */
class DesignFolderController extends Controller
{
    public function index(): Response
    {
        $folder = DesignFolder::make();

        return Inertia::render('Design/Index', [
            'status' => $folder->status(),
        ]);
    }

    /**
     * design/ → the editor.
     *
     * The destructive one: the folder is the source of truth, so a card the
     * editor has and the folder does not is deleted by this. The page says so
     * and asks before getting here.
     */
    public function import(): RedirectResponse
    {
        $folder = DesignFolder::make();
        $result = $folder->import();

        return $result['ok']
            ? back()->with('success', 'Imported design/ into the editor. '.$this->firstLine($result['output']))
            : back()->with('error', 'design:import failed. '.$this->firstLine($result['output']));
    }

    /** The editor → design/, leaving the writing to git. */
    public function export(): RedirectResponse
    {
        $folder = DesignFolder::make();
        $result = $folder->export();

        if (! $result['ok']) {
            return back()->with('error', 'design:export failed. '.$this->firstLine($result['output']));
        }

        $changed = count($folder->changes());

        return back()->with('success', $changed === 0
            ? 'Exported. The design folder already matched the editor, so nothing changed.'
            : "Exported. {$changed} ".($changed === 1 ? 'file differs' : 'files differ').' from the last commit.');
    }

    /**
     * Export, commit and push, stopping at the first step that does not work.
     *
     * The message is the designer's and is required: a push is deliberate, so
     * there is no default message that would let one happen by accident.
     */
    public function publish(Request $request): RedirectResponse
    {
        $folder = DesignFolder::make();

        $data = $request->validate([
            'message' => ['required', 'string', 'max:200'],
            // Off pushes nothing and leaves the commit sitting locally.
            'push' => ['boolean'],
        ]);

        $export = $folder->export();

        if (! $export['ok']) {
            return back()->with('error', 'design:export failed, so nothing was committed. '.$this->firstLine($export['output']));
        }

        if ($folder->changes() === []) {
            return back()->with('success', 'Exported. Nothing in design/ had changed, so there was nothing to commit.');
        }

        $commit = $folder->commit($data['message']);

        if (! $commit['ok']) {
            return back()->with('error', 'Exported, but the commit failed. '.$this->firstLine($commit['output']));
        }

        if (! ($data['push'] ?? true)) {
            return back()->with('success', 'Exported and committed. Not pushed.');
        }

        $status = $folder->status();

        if ($status['branch'] === null || ! $status['remote']) {
            return back()->with('success', 'Exported and committed. Nothing was pushed: '
                .($status['branch'] === null ? 'HEAD is detached.' : 'there is no "origin" remote.'));
        }

        $push = $folder->push($status['branch']);

        return $push['ok']
            ? back()->with('success', "Exported, committed and pushed to {$status['branch']}.")
            : back()->with('error', 'Exported and committed, but the push failed. '.$this->firstLine($push['output']));
    }

    /**
     * The one line of command output worth putting in a flash message. The page
     * itself shows the folder's state, so this only has to say what happened.
     */
    private function firstLine(string $output): string
    {
        $lines = array_values(array_filter(array_map(trim(...), preg_split('/\r\n|\r|\n/', $output))));

        return $lines === [] ? '' : end($lines);
    }
}
