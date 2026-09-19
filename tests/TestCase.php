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
}
