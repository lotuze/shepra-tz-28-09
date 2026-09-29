<?php

declare(strict_types=1);

namespace App\Controller;

use App\Import\ImportSubmissionService;
use App\Repository\ImportErrorRepository;
use App\Repository\ImportJobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final class ImportController
{
    public function __construct(
        private readonly ImportSubmissionService $submissions,
        private readonly ImportJobRepository $jobs,
        private readonly ImportErrorRepository $errors,
    ) {}

    public function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        if ($file === null) { return $this->json($response, ['error' => 'Multipart field "file" is required.'], 400); }
        try { $job = $this->submissions->submit($file); }
        catch (\InvalidArgumentException $exception) { return $this->json($response, ['error' => $exception->getMessage()], 400); }
        catch (\Throwable) { return $this->json($response, ['error' => 'Unable to queue import.'], 500); }
        return $this->json($response, [
            'id' => $job->getId(),
            'status' => $job->getStatus(),
            'statusUrl' => '/api/imports/' . $job->getId(),
        ], 202);
    }

    public function show(ServerRequestInterface $request, ResponseInterface $response, array $arguments): ResponseInterface
    {
        $job = $this->jobs->findById((int) $arguments['id']);
        if ($job === null) { return $this->json($response, ['error' => 'Import job not found.'], 404); }
        $errors = $this->errors->firstForJob($job);
        return $this->json($response, [
            'id' => $job->getId(), 'originalFilename' => $job->getOriginalFilename(), 'status' => $job->getStatus(),
            'totalRows' => $job->getTotalRows(), 'processedRows' => $job->getProcessedRows(),
            'successfulRows' => $job->getSuccessfulRows(), 'failedRows' => $job->getFailedRows(),
            'progress' => $job->progress(),
            'errors' => array_map(static fn ($error): array => [
                'id' => $error->getId(), 'rowNumber' => $error->getRowNumber(), 'externalCode' => $error->getExternalCode(),
                'severity' => $error->getSeverity(), 'message' => $error->getMessage(), 'rawData' => $error->getRawData(),
                'createdAt' => $error->getCreatedAt()->format(DATE_ATOM),
            ], $errors),
            'errorsTotal' => $this->errors->countForJob($job),
            'createdAt' => $job->getCreatedAt()->format(DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
            'fatalError' => $job->getFatalError(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function json(ResponseInterface $response, array $payload, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
