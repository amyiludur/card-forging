<?php

namespace App\Console\Commands;

use App\Support\Pocket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

/**
 * Writes the pocket page to a file: every rule and every card in one page of
 * HTML that needs no server, no network and no fonts.
 *
 * This is the command the published copy is built with
 * (.github/workflows/pocket.yml), and it is the one to run to carry the design
 * somewhere by hand — the file is whole, so it opens off a phone's own storage
 * as happily as off a web address.
 *
 * It reads the database, which is the editor's working copy, so the loop is the
 * same as every other output: design:import, edit, then build.
 */
class BuildPocket extends Command
{
    protected $signature = 'pocket:build
                            {--out= : File to write (defaults to storage/app/pocket/index.html)}';

    protected $description = 'Write the rules and every card to one self-contained HTML file';

    public function handle(): int
    {
        $data = Pocket::make()->data();
        $html = View::make('pocket', $data)->render();

        $path = $this->option('out') ?: storage_path('app/pocket/index.html');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->error("Could not create {$directory}.");

            return self::FAILURE;
        }

        if (file_put_contents($path, $html) === false) {
            $this->error("Could not write {$path}.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Wrote %s — %d %s, %d %s, %s.',
            $path,
            $data['counts']['documents'],
            Str::plural('document', $data['counts']['documents']),
            $data['counts']['cards'],
            Str::plural('card', $data['counts']['cards']),
            $this->size(strlen($html)),
        ));

        // Said here as well as on the page: a page carried to a table should
        // say which of its numbers are not decided yet.
        if ($data['placeholderNumbers'] !== []) {
            $this->warn('It quotes '.count($data['placeholderNumbers']).' placeholder numbers: '
                .implode(', ', $data['placeholderNumbers']).'.');
        }

        return self::SUCCESS;
    }

    private function size(int $bytes): string
    {
        return $bytes < 1024 * 1024
            ? round($bytes / 1024).' KB'
            : round($bytes / 1024 / 1024, 1).' MB';
    }
}
