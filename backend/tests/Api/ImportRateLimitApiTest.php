<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\ImportJob;
use App\Tests\TestKernel;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\UploadedFile;

final class ImportRateLimitApiTest extends TestCase
{
    use AuthenticationTrait;

    /** @var list<int> */
    private array $createdJobIds = [];

    protected function setUp(): void
    {
        TestKernel::$entityManager->getConnection()->executeStatement('DELETE FROM import_rate_limits');
    }

    protected function tearDown(): void
    {
        foreach ($this->createdJobIds as $id) {
            $job = TestKernel::$entityManager->find(ImportJob::class, $id);
            if ($job === null) {
                continue;
            }
            $path = dirname(__DIR__, 2) . '/storage/' . $job->getStoredPath();
            TestKernel::$entityManager->remove($job);
            TestKernel::$entityManager->flush();
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function testSixthRequestIsRejectedWithoutCreatingAJob(): void
    {
        $before = (int) TestKernel::$entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM import_jobs');
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $response = $this->upload();
            self::assertSame(202, $response->getStatusCode());
            $this->createdJobIds[] = (int) $this->payload($response)['id'];
        }

        $rejectedTemporary = $this->copyFixture();
        $response = $this->upload($rejectedTemporary);
        self::assertSame(429, $response->getStatusCode());
        self::assertGreaterThan(0, (int) $response->getHeaderLine('Retry-After'));
        self::assertSame($before + 5, (int) TestKernel::$entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM import_jobs'));
        self::assertFileExists($rejectedTemporary, 'Rate limiter must run before the upload is persisted.');
        unlink($rejectedTemporary);
    }

    public function testExpiredWindowAllowsAnotherRequest(): void
    {
        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            $response = $this->upload();
            $this->createdJobIds[] = (int) $this->payload($response)['id'];
        }
        self::assertSame(429, $this->uploadAndRemoveRejectedTemporary()->getStatusCode());

        TestKernel::$entityManager->getConnection()->executeStatement("UPDATE import_rate_limits SET window_started_at = CURRENT_TIMESTAMP - INTERVAL '61 seconds'");
        $response = $this->upload();
        self::assertSame(202, $response->getStatusCode());
        $this->createdJobIds[] = (int) $this->payload($response)['id'];
    }

    private function upload(?string $temporary = null): \Psr\Http\Message\ResponseInterface
    {
        $temporary ??= $this->copyFixture();
        $upload = new UploadedFile($temporary, 'import-example.xlsx', null, filesize($temporary));
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/imports')->withUploadedFiles(['file' => $upload]);
        return TestKernel::app()->handle($this->authorized($request));
    }

    private function uploadAndRemoveRejectedTemporary(): \Psr\Http\Message\ResponseInterface
    {
        $temporary = $this->copyFixture();
        $response = $this->upload($temporary);
        if (is_file($temporary)) {
            unlink($temporary);
        }
        return $response;
    }

    private function copyFixture(): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'rate-limit-test-');
        copy(dirname(__DIR__) . '/Fixtures/import-example.xlsx', $temporary);
        return $temporary;
    }

    /** @return array<string, mixed> */
    private function payload(\Psr\Http\Message\ResponseInterface $response): array
    {
        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }
}
