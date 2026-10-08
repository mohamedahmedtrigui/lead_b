<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Interest scoring weights
    |--------------------------------------------------------------------------
    |
    | The interest score (0-100) is computed server-side from the qualification
    | answers. Each rule adds its weight when it matches; the total is capped
    | at 100. Tune the weights here without touching the scoring code.
    |
    */

    'weights' => [
        'daily_transport' => 20,
        'recurring_route' => 15,
        'multiple_passengers' => 15,
        'accepts_shared' => 15,
        'maybe_shared' => 7,
        'b2b_need' => 20,
        'requests_quotation' => 10,
        'requests_callback' => 10,
        'positive_experience' => 5,
    ],

    'positive_experience_min_rating' => 4,

    'max_score' => 100,

    /*
    | Lower bound (inclusive) of each interest level.
    */
    'levels' => [
        'HOT' => 80,
        'WARM' => 60,
        'INTERESTED' => 40,
        'LOW' => 0,
    ],

    'summary_note_min_length' => 20,

];
