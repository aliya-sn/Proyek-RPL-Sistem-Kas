<?php

use App\Core\Router;
use App\Core\Response;

// REST API Routes placeholder
Router::group(['prefix' => 'api'], function() {
    Router::get('/status', function() {
        Response::json([
            'status' => 'healthy',
            'app' => config('app.name'),
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    });
});
