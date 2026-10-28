<?php

declare(strict_types=1);

/*
 * Global middleware, outermost first. Each one is resolved from the container
 * only when a request reaches it.
 */

use App\Http\Middleware\ErrorMiddleware;
use App\Http\Middleware\JsonBodyMiddleware;
use Solo\Application\RoutingMiddleware;

return [
    // Turns every exception thrown below it, routing errors included, into a JSON error
    ErrorMiddleware::class,
    // Decodes application/json bodies, so getParsedBody() works for JSON as it does for forms
    JsonBodyMiddleware::class,
    // Matches the route and runs its group and route middleware; 404 and 405 are thrown
    RoutingMiddleware::class,
];
