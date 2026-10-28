<?php

declare(strict_types=1);

namespace App\Task;

use Solo\Router\RouteCollector;

final class TaskRoutes
{
    public function register(RouteCollector $r): void
    {
        $r->group('/tasks', function (RouteCollector $r): void {
            $r->get('', [TaskController::class, 'index'])->name('tasks.index');
            $r->post('', [TaskController::class, 'store'])->name('tasks.store');
            $r->get('/{id:\d+}', [TaskController::class, 'show'])->name('tasks.show');
            $r->patch('/{id:\d+}', [TaskController::class, 'update'])->name('tasks.update');
            $r->delete('/{id:\d+}', [TaskController::class, 'destroy'])->name('tasks.destroy');
        });
    }
}
