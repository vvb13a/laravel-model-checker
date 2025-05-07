<?php

use Vvb13a\LaravelResponseChecker\Checks;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Checks
    |--------------------------------------------------------------------------
    |
    | Define the default list of CheckInterface implementations that should be
    | executed for each checkable URL. Add the fully qualified class names.
    | These checks will be resolved from the service container.
    |
    */
    'checks' => [
        Checks\StatusCodeCheck::class,
        Checks\TitleCheck::class,
        Checks\MetaDescriptionCheck::class,
        Checks\ImageAltTextCheck::class,
        Checks\H1Check::class
    ],

    /*
    |--------------------------------------------------------------------------
    | Store Success Findings
    |--------------------------------------------------------------------------
    |
    | Set this to true if you want to store a Finding record even when a
    | check passes successfully (FindingLevel::SUCCESS). Setting it to false
    | will only store INFO, WARNING, and ERROR level findings.
    |
    */
    'store_success_findings' => true,

    /*
    |--------------------------------------------------------------------------
    | Queue Name
    |--------------------------------------------------------------------------
    |
    | Specify the queue connection name that the RunModelChecksJob should be
    | dispatched to. Use null to use the default queue connection.
    |
    */
    'queue_name' => env('MODEL_CHECKER_QUEUE_NAME', 'default'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connection
    |--------------------------------------------------------------------------
    |
    | Specify the queue connection name that the RunModelChecksJob should be
    | dispatched to. Use null to use the default queue connection.
    |
    */
    'queue_connection' => env('MODEL_CHECKER_QUEUE_CONNECTION', null),

    /*
     |--------------------------------------------------------------------------
     | HTTP Request Options
     |--------------------------------------------------------------------------
     |
     | Define default options for the Illuminate HTTP client used for checks.
     | See https://laravel.com/docs/http-client#request-options
     |
     */
    'http_options' => [
        'timeout' => 15, // Default request timeout in seconds
        'connect_timeout' => 10, // Default connection timeout
        // 'allow_redirects' => false, // Example: uncomment to disable redirects
        // 'verify' => false, // Example: uncomment to disable SSL verification (use with caution)
    ],

];
