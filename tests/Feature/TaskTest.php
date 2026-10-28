<?php

declare(strict_types=1);

namespace Tests\Feature;

use Psr\Http\Message\ResponseInterface;
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

    public function testShowAndMissingTask(): void
    {
        $id = $this->createTask('Read me');

        self::assertSame('Read me', $this->data($this->request('GET', "/tasks/$id"))['title'] ?? null);
        self::assertSame(404, $this->request('GET', '/tasks/999')->getStatusCode());
        // {id:\d+} does not match, so this is the router's 404, not the controller's
        self::assertSame(404, $this->request('GET', '/tasks/abc')->getStatusCode());
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

    public function testDelete(): void
    {
        $id = $this->createTask('Delete me');

        $response = $this->request('DELETE', "/tasks/$id");

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertSame(404, $this->request('GET', "/tasks/$id")->getStatusCode());
    }

    private function createTask(string $title, bool $done = false): int
    {
        $id = $this->data($this->request('POST', '/tasks', ['title' => $title, 'done' => $done]))['id'] ?? null;
        self::assertIsInt($id);

        return $id;
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
