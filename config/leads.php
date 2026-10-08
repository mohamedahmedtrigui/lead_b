<?php

return [

    /*
    |--------------------------------------------------------------------------
    | NRP (No Response) workflow
    |--------------------------------------------------------------------------
    |
    | A lead becomes "NRP final" after `max_attempts` unanswered calls.
    | Two NRP attempts must be separated by at least `min_interval_minutes`
    | so a dispatcher cannot burn all attempts in a few seconds.
    |
    */

    'nrp' => [
        'max_attempts' => (int) env('NRP_MAX_ATTEMPTS', 3),
        'min_interval_minutes' => (int) env('NRP_MIN_INTERVAL_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV import
    |--------------------------------------------------------------------------
    */

    'import' => [
        // Timezone of the "Created" column exported by the lead source.
        'timezone' => env('LEADS_IMPORT_TIMEZONE', 'Africa/Tunis'),
        // Prefix applied to local 8-digit numbers (Tunisia = 216).
        'default_country_code' => env('LEADS_DEFAULT_COUNTRY_CODE', '216'),
        'local_number_length' => 8,
        'max_file_size_kb' => 5120,
        'created_format' => 'm/d/Y g:ia',
    ],

];
