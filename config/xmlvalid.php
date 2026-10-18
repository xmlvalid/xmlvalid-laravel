<?php

return [

    /*
    | An API key (xv_…) from https://xmlvalid.com/dashboard/api
    */
    'key' => env('XMLVALID_API_KEY'),

    'base_url' => env('XMLVALID_BASE_URL', 'https://xmlvalid.com/api/v1'),

    /*
    | Seconds a validation is waited for in all (the API keeps results for 24
    | hours, so a longer one can be fetched later with Xmlvalid::validation()).
    */
    'timeout' => (float) env('XMLVALID_TIMEOUT', 900),

    /*
    | Seconds the API holds a new validation's request before answering with
    | its status (0-60). Small documents are usually done within it.
    */
    'wait' => (int) env('XMLVALID_WAIT', 10),

    /*
    | Times a request refused with 429 rate_limited is retried.
    */
    'retries' => (int) env('XMLVALID_RETRIES', 3),

    'webhook' => [

        /*
        | The endpoint's signing secret (Dashboard → Webhooks). Without it the
        | webhook route is not registered.
        */
        'secret' => env('XMLVALID_WEBHOOK_SECRET'),

        /*
        | Where xmlvalid posts the events: https://your-app.com/xmlvalid/webhook
        */
        'path' => env('XMLVALID_WEBHOOK_PATH', 'xmlvalid/webhook'),

        /*
        | Seconds a signature stays valid, against replayed requests.
        */
        'tolerance' => 300,

        'middleware' => [],
    ],

];
