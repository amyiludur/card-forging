<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * One place up or down among a row's siblings, in one request. The siblings
 * are renumbered 0..n first, so two rows that somehow share a sort still swap
 * rather than tie — which is what moving with two separate saves got wrong.
 * The first row moving up, or the last moving down, stays where it is.
 */
trait MovesRows
{
    protected function moveAmong(Request $request, HasMany $siblings, Model $row): void
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];

        DB::transaction(function () use ($siblings, $row, $direction) {
            // The relation is already ordered by sort, then id.
            $rows = $siblings->get()->values();
            $from = $rows->search(fn (Model $r) => $r->is($row));
            $to = $direction === 'up' ? $from - 1 : $from + 1;

            if ($from === false || $to < 0 || $to >= $rows->count()) {
                return;
            }

            $ordered = $rows->all();
            [$ordered[$from], $ordered[$to]] = [$ordered[$to], $ordered[$from]];

            foreach ($ordered as $sort => $sibling) {
                if ($sibling->sort !== $sort) {
                    $sibling->update(['sort' => $sort]);
                }
            }
        });
    }
}
