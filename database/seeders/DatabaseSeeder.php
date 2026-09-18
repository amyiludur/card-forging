<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * The design folder is the seed: `php artisan migrate:fresh --seed` gives a
     * database that matches what is committed to git.
     */
    public function run(): void
    {
        $this->command->call('design:import');
    }
}
