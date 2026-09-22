<?php

namespace Tests;

use App\Models\Domain;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Import the design folder, then drop the domains it brought.
     *
     * The design folder holds drafted domains now, and a test that builds its
     * own domain world has to start from none — otherwise it is testing
     * whatever the designer drafted this week rather than the tool. Tests that
     * are about the design folder's own contents import it directly instead.
     */
    protected function importDesignWithoutDomains(): void
    {
        $this->artisan('design:import');

        // Their cards go with them, the same way deleting one in the app does.
        Domain::query()->delete();
    }

    /**
     * The slugs of the character files in the design folder.
     *
     * A test about the design folder reads the folder rather than listing the
     * characters the designer happens to have drafted this week: a new one is
     * covered the day it is written, and none of these tests go red because of
     * it. Same rule as importDesignWithoutDomains().
     *
     * @return array<int, string>
     */
    protected function characterSlugs(): array
    {
        $files = glob(base_path('design/players/*.json'));

        // A character file is the one with a signature card list in it, which
        // is what the importer looks for too.
        return array_values(array_map(
            fn (string $file) => basename($file, '.json'),
            array_filter(
                $files,
                fn (string $file) => isset(json_decode(file_get_contents($file), true)['signatureCards']),
            ),
        ));
    }
}
