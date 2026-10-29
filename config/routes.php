<?php

declare(strict_types=1);

/*
 * Every route of the application. A module keeps its routes in its own class,
 * and adding a module means adding one line here.
 */

use App\Modules\Home\HomeController;
use App\Modules\Task\TaskRoutes;
use Solo\Router\RouteCollector;

return function (RouteCollector $r): void {
    $r->get('/', HomeController::class)->name('home');

    (new TaskRoutes())->register($r);
};
