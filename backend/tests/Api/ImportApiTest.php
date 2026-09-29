<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ImportJob;
use App\Import\ImportSubmissionService;
use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;

final class ImportApiTest extends TestCase
{
    use AuthenticationTrait;

    protected function setUp(): void
    {
        TestKernel::$entityManager->getConnection()->executeStatement('DELETE FROM import_rate_limits');
    }
    public function testUploadReturns202AndStatusEndpoint(): void
    {
        $temporary = $this->copyFixture();
        $upload = new UploadedFile($temporary, 'import-example.xlsx', null, filesize($temporary));
        $request = $this->authorized((new ServerRequestFactory())->createServerRequest('POST', '/api/imports')->withUploadedFiles(['file' => $upload]));
        $response = TestKernel::app()->handle($request);
        $payload = $this->payload($response);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame(ImportJob::QUEUED, $payload['status']);
        self::assertSame('/api/imports/' . $payload['id'], $payload['statusUrl']);

        $status = TestKernel::app()->handle($this->authorized((new ServerRequestFactory())->createServerRequest('GET', $payload['statusUrl'])));
        self::assertSame(200, $status->getStatusCode());
        self::assertSame(0, $this->payload($status)['progress']);
        $this->removeJob((int) $payload['id']);
    }

    public function testRejectsWrongExtensionAndMime(): void
    {
        $temporary = $this->copyFixture();
        $request = $this->authorized((new ServerRequestFactory())->createServerRequest('POST', '/api/imports')->withUploadedFiles([
            'file' => new UploadedFile($temporary, 'import.csv', null, filesize($temporary)),
        ]));
        self::assertSame(400, TestKernel::app()->handle($request)->getStatusCode());
        if (is_file($temporary)) { unlink($temporary); }

        $text = tempnam(sys_get_temp_dir(), 'bad-xlsx-');
        file_put_contents($text, 'not an xlsx');
        $request = $this->authorized((new ServerRequestFactory())->createServerRequest('POST', '/api/imports')->withUploadedFiles([
            'file' => new UploadedFile($text, 'import.xlsx', null, filesize($text)),
        ]));
        self::assertSame(400, TestKernel::app()->handle($request)->getStatusCode());
        if (is_file($text)) { unlink($text); }
    }

    public function testRejectsOversizedFile(): void
    {
        $temporary = $this->copyFixture();
        $service = new ImportSubmissionService(
            TestKernel::$entityManager,
            TestKernel::$container->get(MessageBusInterface::class),
            dirname(__DIR__, 2) . '/storage',
            1,
        );
        $this->expectException(\InvalidArgumentException::class);
        try { $service->submit(new UploadedFile($temporary, 'import.xlsx', null, filesize($temporary))); }
        finally { if (is_file($temporary)) { unlink($temporary); } }
    }

    private function copyFixture(): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'import-test-');
        copy(dirname(__DIR__) . '/Fixtures/import-example.xlsx', $temporary);
        return $temporary;
    }

    private function removeJob(int $id): void
    {
        $job = TestKernel::$entityManager->find(ImportJob::class, $id);
        if ($job !== null) {
            $path = dirname(__DIR__, 2) . '/storage/' . $job->getStoredPath();
            TestKernel::$entityManager->remove($job); TestKernel::$entityManager->flush();
            if (is_file($path)) { unlink($path); }
        }
    }

    /** @return array<string, mixed> */
    private function payload(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
