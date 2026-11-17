<?php

namespace App\Middleware;

use App\Core\Request;

interface MiddlewareInterface
{
    /**
     * Handle incoming request before passing to controller.
     *
     * @param Request $request
     * @param mixed ...$params
     * @return void
     */
    public function handle(Request $request, ...$params): void;
}
