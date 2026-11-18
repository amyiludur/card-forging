<?php

return [
    /*
     * The design folder: the rules markdown, the scenario JSON and the card
     * art, tracked by git and the source of truth. design:import and
     * design:export read and write it unless given --path, and uploaded art is
     * stored under its art/ directory. Tests point this at a temporary folder
     * so that nothing they upload lands in the real one.
     */
    'design_path' => env('CARDFORGE_DESIGN_PATH', base_path('design')),
];
