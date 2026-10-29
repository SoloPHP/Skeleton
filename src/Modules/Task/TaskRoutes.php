<?php

declare(strict_types=1);

namespace App\Modules\Task;

use App\Http\Middleware\RequireJsonMiddleware;
use Solo\Router\RouteCollector;

final class TaskRoutes
{
    public function register(RouteCollector $r): void
    {
        $r->group('/tasks', function (RouteCollector $r): void {
            $r->get('', [TaskController::class, 'index'])->name('tasks.index');
            $r->get('/schema', [TaskController::class, 'schema'])->name('tasks.schema');

            // A body that is not JSON is a 415; DELETE has none, so it asks for nothing
            $r->post('', [TaskController::class, 'store'])
                ->name('tasks.store')
                ->middleware(RequireJsonMiddleware::class);

            // A canonical id, so a path like /tasks/007 is no row's path either
            $r->get('/{id:[1-9][0-9]*}', [TaskController::class, 'show'])->name('tasks.show');
            $r->patch('/{id:[1-9][0-9]*}', [TaskController::class, 'update'])
                ->name('tasks.update')
                ->middleware(RequireJsonMiddleware::class);
            $r->delete('/{id:[1-9][0-9]*}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        });
    }
}
