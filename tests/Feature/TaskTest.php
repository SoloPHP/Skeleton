<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Exceptions\ConflictException;
use App\Modules\Task\TaskRepository;
use App\Validation\UniqueRule;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Psr\Http\Message\ResponseInterface;
use Solo\Validator\Field;
use Tests\TestCase;

final class TaskTest extends TestCase
{
    public function testCreateReturnsTheTaskAndItsLocation(): void
    {
        $response = $this->request('POST', '/tasks', ['title' => 'Write the docs']);

        self::assertSame(201, $response->getStatusCode());

        $task = $this->data($response);
        self::assertSame('Write the docs', $task['title'] ?? null);
        self::assertFalse($task['done'] ?? null);
        self::assertIsInt($task['id'] ?? null);
        self::assertSame('/tasks/' . $task['id'], $response->getHeaderLine('Location'));
    }

    public function testCreateStoresSubtasksAndAGeneratedUuid(): void
    {
        $response = $this->request('POST', '/tasks', [
            'uuid' => 'not-mine',
            'title' => 'Ship it',
            'subtasks' => [['title' => 'Write the code'], ['title' => 'Write the docs', 'done' => true]],
        ]);

        self::assertSame(201, $response->getStatusCode());

        $task = $this->data($response);
        // #[Generator] ignores what the client sends
        $uuid = $task['uuid'] ?? null;
        self::assertIsString($uuid);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
        );
        self::assertSame([
            ['title' => 'Write the code', 'done' => false],
            ['title' => 'Write the docs', 'done' => true],
        ], $task['subtasks'] ?? null);
    }

    public function testCreateAcceptsSubtasksAsStrings(): void
    {
        // #[Normalize] turns a plain string into the object #[Items] builds
        $response = $this->request('POST', '/tasks', [
            'title' => 'Ship it',
            'subtasks' => ['Write the code', ['title' => 'Write the docs', 'done' => true]],
        ]);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame([
            ['title' => 'Write the code', 'done' => false],
            ['title' => 'Write the docs', 'done' => true],
        ], $this->data($response)['subtasks'] ?? null);
    }

    public function testANormalizedSubtaskIsStillValidated(): void
    {
        $response = $this->request('POST', '/tasks', ['title' => 'Ship it', 'subtasks' => ['']]);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => 'VALIDATION_FAILED',
            'fields' => ['subtasks.0.title' => [['rule' => 'required']]],
        ], $this->json($response));
    }

    public function testCreateRejectsABadSubtask(): void
    {
        $response = $this->request('POST', '/tasks', ['title' => 'Ship it', 'subtasks' => [['title' => '']]]);

        self::assertSame(422, $response->getStatusCode());
        // #[Items] reports the error under the path the client used
        self::assertSame([
            'error' => 'VALIDATION_FAILED',
            'fields' => ['subtasks.0.title' => [['rule' => 'required']]],
        ], $this->json($response));
    }

    public function testWriteRoutesRequireAJsonBody(): void
    {
        // No body, so the request sends no Content-Type at all
        $response = $this->request('POST', '/tasks');

        self::assertSame(415, $response->getStatusCode());
        self::assertSame(['error' => 'UNSUPPORTED_MEDIA_TYPE'], $this->json($response));
    }

    public function testAWriteBodyIsJudgedByItsMediaType(): void
    {
        // A PATCH without a body sends no Content-Type either
        self::assertSame(415, $this->request('PATCH', '/tasks/1')->getStatusCode());

        // A parameter that merely mentions json does not make the body JSON
        self::assertSame(415, $this->postAs('text/plain; name=json')->getStatusCode());

        // A charset is a parameter of JSON, not another format
        self::assertSame(201, $this->postAs('application/json; charset=utf-8')->getStatusCode());
    }

    public function testCreateRejectsInvalidInput(): void
    {
        $response = $this->request('POST', '/tasks', ['title' => '', 'done' => 'maybe']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => 'VALIDATION_FAILED',
            'fields' => [
                'title' => [['rule' => 'required']],
                'done' => [['rule' => 'boolean']],
            ],
        ], $this->json($response));
    }

    public function testCreateRejectsATitleAnotherTaskHas(): void
    {
        $this->createTask('Ship it');

        $response = $this->request('POST', '/tasks', ['title' => 'Ship it']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => 'VALIDATION_FAILED',
            'fields' => ['title' => [['rule' => 'unique']]],
        ], $this->json($response));
    }

    public function testShowAndMissingTask(): void
    {
        $id = $this->createTask('Read me');

        self::assertSame('Read me', $this->data($this->request('GET', "/tasks/$id"))['title'] ?? null);
        self::assertSame(404, $this->request('GET', '/tasks/999')->getStatusCode());
        // {id:[1-9][0-9]*} does not match, so this is the router's 404, not the controller's
        self::assertSame(404, $this->request('GET', '/tasks/abc')->getStatusCode());
    }

    public function testAnIdNoPrimaryKeyCanTakeIsNotFound(): void
    {
        $id = $this->createTask('Read me');

        // A non-canonical id names no row: the route's pattern rejects it
        self::assertSame(404, $this->request('GET', '/tasks/007')->getStatusCode());
        self::assertSame(404, $this->request('PATCH', '/tasks/007', ['done' => true])->getStatusCode());
        self::assertSame(404, $this->request('DELETE', '/tasks/007')->getStatusCode());

        // A number past the PHP integer range matches the pattern and the primary key still cannot hold it
        self::assertSame(404, $this->request('GET', '/tasks/99999999999999999999')->getStatusCode());

        // Neither of them touched the row
        self::assertSame('Read me', $this->data($this->request('GET', "/tasks/$id"))['title'] ?? null);
    }

    public function testIndexFiltersSortsAndPaginates(): void
    {
        $this->createTask('First');
        $this->createTask('Second', done: true);
        $this->createTask('Third');

        $response = $this->request('GET', '/tasks?done=false&sort=title&per_page=1&page=2');

        self::assertSame(['Third'], $this->titles($response));
        $meta = $this->json($response)['meta'] ?? null;
        self::assertIsArray($meta);
        self::assertSame(['page' => 2, 'per_page' => 1, 'total' => 2, 'pages' => 2], $meta['pagination'] ?? null);
    }

    public function testSearchMatchesPartOfTheTitle(): void
    {
        $this->createTask('Write the docs');
        $this->createTask('Deploy');

        self::assertSame(['Write the docs'], $this->titles($this->request('GET', '/tasks?q=DOCS')));
        // A blank filter is no filter
        self::assertCount(2, $this->titles($this->request('GET', '/tasks?q=')));
        self::assertCount(2, $this->titles($this->request('GET', '/tasks?q=%20&done=')));
    }

    public function testSearchMatchesWildcardsLiterally(): void
    {
        $this->createTask('50% off');
        $this->createTask('500 errors');
        $this->createTask('snake_case');
        $this->createTask('snakeXcase');

        self::assertSame(['50% off'], $this->titles($this->request('GET', '/tasks?q=' . rawurlencode('50%'))));
        self::assertSame(['snake_case'], $this->titles($this->request('GET', '/tasks?q=e_c')));
    }

    public function testIndexFiltersByADateRange(): void
    {
        $this->createTask('Today');

        $yesterday = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
        $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');

        self::assertSame(
            ['Today'],
            $this->titles($this->request('GET', "/tasks?created[from]=$yesterday&created[to]=$tomorrow")),
        );
        // Both bounds narrow: a range that ends before the row is no match
        self::assertSame([], $this->titles($this->request('GET', '/tasks?created[to]=2000-01-01')));
    }

    public function testAnUnusableDateFilterIsIgnored(): void
    {
        $this->createTask('Today');

        // A day that does not exist rolls over in the parser, so it is no bound at all
        self::assertSame(['Today'], $this->titles($this->request('GET', '/tasks?created[from]=2026-02-31')));
        // array:from,to checks the keys of the map, not its values
        self::assertSame(['Today'], $this->titles($this->request('GET', '/tasks?created[from][]=2026-10-01')));
    }

    public function testIndexRejectsAnUnknownDateKey(): void
    {
        self::assertSame(422, $this->request('GET', '/tasks?created[since]=2026-10-01')->getStatusCode());
    }

    public function testIndexRejectsUnknownSort(): void
    {
        $response = $this->request('GET', '/tasks?sort=password');

        self::assertSame(422, $response->getStatusCode());
        $fields = $this->json($response)['fields'] ?? null;
        self::assertIsArray($fields);
        self::assertArrayHasKey('sort', $fields);
    }

    public function testUpdateChangesOnlyTheSentFields(): void
    {
        $id = $this->createTask('Keep this title');

        $response = $this->request('PATCH', "/tasks/$id", ['done' => true]);

        self::assertSame(200, $response->getStatusCode());
        $task = $this->data($response);
        self::assertSame('Keep this title', $task['title'] ?? null);
        self::assertTrue($task['done'] ?? null);
    }

    public function testUpdateRejectsAnEmptyTitle(): void
    {
        $id = $this->createTask('Title');

        self::assertSame(422, $this->request('PATCH', "/tasks/$id", ['title' => ''])->getStatusCode());
        self::assertSame(404, $this->request('PATCH', '/tasks/999', ['done' => true])->getStatusCode());
    }

    public function testUpdateRejectsAnotherTaskTitleButKeepsItsOwn(): void
    {
        $id = $this->createTask('Ship it');
        $this->createTask('Review');

        // A task's own title is not a duplicate of itself
        self::assertSame(200, $this->request('PATCH', "/tasks/$id", ['title' => 'Ship it'])->getStatusCode());

        $response = $this->request('PATCH', "/tasks/$id", ['title' => 'Review']);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame([
            'error' => 'VALIDATION_FAILED',
            'fields' => ['title' => [['rule' => 'unique']]],
        ], $this->json($response));
    }

    public function testUpdateReplacesTheSubtasks(): void
    {
        $id = $this->createTask('Ship it');
        $subtasks = [['title' => 'Write the docs', 'done' => true]];

        $response = $this->request('PATCH', "/tasks/$id", ['subtasks' => $subtasks]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame($subtasks, $this->data($response)['subtasks'] ?? null);
    }

    public function testUpdateFillsInASubtaskFlagTheClientLeftOut(): void
    {
        $id = $this->createTask('Ship it');

        // A partial input exports only what the client sent, nested fields included
        $response = $this->request('PATCH', "/tasks/$id", ['subtasks' => [['title' => 'Write the docs']]]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [['title' => 'Write the docs', 'done' => false]],
            $this->data($response)['subtasks'] ?? null,
        );

        // And what it wrote still reads back
        $read = $this->request('GET', "/tasks/$id");
        self::assertSame(200, $read->getStatusCode());
        self::assertSame(
            [['title' => 'Write the docs', 'done' => false]],
            $this->data($read)['subtasks'] ?? null,
        );
    }

    public function testATaskStoredWithoutASubtaskFlagStillReads(): void
    {
        $id = $this->createTask('Ship it');

        // A document written the way the old code wrote it: no `done` in it
        $this->app->container->get(TaskRepository::class)
            ->update($id, ['subtasks' => [['title' => 'Write the docs']]]);

        $response = $this->request('GET', "/tasks/$id");

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            [['title' => 'Write the docs', 'done' => false]],
            $this->data($response)['subtasks'] ?? null,
        );
    }

    public function testUpdateWithANullSubtaskListClearsIt(): void
    {
        $id = $this->createTask('Ship it', subtasks: [['title' => 'Write the docs']]);

        // A partial input exports `subtasks: null`, and the column keeps a list
        $response = $this->request('PATCH', "/tasks/$id", ['subtasks' => null]);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->data($response)['subtasks'] ?? null);
        self::assertSame([], $this->data($this->request('GET', "/tasks/$id"))['subtasks'] ?? null);

        // A body that sends the list as null writes an empty one too
        $created = $this->request('POST', '/tasks', ['title' => 'Ship it again', 'subtasks' => null]);

        self::assertSame(201, $created->getStatusCode());
        self::assertSame([], $this->data($created)['subtasks'] ?? null);
    }

    public function testARepositoryWriteWithoutASubtaskListStoresAnEmptyOne(): void
    {
        // A caller below HTTP has no input to fill the column's default in
        $task = $this->app->container->get(TaskRepository::class)
            ->create(['uuid' => 'uuid-without-subtasks', 'title' => 'Ship it']);

        self::assertSame([], $task->subtasks);
    }

    public function testATitleAnotherRequestTookFirstIsAConflict(): void
    {
        $tasks = $this->app->container->get(TaskRepository::class);
        $tasks->create(['uuid' => 'uuid-1', 'title' => 'Ship it']);

        // The `unique` rule passed, so the table's index is what answers here
        $this->expectException(ConflictException::class);
        $tasks->create(['uuid' => 'uuid-2', 'title' => 'Ship it']);
    }

    public function testUniqueComparesTheKeyItIsGiven(): void
    {
        $tasks = $this->app->container->get(TaskRepository::class);
        $tasks->create(['uuid' => 'uuid-1', 'title' => 'Ship it']);
        $tasks->create(['uuid' => 'uuid-2', 'title' => 'Review']);

        $rule = new UniqueRule($this->app->container->get(Connection::class));
        // The fourth parameter names the key the changed row is found by
        $payload = new Field('title', ['uuid' => 'uuid-2']);

        // Its own row is not a duplicate of itself
        self::assertNull($rule->validate('Review', ['tasks', 'title', '{uuid}', 'uuid'], $payload));
        self::assertSame(
            ['rule' => 'unique'],
            $rule->validate('Ship it', ['tasks', 'title', '{uuid}', 'uuid'], $payload),
        );
    }

    public function testSchemaDescribesWhatTheClientMaySend(): void
    {
        $schema = $this->data($this->request('GET', '/tasks/schema'));

        $title = $schema['title'] ?? null;
        self::assertIsArray($title);
        self::assertSame('string', $title['type'] ?? null);
        self::assertTrue($title['required'] ?? null);

        $subtasks = $schema['subtasks'] ?? null;
        self::assertIsArray($subtasks);
        self::assertSame('array', $subtasks['type'] ?? null);

        // A generated field is not something the client sends
        self::assertArrayNotHasKey('uuid', $schema);
    }

    public function testDelete(): void
    {
        $id = $this->createTask('Delete me');

        $response = $this->request('DELETE', "/tasks/$id");

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame(404, $this->request('GET', "/tasks/$id")->getStatusCode());
    }

    /** @param list<array{title: string, done?: bool}> $subtasks */
    private function createTask(string $title, bool $done = false, array $subtasks = []): int
    {
        $id = $this->data($this->request('POST', '/tasks', [
            'title' => $title,
            'done' => $done,
            'subtasks' => $subtasks,
        ]))['id'] ?? null;
        self::assertIsInt($id);

        return $id;
    }

    /** A POST /tasks whose Content-Type is exactly what the test gives */
    private function postAs(string $contentType): ResponseInterface
    {
        $request = (new ServerRequest('POST', '/tasks'))
            ->withHeader('Content-Type', $contentType)
            ->withBody(Stream::create('{"title": "Ship it"}'));

        return $this->app->handle($request);
    }

    /** @return list<mixed> The titles of a list response, in its order */
    private function titles(ResponseInterface $response): array
    {
        $titles = [];
        foreach ($this->data($response) as $task) {
            self::assertIsArray($task);
            $titles[] = $task['title'] ?? null;
        }

        return $titles;
    }
}
