<?php

return [
    'token' => env('FONNTE_API_TOKEN', ''),
    'api_url' => env('FONNTE_API_URL', 'https://api.fonnte.com/send'),
    'country_code' => env('FONNTE_COUNTRY_CODE', '62'),
    'timeout' => (int) env('FONNTE_TIMEOUT', 15),
];
