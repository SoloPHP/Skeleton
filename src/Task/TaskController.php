<?php

declare(strict_types=1);

namespace App\Task;

use App\Http\Exceptions\NotFoundException;
use App\Http\JsonResponse;
use App\Task\Inputs\TaskIndexInput;
use App\Task\Inputs\TaskStoreInput;
use App\Task\Inputs\TaskUpdateInput;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Solo\Input\InputFactory;
use Solo\Input\Inputs;
use Solo\Router\UrlGenerator;

/**
 * A thin controller: it turns the request into an input DTO, hands it to the
 * repository and turns the result into JSON. Validation errors and missing
 * records are thrown, and ErrorMiddleware answers them.
 *
 * The container builds it and injects the constructor's dependencies by type.
 */
final readonly class TaskController
{
    public function __construct(
        private InputFactory $inputs,
        private TaskRepository $tasks,
        private UrlGenerator $urls,
    ) {
    }

    public function index(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->inputs->fromQuery(TaskIndexInput::class, $request);
        $criteria = $input->criteria();

        $tasks = $this->tasks->read()->findBy(
            $criteria,
            $input->sort,
            limit: $input->per_page,
            offset: ($input->page - 1) * $input->per_page,
        );

        return JsonResponse::page(
            $response,
            TaskResource::collection($tasks),
            $input->page,
            $input->per_page,
            $this->tasks->read()->count($criteria),
        );
    }

    /** @param array{id: string} $params */
    public function show(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params,
    ): ResponseInterface {
        return JsonResponse::data($response, TaskResource::make($this->find($params['id'])));
    }

    public function store(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $input = $this->inputs->fromBody(TaskStoreInput::class, $request);
        $task = $this->tasks->create(Inputs::toArray($input));

        // The path comes from the route's name, so it follows the route if its path changes
        return JsonResponse::data($response, TaskResource::make($task), 201)
            ->withHeader('Location', $this->urls->generate('tasks.show', ['id' => $task->id]));
    }

    /** @param array{id: string} $params */
    public function update(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params,
    ): ResponseInterface {
        $task = $this->find($params['id']);
        $input = $this->inputs->fromBody(TaskUpdateInput::class, $request);

        // A partial input gives only the fields the client sent
        $task = $this->tasks->change($task, Inputs::toArray($input));

        return JsonResponse::data($response, TaskResource::make($task));
    }

    /** @param array{id: string} $params */
    public function destroy(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $params,
    ): ResponseInterface {
        $this->tasks->delete($this->find($params['id'])->id);

        return $response->withStatus(204);
    }

    private function find(string $id): Task
    {
        return $this->tasks->read()->find((int) $id) ?? throw new NotFoundException();
    }
}
